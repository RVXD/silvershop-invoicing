<?php

namespace SilverShop\Invoicing;

use SilverShop\Model\Order;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\ORM\DB;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Builds an immutable {@link ShopCreditMemo} (with snapshot lines) from a refunded payment/order.
 * Mirrors {@link InvoiceService}; links back to the order's invoice when one exists.
 */
class CreditMemoService
{
    /**
     * Create (or return the existing) credit memo for a refunded payment. Idempotent per payment.
     */
    public static function creditMemoForPayment(Payment $payment): ?ShopCreditMemo
    {
        $order = $payment->Order();
        if (!$order || !$order->exists()) {
            return null;
        }

        if ($existing = ShopCreditMemo::get()->filter('PaymentID', $payment->ID)->first()) {
            return $existing;
        }

        return self::creditMemoForOrder($order, $payment);
    }

    public static function creditMemoForOrder(Order $order, ?Payment $payment = null, ?string $reason = null): ShopCreditMemo
    {
        $memo = null;

        DB::get_conn()->withTransaction(function () use (&$memo, $order, $payment, $reason) {
            $memo = ShopCreditMemo::create();
            $memo->OrderID = $order->ID;
            if ($payment) {
                $memo->PaymentID = $payment->ID;
            }
            $memo->Total = (float) ($payment ? $payment->getAmount() : $order->Total());
            $memo->Currency = $order->Currency();
            if ($reason) {
                $memo->Reason = $reason;
            }
            if ($invoice = ShopInvoice::get()->filter('OrderID', $order->ID)->first()) {
                $memo->InvoiceID = $invoice->ID;
            }
            // Extension point: adjust the credit memo from its order/payment before it is written & frozen.
            $memo->extend('updateDocumentFromOrder', $order, $payment);
            $memo->write();

            DocumentLineSnapshotter::snapshot($order, ShopCreditMemoLine::class, 'CreditMemoID', $memo->ID);
        });

        if ($memo && SiteConfig::current_site_config()->InvoiceEmailOnIssue) {
            DocumentEmailService::send($memo);
        }

        return $memo;
    }

    /**
     * Cancel an issued invoice by issuing a reversing credit memo — the compliant "cancellation": the
     * invoice keeps its number and the credit memo offsets it (net zero). Copies the invoice's exact
     * lines. Returns null if the invoice isn't issued or already has a credit memo. Not auto-emailed.
     */
    public static function creditMemoForInvoice(ShopInvoice $invoice, ?string $reason = null): ?ShopCreditMemo
    {
        if (!$invoice->isIssued() || ShopCreditMemo::get()->filter('InvoiceID', $invoice->ID)->exists()) {
            return null;
        }

        $memo = null;
        DB::get_conn()->withTransaction(function () use (&$memo, $invoice, $reason) {
            $memo = ShopCreditMemo::create();
            $memo->Status = 'Issued';
            $memo->OrderID = $invoice->OrderID;
            $memo->InvoiceID = $invoice->ID;
            $memo->Total = (float) $invoice->Total;
            $memo->Currency = $invoice->Currency;
            $memo->Reason = $reason ?: 'Cancellation of invoice ' . $invoice->Number;
            $memo->write();

            foreach ($invoice->Lines()->sort('Sort') as $line) {
                $copy = ShopCreditMemoLine::create();
                $copy->CreditMemoID = $memo->ID;
                $copy->Kind = $line->Kind;
                $copy->Title = $line->Title;
                $copy->SKU = $line->SKU;
                $copy->Quantity = $line->Quantity;
                $copy->UnitAmount = $line->UnitAmount;
                $copy->Amount = $line->Amount;
                $copy->OrderItemID = $line->OrderItemID;
                $copy->OrderModifierID = $line->OrderModifierID;
                $copy->Sort = $line->Sort;
                $copy->write();
            }
        });

        return $memo;
    }
}
