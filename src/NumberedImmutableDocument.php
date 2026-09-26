<?php

namespace SilverShop\Invoicing;

use LeKoala\CmsActions\CustomAction;
use LeKoala\CmsActions\CustomLink;
use SilverShop\ORM\FieldType\ShopCurrency;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
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
        if (!$this->isInDB()) {
            // Issue date first, so the number's year series matches it.
            if (!$this->Issued) {
                $this->Issued = DBDatetime::now()->getValue();
            }
            if (!$this->Number) {
                $this->Number = $this->generateNumber();
            }
        } elseif ($this->hasImmutableChanges()) {
            throw new ValidationException(static::class . ' records are immutable once issued.');
        }

        parent::onBeforeWrite();
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
        return false;
    }

    public function canDelete($member = null): bool
    {
        return false;
    }

    public function canCreate($member = null, $context = []): bool
    {
        return false;
    }

    /**
     * Human-readable title (used in CMS breadcrumbs, dropdowns, etc.) — the document number plus the
     * customer, instead of the default "#<id>".
     */
    public function getTitle(): string
    {
        $number = (string) $this->Number;
        $order = $this->Order();
        if ($number && $order && $order->exists() && $order->Name) {
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

        $url = $this->DocumentURL();
        if ($url) {
            $actions->push(
                (new CustomLink('downloadDocumentPdf', _t(self::class . '.PDF', 'Download PDF'), $url . '/pdf'))
                    ->setButtonIcon('down-circled')
                    ->setNewWindow(true)
                    ->addExtraClass('btn-primary')
            );
        }

        $order = $this->Order();
        if ($order && $order->exists() && $order->LatestEmail) {
            $label = $this->EmailSent
                ? _t(self::class . '.EMAIL_RESEND', 'Re-email to customer')
                : _t(self::class . '.EMAIL_BUTTON', 'Email to customer');
            $actions->push(
                (new CustomAction('emailToCustomer', $label))->setButtonIcon('p-mail')
            );
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
}
