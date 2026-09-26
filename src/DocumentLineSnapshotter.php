<?php

namespace SilverShop\Invoicing;

use SilverShop\Model\Order;

/**
 * Snapshots an order's item + adjustment lines into a document's line rows. Shared by invoices and
 * credit memos. Item lines come from Order->Items(); adjustment lines from Order->Modifiers() read
 * via the generic OrderModifier interface (Amount + Type + getTableTitle) — signed by Type
 * (Chargable +, Deductable −, Ignored skipped) — so any modifier module (discount / tax / shipping)
 * is captured uniformly and Σ lines == Order->Total().
 *
 * @param class-string $lineClass a ShopInvoiceLine / ShopCreditMemoLine
 * @param string $parentField the has_one field linking the line to its document (e.g. "InvoiceID")
 */
class DocumentLineSnapshotter
{
    public static function snapshot(Order $order, string $lineClass, string $parentField, int $parentId): void
    {
        $sort = 0;

        foreach ($order->Items() as $item) {
            $line = $lineClass::create();
            $line->$parentField = $parentId;
            $line->Kind = 'Item';
            $line->Title = $item->getTableTitle();
            $line->Quantity = $item->Quantity;
            $line->UnitAmount = (float) $item->UnitPrice();
            $line->Amount = (float) $item->Total();
            $line->OrderItemID = $item->ID;
            $line->Sort = ++$sort;
            $line->write();
        }

        foreach ($order->Modifiers()->sort('Sort') as $modifier) {
            if ($modifier->Type === 'Ignored') {
                continue; // e.g. the SubTotal marker — item lines already carry it
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
            $line->write();
        }
    }
}
