<?php

namespace SilverShop\Invoicing;

use SilverStripe\Core\Extension;

/**
 * Applied to {@link \SilverStripe\Omnipay\Model\Payment}. When a payment is captured, issue an
 * immutable invoice for its order (idempotent per payment).
 */
class PaymentInvoiceExtension extends Extension
{
    public function onAfterWrite(): void
    {
        $payment = $this->getOwner();

        if ($payment->Status === 'Captured' && $payment->OrderID) {
            InvoiceService::invoiceForPayment($payment);
        }
    }
}
