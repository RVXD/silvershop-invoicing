<?php

namespace SilverShop\Invoicing;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;

/**
 * Applied to {@link \SilverShop\Model\Order}. Adds "Invoices" and "Credit Memos" tabs.
 *
 * Invoices are created here with the standard Add-new flow: "Create invoice" opens a new, unsaved
 * invoice form; saving it snapshots the order's still-to-invoice lines into an editable draft (adjust
 * quantities / add a note), which is then finalised with the Issue button. Credit memos are view-only
 * (auto-created on refund).
 */
class OrderInvoiceExtension extends Extension
{
    private static array $has_many = [
        'Invoices' => ShopInvoice::class . '.Order',
        'CreditMemos' => ShopCreditMemo::class . '.Order',
    ];

    public function updateCMSFields(FieldList $fields): void
    {
        $order = $this->getOwner();
        if (!$order->isInDB()) {
            return;
        }

        // Invoices: create drafts here (Add-new), edit while draft, view once issued.
        $invoicesConfig = GridFieldConfig_RecordEditor::create();
        if ($add = $invoicesConfig->getComponentByType(GridFieldAddNewButton::class)) {
            $add->setButtonName(_t(self::class . '.CREATE_INVOICE', 'Create invoice'));
        }
        $invoicesGrid = GridField::create('Invoices', _t(self::class . '.INVOICES', 'Invoices'), $order->Invoices(), $invoicesConfig);
        $fields->addFieldToTab('Root.Invoices', $invoicesGrid);
        $fields->findOrMakeTab('Root.Invoices')->setTitle(_t(self::class . '.TAB_INVOICES', 'Invoices'));

        // Credit memos are auto-created on refund — view only.
        $creditMemosGrid = GridField::create('CreditMemos', _t(self::class . '.CREDIT_MEMOS', 'Credit Memos'), $order->CreditMemos(), GridFieldConfig_RecordViewer::create());
        $fields->addFieldToTab('Root.CreditMemos', $creditMemosGrid);
        $fields->findOrMakeTab('Root.CreditMemos')->setTitle(_t(self::class . '.TAB_CREDIT_MEMOS', 'Credit Memos'));
    }

    /**
     * The order's issued invoices — for storefront templates (customer account order page). Drafts are
     * excluded; each item exposes DocumentURL() (append "/pdf") and is gated by the document's canView().
     */
    public function CustomerInvoices()
    {
        return $this->getOwner()->Invoices()->filter('Status', 'Issued');
    }

    /**
     * The order's issued credit memos — for storefront templates.
     */
    public function CustomerCreditMemos()
    {
        return $this->getOwner()->CreditMemos()->filter('Status', 'Issued');
    }
}
