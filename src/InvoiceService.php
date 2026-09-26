<?php

namespace SilverShop\Invoicing;

use SilverShop\Model\Order;
use SilverStripe\Omnipay\Model\Payment;

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
        $invoice = ShopInvoice::create();
        $invoice->OrderID = $order->ID;
        if ($payment) {
            $invoice->PaymentID = $payment->ID;
        }
        $invoice->Total = (float) $order->Total();
        $invoice->Currency = $order->Currency();
        $invoice->write();

        $sort = 0;

        foreach ($order->Items() as $item) {
            $line = ShopInvoiceLine::create();
            $line->InvoiceID = $invoice->ID;
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

            $line = ShopInvoiceLine::create();
            $line->InvoiceID = $invoice->ID;
            $line->Kind = 'Modifier';
            $line->Title = $modifier->getTableTitle();
            $line->Quantity = 1;
            $line->Amount = $amount;
            $line->OrderModifierID = $modifier->ID;
            $line->Sort = ++$sort;
            $line->write();
        }

        return $invoice;
    }
}
