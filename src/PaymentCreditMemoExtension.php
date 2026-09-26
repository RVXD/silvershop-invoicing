<?php

namespace SilverShop\Invoicing;

use SilverStripe\Core\Extension;

/**
 * Applied to {@link \SilverStripe\Omnipay\Model\Payment}. When a payment is refunded, issue an
 * immutable credit memo for its order (idempotent per payment).
 */
class PaymentCreditMemoExtension extends Extension
{
    public function onAfterWrite(): void
    {
        $payment = $this->getOwner();

        if ($payment->Status === 'Refunded' && $payment->OrderID) {
            CreditMemoService::creditMemoForPayment($payment);
        }
    }
}
