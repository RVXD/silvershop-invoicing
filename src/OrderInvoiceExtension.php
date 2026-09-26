<?php

namespace SilverShop\Invoicing;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;

/**
 * Applied to {@link \SilverShop\Model\Order}. Shows the order's issued invoices and credit memos in
 * read-only "Invoices" and "Credit Memos" tabs in the CMS.
 */
class OrderInvoiceExtension extends Extension
{
    public function updateCMSFields(FieldList $fields): void
    {
        $order = $this->getOwner();
        if (!$order->isInDB()) {
            return;
        }

        $fields->addFieldToTab('Root.Invoices', GridField::create(
            'ShopInvoices',
            'Invoices',
            ShopInvoice::get()->filter('OrderID', $order->ID),
            GridFieldConfig_RecordViewer::create()
        ));

        $fields->addFieldToTab('Root.CreditMemos', GridField::create(
            'ShopCreditMemos',
            'Credit Memos',
            ShopCreditMemo::get()->filter('OrderID', $order->ID),
            GridFieldConfig_RecordViewer::create()
        ));
    }
}
