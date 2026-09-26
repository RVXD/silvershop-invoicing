<?php

namespace SilverShop\Invoicing;

use SilverShop\Model\Order;
use SilverStripe\Omnipay\Model\Payment;
use SilverStripe\ORM\DB;

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
            $memo->write();

            DocumentLineSnapshotter::snapshot($order, ShopCreditMemoLine::class, 'CreditMemoID', $memo->ID);
        });

        return $memo;
    }
}
