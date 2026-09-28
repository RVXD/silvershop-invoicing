<?php

namespace SilverShop\Invoicing;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;

/**
 * Explains the product's Tax class field in the CMS: the invoicing module reads the chosen class's rate
 * (via Product::getTaxRate()) to build the per-rate VAT breakdown shown on invoices and credit memos.
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
    }
}
