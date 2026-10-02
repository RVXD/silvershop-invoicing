<?php

namespace SilverShop\Invoicing;

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
        $sort = self::snapshotModifiers($order, $lineClass, $parentField, $parentId, $sort);
        self::snapshotTaxSummary($lineClass, $parentField, $parentId, $sort);
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
            // Per-product VAT rate for the tax summary: the buyable may expose getTaxRate() (e.g. 0.09
            // food / 0.21 goods). hasMethod() so an extension-provided rate is honoured. 0 = untaxed.
            $line->TaxRate = $buyable && $buyable->hasMethod('getTaxRate') ? (float) $buyable->getTaxRate() : 0.0;
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
            if ($modifier->Type === 'Ignored') {
                continue; // e.g. the SubTotal marker (item lines carry it) or inclusive tax (see below)
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

    /**
     * VAT summary: one informational Tax line per rate, computed from the document's own item lines
     * (each carrying its per-product TaxRate). Prices are treated as VAT-inclusive, so the tax is
     * extracted (base − base/(1+rate)) and the Tax lines never change the total. An order-level discount
     * reduces the taxable base, apportioned across items in proportion to value — matching how
     * SilverShop's FlatTax handles per-item rates. No item rate → no tax lines.
     *
     * Reads the lines already written for this document, so it must run after snapshotItems() (and
     * snapshotModifiers(), so discounts are present).
     *
     * @param class-string $lineClass
     * @return int the last Sort index used
     */
    public static function snapshotTaxSummary(string $lineClass, string $parentField, int $parentId, int $sort = 0): int
    {
        $lines = $lineClass::get()->filter($parentField, $parentId);
        $itemLines = $lines->filter('Kind', 'Item');
        $itemGross = (float) $itemLines->sum('Amount');
        if ($itemGross <= 0) {
            return $sort;
        }

        // Order-level discount = the negative (Deductable) modifier lines.
        $discount = 0.0;
        foreach ($lines->filter('Kind', 'Modifier') as $modifierLine) {
            $amount = (float) $modifierLine->Amount;
            if ($amount < 0) {
                $discount += -$amount;
            }
        }

        // Discounted taxable base grouped by rate.
        $baseByRate = [];
        foreach ($itemLines as $itemLine) {
            $rate = (float) $itemLine->TaxRate;
            if ($rate <= 0) {
                continue;
            }
            $gross = (float) $itemLine->Amount;
            $taxable = $gross - ($gross / $itemGross) * $discount;
            $key = number_format($rate, 4, '.', '');
            $baseByRate[$key] = ($baseByRate[$key] ?? 0.0) + $taxable;
        }

        // Taxed modifier lines (e.g. shipping given a rate by silvershop/vat-compliance) join the base at their own
        // rate. Order discounts apply to items, so shipping is added at its full amount (not re-apportioned here).
        foreach ($lines->filter('Kind', 'Modifier') as $modifierLine) {
            $rate = (float) $modifierLine->TaxRate;
            $gross = (float) $modifierLine->Amount;
            if ($rate <= 0 || $gross <= 0) {
                continue;
            }
            $key = number_format($rate, 4, '.', '');
            $baseByRate[$key] = ($baseByRate[$key] ?? 0.0) + $gross;
        }
        ksort($baseByRate, SORT_NUMERIC);

        foreach ($baseByRate as $key => $base) {
            $rate = (float) $key;
            $vat = $base - round($base / (1 + $rate), 2);
            if (abs($vat) < 0.005) {
                continue;
            }
            $pct = rtrim(rtrim(number_format($rate * 100, 2, '.', ''), '0'), '.');

            $line = $lineClass::create();
            $line->$parentField = $parentId;
            $line->Kind = 'Tax';
            $line->TaxRate = $rate;
            $line->Title = _t('SilverShop\\Invoicing\\Tax.LINE', 'VAT {pct}%', ['pct' => $pct]);
            $line->Quantity = 1;
            $line->Amount = $vat;
            $line->Sort = ++$sort;
            $line->write();
        }

        return $sort;
    }
}
