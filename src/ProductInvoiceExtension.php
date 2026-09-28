<?php

namespace SilverShop\Invoicing;

use SilverShop\Model\TaxClass;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;

/**
 * Explains the product's Tax class field in the CMS and pre-selects the default class on new products, so
 * the invoicing module can read the class's rate (via Product::getTaxRate()) to build the per-rate VAT
 * breakdown shown on invoices and credit memos.
 */
class ProductInvoiceExtension extends Extension
{
    public function updateCMSFields(FieldList $fields): void
    {
        $taxClass = $fields->dataFieldByName('TaxClassID');
        if (!$taxClass) {
            return;
        }

        $taxClass->setDescription(_t(
            self::class . '.TAX_CLASS_NOTE',
            'Sets the VAT rate applied to this product on invoices and credit memos (the invoice shows a '
            . 'VAT line per rate). Leave blank to apply no VAT.'
        ));

        // Pre-select the default tax class on a new product so staff don't have to pick one each time.
        $owner = $this->getOwner();
        if (!$owner->isInDB() && !$owner->TaxClassID) {
            $default = TaxClass::get()->filter('IsDefault', true)->first();
            if ($default && $default->exists()) {
                $taxClass->setValue($default->ID);
            }
        }
    }
}
