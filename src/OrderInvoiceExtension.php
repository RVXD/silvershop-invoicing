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
            $add->setButtonName('Create invoice');
        }
        $fields->addFieldToTab(
            'Root.Invoices',
            GridField::create('Invoices', 'Invoices', $order->Invoices(), $invoicesConfig)
        );

        // Credit memos are auto-created on refund — view only.
        $fields->addFieldToTab(
            'Root.CreditMemos',
            GridField::create('CreditMemos', 'Credit Memos', $order->CreditMemos(), GridFieldConfig_RecordViewer::create())
        );
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
