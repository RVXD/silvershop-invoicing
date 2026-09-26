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
        DB::get_conn()->withTransaction(function () use (&$invoice, $order, $payment) {
            $invoice = ShopInvoice::create();
            $invoice->OrderID = $order->ID;
            if ($payment) {
                $invoice->PaymentID = $payment->ID;
            }
            $invoice->Total = (float) $order->Total();
            $invoice->Currency = $order->Currency();
            // Extension point: adjust the invoice from its order/payment before it is written & frozen.
            $invoice->extend('updateDocumentFromOrder', $order, $payment);
            $invoice->write();

            DocumentLineSnapshotter::snapshot($order, ShopInvoiceLine::class, 'InvoiceID', $invoice->ID);
        });

        // Optionally email the customer once the invoice is fully committed (opt-in via SiteConfig).
        if ($invoice && SiteConfig::current_site_config()->InvoiceEmailOnIssue) {
            DocumentEmailService::send($invoice);
        }

        return $invoice;
    }
}
