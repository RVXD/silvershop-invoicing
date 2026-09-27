<?php

namespace SilverShop\Invoicing;

use SilverShop\Model\Modifiers\OrderModifier;
use SilverShop\Model\OrderItem;
use SilverShop\ORM\FieldType\ShopCurrency;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Permission;

/**
 * One snapshot line on a {@link ShopInvoice}: either a product line (from an OrderItem) or an
 * adjustment line (from an OrderModifier — discount / tax / shipping / any modifier module). Copied
 * at invoice time so it never changes even if the order is later edited.
 *
 * @property string $Kind
 * @property string $Title
 * @property float  $Quantity
 * @property float  $Amount
 */
class ShopInvoiceLine extends DataObject
{
    private static string $table_name = 'ShopInvoiceLine';

    private static array $db = [
        'Kind' => "Enum('Item,Modifier,Tax','Item')",
        'Title' => 'Varchar(255)',
        'SKU' => 'Varchar(100)',
        'Quantity' => 'Decimal(12,2)',
        'UnitAmount' => ShopCurrency::class,
        'Amount' => ShopCurrency::class,
        'Sort' => 'Int',
    ];

    private static array $has_one = [
        'Invoice' => ShopInvoice::class,
        'OrderItem' => OrderItem::class,
        'OrderModifier' => OrderModifier::class,
    ];

    private static string $default_sort = '"Sort" ASC, "ID" ASC';

    private static array $summary_fields = [
        'Title' => 'Description',
        'Quantity' => 'Qty',
        'NiceAmount' => 'Amount',
    ];

    private static array $casting = [
        'NiceAmount' => 'HTMLText',
    ];

    /**
     * Amount via ShopCurrency::Nice(), which wraps negatives in <span class="negative">. Returned as
     * HTMLText so the CMS GridField renders the markup instead of escaping it to visible raw tags.
     */
    public function getNiceAmount(): DBHTMLText
    {
        return DBField::create_field('HTMLText', $this->dbObject('Amount')->Nice());
    }

    protected function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        // Product lines: amount follows unit × quantity (staff can adjust the quantity on a draft).
        if ($this->Kind === 'Item') {
            $this->Amount = (float) $this->UnitAmount * (float) $this->Quantity;
        }
    }

    protected function onAfterWrite(): void
    {
        parent::onAfterWrite();
        $this->syncDraftInvoiceTotal();
    }

    protected function onAfterDelete(): void
    {
        parent::onAfterDelete();
        $this->syncDraftInvoiceTotal();
    }

    /**
     * Keep a draft invoice's Total in step with its lines. Issued invoices are frozen and never touched.
     */
    private function syncDraftInvoiceTotal(): void
    {
        $invoice = $this->Invoice();
        if (!$invoice || !$invoice->exists() || $invoice->isIssued()) {
            return;
        }

        // Tax lines are informational (inclusive VAT already sits in the item prices) — never in the total.
        $total = (float) $invoice->Lines()->exclude('Kind', 'Tax')->sum('Amount');
        if (abs((float) $invoice->Total - $total) > 0.00001) {
            $invoice->Total = $total;
            $invoice->write();
        }
    }

    public function canEdit($member = null): bool
    {
        $invoice = $this->Invoice();

        return $invoice && $invoice->exists() && !$invoice->isIssued()
            && Permission::check('CMS_ACCESS', 'any', $member);
    }

    public function canDelete($member = null): bool
    {
        return $this->canEdit($member);
    }

    public function canCreate($member = null, $context = []): bool
    {
        return false; // lines are snapshotted from the order, not added by hand
    }

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['InvoiceID', 'OrderItemID', 'OrderModifierID', 'Sort']);

        // Only a product line's quantity is editable; every other value is a frozen snapshot.
        $readonly = ['Kind', 'Title', 'SKU', 'UnitAmount', 'Amount'];
        if ($this->Kind !== 'Item') {
            $readonly[] = 'Quantity';
        }
        foreach ($readonly as $name) {
            if ($field = $fields->dataFieldByName($name)) {
                $fields->makeFieldReadonly($field);
            }
        }

        return $fields;
    }
}
