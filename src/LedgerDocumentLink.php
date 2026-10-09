<?php

namespace SilverShop\Invoicing;

use SilverShop\Ledger\ShopPostingEntry;
use SilverStripe\ORM\DataObject;

/**
 * Links a financial document (invoice / credit memo) to its matching ledger posting entry, so the ledger
 * references the source document. Order-independent: called both after a document is created and when a
 * posting entry is created (whichever happens second completes the link). No-op if silvershop/ledger is
 * not installed.
 */
class LedgerDocumentLink
{
    /**
     * Document class → the ShopPostingEntry Type that represents it in the ledger.
     *
     * @var array<class-string, string>
     */
    private const ENTRY_TYPE_FOR_DOCUMENT = [
        ShopInvoice::class => 'Captured',
        ShopCreditMemo::class => 'Refunded',
    ];

    /**
     * Attach a just-created document to the matching ledger entry for its payment (if that entry exists and
     * is still unlinked).
     */
    public static function linkDocument(DataObject $document): void
    {
        if (!class_exists(ShopPostingEntry::class)) {
            return;
        }

        $type = self::ENTRY_TYPE_FOR_DOCUMENT[$document->ClassName] ?? null;
        $paymentID = (int) $document->PaymentID;
        if (!$type || !$paymentID) {
            return;
        }

        $entry = ShopPostingEntry::get()->filter(['PaymentID' => $paymentID, 'Type' => $type])->first();
        if ($entry && !$entry->DocumentID) {
            $entry->attachDocument($document);
        }
    }

    /**
     * The source document for a posting entry (by payment + type), or null. Used to link an entry to an
     * already-existing document *before* the entry is first written (so no second, immutable write is
     * needed) — see {@link PostingEntryDocumentExtension}.
     */
    public static function documentFor(ShopPostingEntry $entry): ?DataObject
    {
        if (!$entry->PaymentID) {
            return null;
        }

        $documentClass = array_search($entry->Type, self::ENTRY_TYPE_FOR_DOCUMENT, true);
        if (!$documentClass) {
            return null;
        }

        return $documentClass::get()->filter('PaymentID', $entry->PaymentID)->first();
    }
}
