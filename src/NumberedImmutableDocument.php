<?php

namespace SilverShop\Invoicing;

use LeKoala\CmsActions\CustomAction;
use LeKoala\CmsActions\CustomLink;
use SilverShop\ORM\FieldType\ShopCurrency;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * Shared behaviour for immutable, sequentially-numbered financial documents (invoice, credit memo):
 * assigns a `Number` (prefixed via the class's `number_prefix` config) and `Issued` date on create,
 * and blocks any change or deletion afterwards. The using class must declare `Number` + `Issued` in
 * `$db` and a `number_prefix` config.
 */
trait NumberedImmutableDocument
{
    /**
     * Numbering scheme: 'year' → per-year series that resets each year (e.g. INV-2026-00001);
     * 'continuous' → one ever-increasing series (e.g. INV-00001). Padding is the minimum digit
     * width; start is the first number in each series.
     */
    private static string $number_series = 'year';

    private static int $number_padding = 5;

    private static int $number_start = 1;

    /**
     * Fields allowed to change after a document is issued. The financial content stays frozen; these
     * are status flags only (e.g. the "emailed to customer" timestamp).
     */
    private static array $mutable_fields = ['EmailSent'];

    protected function onBeforeWrite(): void
    {
        // Assign the gap-free number + issue date only when the document becomes Issued — never for a
        // draft, so a draft consumes no number and can be discarded without leaving a gap in the series.
        if ($this->isIssued() && !$this->Number) {
            if (!$this->Issued) {
                $this->Issued = DBDatetime::now()->getValue();
            }
            $this->Number = $this->generateNumber();
        }

        // Freeze the document once issued (drafts stay fully editable). The issue transition is allowed.
        $wasAlreadyIssued = $this->isInDB() && $this->isIssued() && !$this->isChanged('Status');
        if ($wasAlreadyIssued && $this->hasImmutableChanges()) {
            throw new ValidationException(static::class . ' records are immutable once issued.');
        }

        parent::onBeforeWrite();
    }

    /**
     * A finalised document (numbered, frozen). Drafts are editable and unnumbered until issued.
     */
    public function isIssued(): bool
    {
        return $this->Status === 'Issued';
    }

    /**
     * Finalise a draft: assign the gap-free number + issue date and freeze it. No-op if already issued.
     */
    public function issue(): void
    {
        if ($this->isIssued()) {
            return;
        }

        $this->Status = 'Issued';
        $this->write();
    }

    /**
     * True when a frozen (non-{@link $mutable_fields}) field has changed on an already-issued document.
     */
    protected function hasImmutableChanges(): bool
    {
        $allowed = array_merge((array) $this->config()->get('mutable_fields'), ['LastEdited']);
        $changed = array_keys($this->getChangedFields(true, DataObject::CHANGE_VALUE));

        return (bool) array_diff($changed, $allowed);
    }

    /**
     * Gap-free, concurrency-safe number: reserved atomically from a per-series counter
     * ({@link DocumentNumberSequence}) inside the document's write transaction.
     */
    protected function generateNumber(): string
    {
        $prefix = (string) $this->config()->get('number_prefix');
        $padding = max(1, (int) $this->config()->get('number_padding'));
        $start = max(1, (int) $this->config()->get('number_start'));
        $yearly = $this->config()->get('number_series') !== 'continuous';

        $issued = (string) $this->Issued ?: DBDatetime::now()->getValue();
        $year = (int) date('Y', strtotime($issued));

        $seriesKey = rtrim($prefix, '-') . ($yearly ? '-' . $year : '');
        $next = DocumentNumberSequence::reserve($seriesKey, $start);
        $sequence = str_pad((string) $next, $padding, '0', STR_PAD_LEFT);

        return $yearly ? $prefix . $year . '-' . $sequence : $prefix . $sequence;
    }

    public function canEdit($member = null): bool
    {
        // Drafts are editable by staff; issued documents are frozen.
        return !$this->isIssued() && Permission::check('CMS_ACCESS', 'any', $member);
    }

    public function canDelete($member = null): bool
    {
        // Drafts can be discarded; issued documents are permanent (cancel via a credit memo instead).
        return !$this->isIssued() && Permission::check('CMS_ACCESS', 'any', $member);
    }

    public function canCreate($member = null, $context = []): bool
    {
        // Staff can create a draft (from an order); it is finalised via the Issue action.
        return Permission::check('CMS_ACCESS', 'any', $member);
    }

    /**
     * Staff (CMS access) or the customer who placed the order may view the document. This gates the
     * customer-facing PDF download in {@link ShopDocumentController}.
     */
    public function canView($member = null): bool
    {
        $member = $member ?: Security::getCurrentUser();

        if (Permission::check('CMS_ACCESS', 'any', $member)) {
            return true;
        }

        $order = $this->Order();

        return (bool) ($order && $order->exists() && $member && (int) $order->MemberID === (int) $member->ID);
    }

    /**
     * Human-readable title (used in CMS breadcrumbs, dropdowns, etc.) — the document number plus the
     * customer, instead of the default "#<id>".
     */
    public function getTitle(): string
    {
        $number = (string) $this->Number ?: 'Draft';
        $order = $this->Order();
        if ($order && $order->exists() && $order->Name) {
            return $number . ' — ' . $order->Name;
        }

        return $number;
    }

    /**
     * Template helpers: split the snapshot lines into product lines (the itemised table) and
     * adjustment lines (the totals block), with the item subtotal.
     */
    public function ItemLines()
    {
        return $this->Lines()->filter('Kind', 'Item');
    }

    public function ModifierLines()
    {
        // Hide zero-amount adjustments (e.g. a $0 discount) — they only add noise.
        return $this->Lines()->filter('Kind', 'Modifier')->exclude('Amount', 0);
    }

    public function Subtotal(): DBField
    {
        return DBField::create_field(ShopCurrency::class, (float) $this->ItemLines()->sum('Amount'));
    }

    /**
     * Absolute URL of the printable document (HTML view). Append "/pdf" for the PDF.
     * Uses the class's `document_url_segment` config (e.g. "invoice", "creditmemo").
     */
    public function DocumentURL(): string
    {
        $segment = (string) $this->config()->get('document_url_segment');
        if (!$segment || !$this->ID) {
            return '';
        }

        return Director::absoluteURL('invoicing/' . $segment . '/' . $this->ID);
    }

    /**
     * Append a note to this document's history (see {@link ShopDocumentComment}). Used both by staff
     * (CMS) and by the module itself to log events such as "invoice emailed to customer".
     */
    public function addComment(
        string $body,
        bool $notifyCustomer = false,
        bool $visibleOnFront = false,
        ?Member $author = null
    ): ShopDocumentComment {
        $comment = ShopDocumentComment::create();
        $comment->Body = $body;
        $comment->NotifyCustomer = $notifyCustomer;
        $comment->VisibleOnFront = $visibleOnFront;
        $comment->DocumentID = $this->ID;
        $comment->DocumentClass = static::class;
        if ($author) {
            $comment->AuthorID = $author->ID;
        }
        $comment->write();

        return $comment;
    }

    /**
     * Add a "Preview" tab that embeds the rendered document in an iframe, so the whole
     * invoice / credit memo is visible inside the CMS without leaving the record.
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // New (unsaved) draft: explain the Add-new flow. Lines are snapshotted on save (onAfterWrite).
        if (!$this->isInDB()) {
            $fields->addFieldToTab('Root.Main', LiteralField::create(
                'NewDraftHint',
                '<p class="message notice" style="display:flex;align-items:flex-start;gap:.5em">'
                . '<span class="font-icon-info-circled" aria-hidden="true"></span><span>'
                . 'Saving creates a <strong>draft</strong> invoice from the order&rsquo;s still-to-invoice items. '
                . 'You can then adjust the line quantities, add a note, and <strong>Issue</strong> it.'
                . '</span></p>'
            ));
        }

        // Show who/what the document is for, next to the order relation.
        $order = $this->Order();
        if ($order && $order->exists()) {
            $summary = [
                ReadonlyField::create('SummaryCustomer', 'Customer', $order->Name),
                ReadonlyField::create('SummaryEmail', 'Email', $order->LatestEmail),
                ReadonlyField::create('SummaryOrderStatus', 'Order status', $order->Status),
            ];
            if ($fields->dataFieldByName('Order')) {
                foreach ($summary as $field) {
                    $fields->insertBefore('Order', $field);
                }
            } else {
                $fields->addFieldsToTab('Root.Main', $summary);
            }
        }

        // The Comments has_many auto-scaffolds its own tab; DocumentCommentsFormExtension re-enables
        // that grid for adding (the rest of the read-only form stays frozen).

        // Preview tab: embed the real rendered PDF (not the HTML), so it matches the download exactly.
        $url = $this->DocumentURL();
        if ($url) {
            $fields->addFieldToTab(
                'Root.Preview',
                LiteralField::create(
                    'DocumentPreview',
                    sprintf(
                        '<div class="invoicing-preview" style="margin-top:12px">'
                        . '<iframe src="%s/pdf" title="%s" style="width:100%%;height:80vh;'
                        . 'border:1px solid rgba(0,0,0,.12);border-radius:6px;background:#fff"></iframe></div>',
                        Convert::raw2att($url),
                        Convert::raw2att((string) $this->Number)
                    )
                )
            );
        }

        // A draft is editable: keep the header/relation fields read-only (issue via the button, not by
        // hand), let staff edit only the customer note and the line quantities.
        foreach (['Status', 'Number', 'Issued', 'Total', 'Currency', 'EmailSent', 'Order', 'Payment'] as $name) {
            if ($field = $fields->dataFieldByName($name)) {
                $fields->makeFieldReadonly($field);
            }
        }
        $fields->dataFieldByName('CustomerNote')?->setDescription(
            'Optional note printed on the invoice (e.g. payment terms or a message). Editable while draft.'
        );

        // Level-2 editing: staff may change line quantities on a draft, but not add arbitrary lines.
        $linesGrid = $fields->dataFieldByName('Lines');
        if ($linesGrid instanceof GridField) {
            $linesGrid->getConfig()
                ->removeComponentsByType(GridFieldAddNewButton::class)
                ->removeComponentsByType(GridFieldAddExistingAutocompleter::class);
        }

        // Extension point: modify the final field list (after this module's own additions).
        $this->extend('updateDocumentCMSFields', $fields);

        return $fields;
    }

    /**
     * Add a "Download PDF" button to the detail-view bottom bar via
     * lekoala/silverstripe-cms-actions. Opens in a new tab.
     */
    public function getCMSActions()
    {
        $actions = parent::getCMSActions();

        // A new (unsaved) record only needs the standard Create/Save button.
        if (!$this->isInDB()) {
            return $actions;
        }

        $url = $this->DocumentURL();
        $order = $this->Order();
        $hasRecipient = $order && $order->exists() && $order->LatestEmail;

        if (!$this->isIssued()) {
            // Draft: finalise it (optionally emailing), plus a draft PDF preview.
            $actions->push(
                (new CustomAction('doIssue', _t(self::class . '.ISSUE', 'Issue invoice')))
                    ->setButtonIcon('check-mark-circle')
                    ->addExtraClass('btn-primary')
            );
            if ($hasRecipient) {
                $actions->push(
                    (new CustomAction('doIssueAndEmail', _t(self::class . '.ISSUE_EMAIL', 'Issue & email customer')))
                        ->setButtonIcon('p-mail')
                );
            }
            if ($url) {
                $actions->push(
                    (new CustomLink('previewDraftPdf', _t(self::class . '.PREVIEW_PDF', 'Preview PDF'), $url . '/pdf'))
                        ->setButtonIcon('down-circled')
                        ->setNewWindow(true)
                );
            }
        } else {
            // Issued: download the PDF and (re-)email it.
            if ($url) {
                $actions->push(
                    (new CustomLink('downloadDocumentPdf', _t(self::class . '.PDF', 'Download PDF'), $url . '/pdf'))
                        ->setButtonIcon('down-circled')
                        ->setNewWindow(true)
                        ->addExtraClass('btn-primary')
                );
            }
            if ($hasRecipient) {
                $label = $this->EmailSent
                    ? _t(self::class . '.EMAIL_RESEND', 'Re-email to customer')
                    : _t(self::class . '.EMAIL_BUTTON', 'Email to customer');
                $actions->push(
                    (new CustomAction('emailToCustomer', $label))->setButtonIcon('p-mail')
                );
            }
        }

        // Extension point: add/remove/relabel the document's action buttons.
        $this->extend('updateDocumentCMSActions', $actions);

        return $actions;
    }

    /**
     * cms-actions handler for the "Email to customer" button: sends the document (PDF attached) to the
     * order's email address, records it and returns a status message.
     */
    public function emailToCustomer($data = null, $form = null, $controller = null): string
    {
        $order = $this->Order();
        $recipient = ($order && $order->exists()) ? (string) $order->LatestEmail : '';
        if (!$recipient) {
            return _t(self::class . '.EMAIL_NONE', 'No customer email address on the order.');
        }

        if (DocumentEmailService::send($this, Security::getCurrentUser())) {
            return _t(self::class . '.EMAIL_OK', 'Emailed to {email}', ['email' => $recipient]);
        }

        return _t(self::class . '.EMAIL_FAIL', 'The email could not be sent.');
    }

    /**
     * cms-actions handler: finalise the draft (assign its number + freeze it).
     */
    public function doIssue($data = null, $form = null, $controller = null): string
    {
        if ($this->isIssued()) {
            return _t(self::class . '.ALREADY_ISSUED', 'This document is already issued.');
        }

        $this->issue();

        return _t(self::class . '.ISSUED_OK', 'Issued {number}.', ['number' => $this->Number]);
    }

    /**
     * cms-actions handler: finalise the draft and email it to the customer.
     */
    public function doIssueAndEmail($data = null, $form = null, $controller = null): string
    {
        if (!$this->isIssued()) {
            $this->issue();
        }

        $order = $this->Order();
        $recipient = ($order && $order->exists()) ? (string) $order->LatestEmail : '';
        if ($recipient && DocumentEmailService::send($this, Security::getCurrentUser())) {
            return _t(self::class . '.ISSUED_EMAILED', 'Issued {number} and emailed it to {email}.', [
                'number' => $this->Number,
                'email' => $recipient,
            ]);
        }

        return _t(self::class . '.ISSUED_NOEMAIL', 'Issued {number}, but the email could not be sent.', [
            'number' => $this->Number,
        ]);
    }
}
