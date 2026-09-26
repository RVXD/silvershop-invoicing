<?php

namespace SilverShop\Invoicing;

use LeKoala\CmsActions\CustomAction;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\HeaderField;
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
            $fields->addFieldToTab('Root.Invoices', HeaderField::create('CreateInvoiceHeader', 'Create an invoice', 3));

            foreach ($order->Items() as $item) {
                if (!isset($remaining[$item->ID])) {
                    continue;
                }
                $fields->addFieldToTab('Root.Invoices', NumericField::create('PartialQty_' . $item->ID, $item->getTableTitle())
                    ->setScale(0)
                    ->setValue(0)
                    ->setDescription('Remaining to invoice: ' . (float) $remaining[$item->ID]));
            }

            $fields->addFieldToTab('Root.Invoices', LiteralField::create('PartialInvoiceInfo', $this->infoNotice(
                '<strong>Partial invoicing</strong> lets you issue an invoice for only part of this order — for '
                . 'example when you ship some items now and the rest later. Enter how many of each remaining item to '
                . 'invoice, then click <strong>Create partial invoice</strong> below. The remaining quantities stay '
                . 'available to invoice later, and any tax or shipping is added to the invoice that completes the order. '
                . 'Leave every quantity at 0 to invoice nothing.'
            )));
        } else {
            $fields->addFieldToTab('Root.Invoices', LiteralField::create('FullyInvoicedNotice', $this->infoNotice(
                'This order is <strong>fully invoiced</strong> — every ordered item has been invoiced, so there is '
                . 'nothing left to invoice.'
            )));
        }
    }

    /**
     * Core-style admin info notice (info-circle icon + spacing), matching SilverShop's other notices.
     */
    private function infoNotice(string $text): string
    {
        return '<p class="message notice" style="margin-top:1.5em;display:flex;align-items:flex-start;gap:.5em">'
            . '<span class="font-icon-info-circled" aria-hidden="true"></span><span>' . $text . '</span></p>';
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
