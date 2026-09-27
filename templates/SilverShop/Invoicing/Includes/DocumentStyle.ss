<%-- Shared document styling for invoices and credit memos. Override at the same path to restyle both.
     Table-based, flat layout: renders identically in the browser and in dompdf (no flexbox / grid). --%>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0; background: #fff; color: #1f2430;
        font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        font-size: 13px; line-height: 1.5; font-variant-numeric: tabular-nums;
    }
    .doc { max-width: 800px; margin: 0 auto; padding: 40px; }

    table { border-collapse: collapse; }
    .num { text-align: right; white-space: nowrap; }

    /* Header: brand left, document title right */
    table.head { width: 100%; margin-bottom: 34px; }
    table.head td { vertical-align: top; }
    .brand { font-size: 20px; font-weight: 700; letter-spacing: -.01em; }
    .brand small { display: block; font-size: 12px; font-weight: 400; color: #6b7280; margin-top: 3px; }
    .brand__logo { display: block; max-height: 64px; max-width: 260px; margin-bottom: 10px; }
    .brand__addr { margin: 6px 0 0; color: #6b7280; font-size: 12px; line-height: 1.5; }
    .brand__meta { margin: 3px 0 0; color: #6b7280; font-size: 12px; }
    td.head__right { text-align: right; }
    .doc__title { font-size: 24px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #1f2430; }
    .doc__ref { margin: 6px 0 0; color: #6b7280; font-size: 12px; }
    .badge { display: inline-block; margin-top: 10px; padding: 4px 12px; border-radius: 4px; font-size: 10px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; background: #f0f1f3; color: #1f2430; border: 1px solid #d5d8de; }
    .badge--refund { background: #f0f1f3; color: #1f2430; }
    .badge--draft { background: #f0f1f3; color: #1f2430; }
    .draft-banner { margin-bottom: 18px; padding: 8px 14px; border: 1px dashed #1f2430; border-radius: 6px; background: #f0f1f3; color: #1f2430; font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .doc__note { margin-top: 22px; padding: 12px 14px; border-left: 3px solid #d5d8de; background: #f7f8fa; color: #1f2430; font-size: 12px; line-height: 1.55; }

    /* Parties */
    table.parties { width: 100%; margin-bottom: 32px; }
    table.parties td { vertical-align: top; width: 50%; padding-right: 24px; }
    .party__label { font-size: 10px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: #6b7280; margin: 0 0 6px; }
    .party__body { margin: 0; line-height: 1.6; }
    .party__note { margin: 12px 0 0; color: #6b7280; font-size: 12px; }

    /* Line items */
    table.lines { width: 100%; }
    table.lines thead th { font-size: 10px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #6b7280; text-align: left; padding: 0 0 10px; border-bottom: 2px solid #e7e9ee; }
    table.lines thead th.num { text-align: right; }
    table.lines tbody td { padding: 12px 0; border-bottom: 1px solid #e7e9ee; vertical-align: top; }
    td.desc { font-weight: 600; padding-right: 16px; }
    td.desc .sku { display: block; margin-top: 2px; font-weight: 400; font-size: 11px; color: #6b7280; }

    /* Totals: right-aligned via a two-cell wrapper (dompdf-safe) */
    table.totals-wrap { width: 100%; margin-top: 18px; }
    table.totals-wrap td.totals-spacer { width: 58%; }
    table.totals-wrap td.totals-cell { width: 42%; }
    table.totals { width: 100%; }
    table.totals td { padding: 7px 0; color: #6b7280; }
    table.totals td.num { color: #1f2430; }
    table.totals tr.grand td { border-top: 2px solid #1f2430; padding-top: 14px; font-size: 17px; font-weight: 700; color: #1f2430; }
    table.totals tr.tax-memo td { padding-top: 2px; padding-bottom: 0; font-size: 11px; color: #9098a5; }

    /* Footer */
    table.foot { width: 100%; margin-top: 40px; border-top: 1px solid #e7e9ee; }
    table.foot td { padding-top: 18px; color: #6b7280; font-size: 12px; vertical-align: top; }
    td.foot__right { text-align: right; }

    @page { margin: 18mm; }
</style>
