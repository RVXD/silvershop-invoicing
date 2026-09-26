<?php

namespace SilverShop\Invoicing;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\FieldType\DBDatetime;

/**
 * Optional: give orders a per-year, gap-free reference in the same style as invoices
 * (e.g. ORD-2026-00001), reusing {@link DocumentNumberSequence}. Hooks SilverShop's own
 * generateReference() extension point, so there is no core change and existing orders keep their
 * references.
 *
 * Opt-in — off by default. Enable with:
 *   SilverShop\Model\Order:
 *     order_numbering_enabled: true
 *
 * Note: order numbers are not legally required to be sequential (only invoices are, for VAT); this is
 * purely for visual consistency with the invoice / credit-memo numbering.
 */
class OrderNumberingExtension extends Extension
{
    /**
     * @param string $reference passed by reference from Order::generateReference()
     */
    public function generateReference(&$reference): void
    {
        $order = $this->getOwner();
        if (!$order->config()->get('order_numbering_enabled')) {
            return; // keep SilverShop's default (zero-padded ID)
        }

        $prefix = (string) $order->config()->get('order_number_prefix');
        $padding = max(1, (int) $order->config()->get('order_number_padding'));
        $yearly = $order->config()->get('order_number_series') !== 'continuous';

        $when = (string) ($order->Placed ?: DBDatetime::now()->getValue());
        $year = (int) date('Y', strtotime($when));

        $seriesKey = rtrim($prefix, '-') . ($yearly ? '-' . $year : '');
        $next = DocumentNumberSequence::reserve($seriesKey, 1);
        $sequence = str_pad((string) $next, $padding, '0', STR_PAD_LEFT);

        $reference = $yearly ? $prefix . $year . '-' . $sequence : $prefix . $sequence;
    }
}
