<?php

namespace SilverShop\Invoicing;

use SilverShop\Model\Order;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\ORM\DB;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Builds an immutable {@link ShopInvoice} (with its snapshot lines) from an order/payment.
 *
 * Lines are module-agnostic: item lines come from Order->Items(); adjustment lines come from
 * Order->Modifiers() (discount / tax / shipping / any modifier module) via the generic OrderModifier
 * interface (Amount + Type + getTableTitle), so Σ lines == Order->Total() == the captured amount.
 */
class InvoiceService
{
    /**
     * Create (or return the existing) invoice for a captured payment. Idempotent per payment.
     */
    public static function invoiceForPayment(Payment $payment): ?ShopInvoice
    {
        $order = $payment->Order();
        if (!$order || !$order->exists()) {
            return null;
        }

        if ($existing = ShopInvoice::get()->filter('PaymentID', $payment->ID)->first()) {
            return $existing;
        }

        return self::invoiceForOrder($order, $payment);
    }

    public static function invoiceForOrder(Order $order, ?Payment $payment = null): ShopInvoice
    {
        $invoice = null;

        // One transaction: the number is reserved during write(); if the snapshot fails and rolls
        // back, the reserved number is released rather than leaving a gap in the series.
        $locale = ShopInvoice::resolveDocumentLocale($order);

        DB::get_conn()->withTransaction(function () use (&$invoice, $order, $payment, $locale) {
            $invoice = ShopInvoice::create();
            $invoice->skipLineSnapshot = true;
            $invoice->Status = 'Issued'; // auto/full invoice — finalised immediately
            $invoice->OrderID = $order->ID;
            if ($payment) {
                $invoice->PaymentID = $payment->ID;
            }
            $invoice->Total = (float) $order->Total();
            $invoice->Currency = $order->Currency();
            $invoice->Locale = $locale;
            // Extension point: adjust the invoice from its order/payment before it is written & frozen.
            $invoice->extend('updateDocumentFromOrder', $order, $payment);
            $invoice->write();

            // Snapshot line/modifier/tax titles in the document's locale (frozen with the document).
            ShopInvoice::withLocale($locale, function () use ($order, $invoice) {
                DocumentLineSnapshotter::snapshot($order, ShopInvoiceLine::class, 'InvoiceID', $invoice->ID);
            });
        });

        // Reference this document from its ledger posting entry (no-op without silvershop/ledger).
        if ($invoice) {
            LedgerDocumentLink::linkDocument($invoice);
        }

        // Optionally email the customer once the invoice is fully committed (opt-in via SiteConfig).
        if ($invoice && SiteConfig::current_site_config()->InvoiceEmailOnIssue) {
            DocumentEmailService::send($invoice);
        }

        return $invoice;
    }

    /**
     * Quantity of each order item already invoiced across all of the order's invoices.
     *
     * @return array<int,float> OrderItemID => quantity
     */
    public static function invoicedQuantities(Order $order): array
    {
        $out = [];
        foreach ($order->Items() as $item) {
            $out[$item->ID] = (float) ShopInvoiceLine::get()
                ->filter(['Invoice.OrderID' => $order->ID, 'OrderItemID' => $item->ID])
                ->sum('Quantity');
        }

        return $out;
    }

    /**
     * Remaining quantity still to invoice per order item (only items with something left).
     *
     * @return array<int,float> OrderItemID => quantity
     */
    public static function remainingQuantities(Order $order): array
    {
        $invoiced = self::invoicedQuantities($order);
        $out = [];
        foreach ($order->Items() as $item) {
            $remaining = (float) $item->Quantity - ($invoiced[$item->ID] ?? 0);
            if ($remaining > 0.00001) {
                $out[$item->ID] = $remaining;
            }
        }

        return $out;
    }

    public static function isFullyInvoiced(Order $order): bool
    {
        return count(self::remainingQuantities($order)) === 0;
    }

    /**
     * Create a partial invoice for the given quantities ([OrderItemID => qty]); each is clamped to what
     * remains. The order's modifiers (tax / shipping / discount) are added on the invoice that completes
     * the order, so Σ all invoices == Order->Total(). Returns null when nothing invoiceable was requested.
     *
     * @param array<int|string,float|int|string> $quantities
     */
    public static function invoiceForItems(
        Order $order,
        array $quantities,
        ?Payment $payment = null,
        bool $issue = false
    ): ?ShopInvoice {
        $remaining = self::remainingQuantities($order);

        $toInvoice = [];
        foreach ($quantities as $itemID => $qty) {
            $itemID = (int) $itemID;
            $qty = (float) $qty;
            if ($qty > 0 && isset($remaining[$itemID])) {
                $toInvoice[$itemID] = min($qty, $remaining[$itemID]);
            }
        }
        if (!$toInvoice) {
            return null;
        }

        // Does this invoice finish the order? (every still-remaining item fully covered here)
        $completing = true;
        foreach ($remaining as $itemID => $rem) {
            if ($rem - ($toInvoice[$itemID] ?? 0) > 0.00001) {
                $completing = false;
                break;
            }
        }

        // Total must be set before write() (documents are immutable): selected items, plus modifiers
        // only on the completing invoice.
        $total = 0.0;
        foreach ($order->Items() as $item) {
            if (isset($toInvoice[$item->ID])) {
                $total += (float) $item->UnitPrice() * $toInvoice[$item->ID];
            }
        }
        if ($completing) {
            foreach ($order->Modifiers() as $modifier) {
                if ($modifier->Type === 'Ignored') {
                    continue;
                }
                $total += $modifier->Type === 'Deductable' ? -(float) $modifier->Amount : (float) $modifier->Amount;
            }
        }

        $locale = ShopInvoice::resolveDocumentLocale($order);

        $invoice = null;
        DB::get_conn()->withTransaction(function () use (&$invoice, $order, $payment, $toInvoice, $completing, $total, $issue, $locale) {
            $invoice = ShopInvoice::create();
            $invoice->skipLineSnapshot = true;
            // Manual invoices start as an editable draft (no number yet); $issue finalises immediately.
            $invoice->Status = $issue ? 'Issued' : 'Draft';
            $invoice->OrderID = $order->ID;
            if ($payment) {
                $invoice->PaymentID = $payment->ID;
            }
            $invoice->Total = $total;
            $invoice->Currency = $order->Currency();
            $invoice->Locale = $locale;
            $invoice->extend('updateDocumentFromOrder', $order, $payment);
            $invoice->write();

            ShopInvoice::withLocale($locale, function () use ($order, $invoice, $toInvoice, $completing) {
                $sort = DocumentLineSnapshotter::snapshotItems(
                    $order,
                    ShopInvoiceLine::class,
                    'InvoiceID',
                    $invoice->ID,
                    $toInvoice
                );
                if ($completing) {
                    $sort = DocumentLineSnapshotter::snapshotModifiers($order, ShopInvoiceLine::class, 'InvoiceID', $invoice->ID, $sort);
                }
                // VAT summary on the invoiced items (after any discount lines are present).
                DocumentLineSnapshotter::snapshotTaxSummary(ShopInvoiceLine::class, 'InvoiceID', $invoice->ID, $sort, $order);
            });
        });

        // Only issued invoices are emailed (a draft is not sent until finalised).
        if ($invoice && $invoice->isIssued() && SiteConfig::current_site_config()->InvoiceEmailOnIssue) {
            DocumentEmailService::send($invoice);
        }

        return $invoice;
    }
}
