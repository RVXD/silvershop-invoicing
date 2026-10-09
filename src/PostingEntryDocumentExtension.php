<?php

namespace SilverShop\Invoicing;

use SilverStripe\Core\Extension;

/**
 * Applied to {@link \SilverShop\Ledger\ShopPostingEntry}. When a posting entry is first created, link it to
 * its source document (invoice / credit memo) if one already exists for the payment — the reverse of
 * {@link InvoiceService}/{@link CreditMemoService} linking the entry after a document is created. Set in
 * onBeforeWrite so the link is part of the initial insert (the entry is append-only once persisted).
 *
 * @extends Extension<\SilverShop\Ledger\ShopPostingEntry>
 */
class PostingEntryDocumentExtension extends Extension
{
    public function onBeforeWrite(): void
    {
        $entry = $this->getOwner();
        if ($entry->isInDB() || $entry->DocumentID || !$entry->PaymentID) {
            return;
        }

        $document = LedgerDocumentLink::documentFor($entry);
        if ($document) {
            $entry->DocumentID = $document->ID;
            $entry->DocumentClass = $document->ClassName;
        }
    }
}
