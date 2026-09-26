<?php

namespace SilverShop\Invoicing;

use LeKoala\CmsActions\CustomAction;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;

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

        // Partial invoicing: quantity inputs for whatever is still to invoice.
        $remaining = InvoiceService::remainingQuantities($order);
        if ($remaining) {
            $fields->addFieldToTab('Root.Invoices', LiteralField::create(
                'PartialInvoiceIntro',
                '<div class="alert alert-info">Enter quantities to invoice now, then use the '
                . '<strong>Create partial invoice</strong> button at the bottom.</div>'
            ));
            foreach ($order->Items() as $item) {
                if (!isset($remaining[$item->ID])) {
                    continue;
                }
                $fields->addFieldToTab('Root.Invoices', NumericField::create(
                    'PartialQty_' . $item->ID,
                    $item->getTableTitle() . ' — remaining ' . (float) $remaining[$item->ID]
                )->setScale(0)->setValue(0));
            }
        } else {
            $fields->addFieldToTab('Root.Invoices', LiteralField::create(
                'FullyInvoicedNotice',
                '<div class="alert alert-success">This order is fully invoiced.</div>'
            ));
        }
    }

    /**
     * cms-actions button to create a partial invoice from the entered quantities.
     */
    public function updateCMSActions(FieldList $actions): void
    {
        $order = $this->getOwner();
        if ($order->isInDB() && InvoiceService::remainingQuantities($order)) {
            $actions->push(
                (new CustomAction('doCreatePartialInvoice', 'Create partial invoice'))->setButtonIcon('addpage')
            );
        }
    }

    /**
     * Handler for the "Create partial invoice" button: reads the per-item quantity fields and issues a
     * partial invoice via {@link InvoiceService::invoiceForItems()}.
     */
    public function doCreatePartialInvoice($data = null, $form = null, $controller = null): string
    {
        $order = $this->getOwner();
        $data = (array) $data;

        $quantities = [];
        foreach (InvoiceService::remainingQuantities($order) as $itemID => $remaining) {
            $qty = (float) ($data['PartialQty_' . $itemID] ?? 0);
            if ($qty > 0) {
                $quantities[$itemID] = $qty;
            }
        }

        if (!$quantities) {
            return _t(self::class . '.PARTIAL_NONE', 'Enter at least one quantity to invoice.');
        }

        $invoice = InvoiceService::invoiceForItems($order, $quantities);
        if (!$invoice) {
            return _t(self::class . '.PARTIAL_FAIL', 'Nothing could be invoiced.');
        }

        return _t(self::class . '.PARTIAL_OK', 'Created invoice {number}.', ['number' => $invoice->Number]);
    }

    /**
     * The order's issued invoices — for storefront templates (customer account order page). Each item
     * exposes DocumentURL() (append "/pdf") and is gated by the document's canView().
     */
    public function CustomerInvoices()
    {
        return ShopInvoice::get()->filter('OrderID', $this->getOwner()->ID);
    }

    /**
     * The order's issued credit memos — for storefront templates.
     */
    public function CustomerCreditMemos()
    {
        return ShopCreditMemo::get()->filter('OrderID', $this->getOwner()->ID);
    }
}
