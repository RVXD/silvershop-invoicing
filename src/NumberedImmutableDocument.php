<?php

namespace SilverShop\Invoicing;

use LeKoala\CmsActions\CustomLink;
use SilverShop\ORM\FieldType\ShopCurrency;
use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;

/**
 * Shared behaviour for immutable, sequentially-numbered financial documents (invoice, credit memo):
 * assigns a `Number` (prefixed via the class's `number_prefix` config) and `Issued` date on create,
 * and blocks any change or deletion afterwards. The using class must declare `Number` + `Issued` in
 * `$db` and a `number_prefix` config.
 */
trait NumberedImmutableDocument
{
    protected function onBeforeWrite(): void
    {
        if (!$this->isInDB()) {
            if (!$this->Number) {
                $this->Number = $this->generateNumber();
            }
            if (!$this->Issued) {
                $this->Issued = DBDatetime::now()->getValue();
            }
        } elseif ($this->isChanged()) {
            throw new ValidationException(static::class . ' records are immutable once issued.');
        }

        parent::onBeforeWrite();
    }

    protected function generateNumber(): string
    {
        $prefix = (string) $this->config()->get('number_prefix');
        $next = (int) static::get()->count() + 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
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
     * Add a "Preview" tab that embeds the rendered document in an iframe, so the whole
     * invoice / credit memo is visible inside the CMS without leaving the record.
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $url = $this->DocumentURL();
        if ($url) {
            $fields->addFieldToTab(
                'Root.Preview',
                LiteralField::create(
                    'DocumentPreview',
                    sprintf(
                        '<div class="invoicing-preview" style="margin-top:12px">'
                        . '<iframe src="%s" title="%s" loading="lazy" style="width:100%%;'
                        . 'height:78vh;border:1px solid rgba(0,0,0,.12);border-radius:6px;'
                        . 'background:#fff"></iframe></div>',
                        Convert::raw2att($url),
                        Convert::raw2att((string) $this->Number)
                    )
                )
            );
        }

        return $fields;
    }

    /**
     * Add Print (HTML) and PDF buttons to the detail-view bottom bar via
     * lekoala/silverstripe-cms-actions. Both open in a new tab.
     */
    public function getCMSActions()
    {
        $actions = parent::getCMSActions();

        $url = $this->DocumentURL();
        if ($url) {
            $print = (new CustomLink('printDocument', _t(self::class . '.PRINT', 'Print'), $url))
                ->setButtonIcon('print')
                ->setNewWindow(true)
                ->addExtraClass('btn-outline-primary');

            $pdf = (new CustomLink('downloadDocumentPdf', _t(self::class . '.PDF', 'Download PDF'), $url . '/pdf'))
                ->setButtonIcon('down-circled')
                ->setNewWindow(true)
                ->addExtraClass('btn-primary');

            $actions->push($print);
            $actions->push($pdf);
        }

        return $actions;
    }
}
