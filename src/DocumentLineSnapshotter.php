<?php

namespace SilverShop\Invoicing;

use SilverShop\Model\Modifiers\Tax\Base as TaxBase;
use SilverShop\Model\Order;

/**
 * Snapshots an order's item + adjustment lines into a document's line rows. Shared by invoices and
 * credit memos. Item lines come from Order->Items(); adjustment lines from Order->Modifiers() read
 * via the generic OrderModifier interface (Amount + Type + getTableTitle) — signed by Type
 * (Chargable +, Deductable −, Ignored skipped) — so any modifier module (discount / tax / shipping)
 * is captured uniformly.
 *
 * Split into snapshotItems() + snapshotModifiers() so partial invoices can snapshot a subset of item
 * quantities and add the modifiers only on the invoice that completes the order.
 *
 * @param class-string $lineClass a ShopInvoiceLine / ShopCreditMemoLine
 * @param string $parentField the has_one field linking the line to its document (e.g. "InvoiceID")
 */
class DocumentLineSnapshotter
{
    /**
     * Full snapshot: every item's full quantity plus all modifiers. Σ lines == Order->Total().
     */
    public static function snapshot(Order $order, string $lineClass, string $parentField, int $parentId): void
    {
        $sort = self::snapshotItems($order, $lineClass, $parentField, $parentId);
        self::snapshotModifiers($order, $lineClass, $parentField, $parentId, $sort);
    }

    /**
     * Snapshot item lines. $quantities maps OrderItemID => quantity to snapshot only those items/qtys
     * (a partial invoice); null snapshots every item's full quantity.
     *
     * @param array<int,float>|null $quantities
     * @return int the last Sort index used
     */
    public static function snapshotItems(
        Order $order,
        string $lineClass,
        string $parentField,
        int $parentId,
        ?array $quantities = null,
        int $sort = 0
    ): int {
        foreach ($order->Items() as $item) {
            if ($quantities !== null && !isset($quantities[$item->ID])) {
                continue;
            }

            $qty = $quantities !== null ? (float) $quantities[$item->ID] : (float) $item->Quantity;
            if ($qty <= 0) {
                continue;
            }

            $buyable = $item->hasMethod('Buyable') ? $item->Buyable() : null;
            $unit = (float) $item->UnitPrice();

            $line = $lineClass::create();
            $line->$parentField = $parentId;
            $line->Kind = 'Item';
            $line->Title = $item->getTableTitle();
            $line->SKU = $buyable && $buyable->InternalItemID ? (string) $buyable->InternalItemID : '';
            $line->Quantity = $qty;
            $line->UnitAmount = $unit;
            $line->Amount = $unit * $qty;
            $line->OrderItemID = $item->ID;
            $line->Sort = ++$sort;
            // Extension point: enrich the item line from its source OrderItem before it is frozen.
            $line->extend('updateSnapshotLine', $item, $order);
            $line->write();
        }

        return $sort;
    }

    /**
     * Snapshot the order's modifier lines (discount / tax / shipping / any modifier module), signed by Type.
     *
     * @return int the last Sort index used
     */
    public static function snapshotModifiers(
        Order $order,
        string $lineClass,
        string $parentField,
        int $parentId,
        int $sort = 0
    ): int {
        foreach ($order->Modifiers()->sort('Sort') as $modifier) {
            // Inclusive tax (e.g. VAT-inclusive prices) is Type 'Ignored' — it does NOT change the total,
            // but a compliant invoice must still state the tax. Capture it as an informational Tax line
            // (excluded from the document total). Exclusive tax is 'Chargable' and falls through to a
            // normal modifier line that does add to the total.
            if ($modifier instanceof TaxBase && $modifier->Type === 'Ignored') {
                $tax = (float) $modifier->Amount;
                if (abs($tax) >= 0.005) {
                    $line = $lineClass::create();
                    $line->$parentField = $parentId;
                    $line->Kind = 'Tax';
                    $line->Title = $modifier->getTableTitle();
                    $line->Quantity = 1;
                    $line->Amount = $tax;
                    $line->OrderModifierID = $modifier->ID;
                    $line->Sort = ++$sort;
                    $line->extend('updateSnapshotLine', $modifier, $order);
                    $line->write();
                }
                continue;
            }

            if ($modifier->Type === 'Ignored') {
                continue; // e.g. the SubTotal marker — item lines already carry it
            }

            // Respect each modifier's own visibility contract — a discount/tax/shipping modifier that
            // has no effect hides itself (e.g. OrderDiscountModifier::ShowInTable() = Amount > 0), just as
            // SilverShop's cart/order templates gate on <% if $ShowInTable %>. Avoids "$0.00 Discount" rows.
            if ($modifier->hasMethod('ShowInTable') && !$modifier->ShowInTable()) {
                continue;
            }

            $amount = (float) $modifier->Amount;
            if ($modifier->Type === 'Deductable') {
                $amount = -$amount;
            }

            $line = $lineClass::create();
            $line->$parentField = $parentId;
            $line->Kind = 'Modifier';
            $line->Title = $modifier->getTableTitle();
            $line->Quantity = 1;
            $line->Amount = $amount;
            $line->OrderModifierID = $modifier->ID;
            $line->Sort = ++$sort;
            // Extension point: enrich the modifier line from its source OrderModifier.
            $line->extend('updateSnapshotLine', $modifier, $order);
            $line->write();
        }

        return $sort;
    }
}
