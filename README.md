# SilverShop Invoicing

Immutable financial **documents** (invoices, and later credit memos) for
[SilverShop](https://github.com/silvershop/silvershop-core), with **overridable templates**.

When a payment is captured, an immutable `ShopInvoice` is issued: a numbered snapshot of the order's
item lines **and** its adjustment lines (discount / tax / shipping / any modifier module — read via the
generic `OrderModifier` interface, so no coupling to those modules). The lines reconcile to the order
total by construction.

Builds on [`silvershop/ledger`](https://github.com/silvershop/silvershop-ledger) (the append-only posting
trail); this module adds the line-item documents on top.

## Overriding the invoice template

The module ships `templates/SilverShop/Invoicing/Invoice.ss`. Override it by placing a file at the **same
path** in your project or theme — no code change:

```
app/templates/SilverShop/Invoicing/Invoice.ss
themes/<your-theme>/templates/SilverShop/Invoicing/Invoice.ss
```

`$Invoice->forTemplate()` renders through the SilverStripe theme cascade, so the most specific template
wins. (PDF output — rendering this same HTML to PDF — is a planned follow-up.)

## What it adds

- **`ShopInvoice`** / **`ShopInvoiceLine`** — immutable, numbered (INV-00001), item + modifier snapshot.
- **`PaymentInvoiceExtension`** — issues an invoice on payment capture (idempotent per payment).
- **`OrderInvoiceExtension`** — a read-only **Invoices** tab on the order.
- **`InvoiceAdmin`** (`/admin/invoices`) — global, filterable, Order/Customer columns, CSV export, no import.

## Ledger integration

When [`silvershop/ledger`](https://github.com/silvershop/silvershop-ledger) is installed, every invoice
and credit memo is **linked to its matching posting entry** (by payment), so the ledger references the
source document and its CMS **Document** column shows the invoice number. The link is established
whichever side is created second — the document after the entry (via `InvoiceService` / `CreditMemoService`)
or the entry after the document (via `PostingEntryDocumentExtension`) — and is wired automatically under a
`moduleexists: silvershop/ledger` config guard. Without the ledger module everything is a silent no-op, so
invoicing works standalone.

## Licence

BSD-3-Clause.
