<%-- Shared document styling for invoices and credit memos. Override at the same path to restyle both. --%>
<style>
    :root {
        --ink: #1f2430; --muted: #6b7280; --line: #e7e9ee; --soft: #f5f6f8;
        --accent: #4f46e5; --ok: #059669; --refund: #b45309;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0; background: #eef0f4; color: var(--ink);
        font: 14px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        -webkit-font-smoothing: antialiased; font-variant-numeric: tabular-nums;
    }
    .doc {
        max-width: 820px; margin: 32px auto; background: #fff; padding: 56px 60px;
        border-radius: 14px; box-shadow: 0 1px 3px rgba(20, 24, 40, .08), 0 12px 32px rgba(20, 24, 40, .05);
    }
    .doc__head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 44px; }
    .brand { font-size: 20px; font-weight: 700; letter-spacing: -.01em; }
    .brand small { display: block; font-size: 13px; font-weight: 400; color: var(--muted); margin-top: 4px; }
    .doc__title { text-align: right; }
    .doc__title h1 { margin: 0; font-size: 26px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--accent); }
    .doc__ref { margin: 8px 0 0; color: var(--muted); font-size: 13px; }
    .badge { display: inline-block; margin-top: 12px; padding: 4px 13px; border-radius: 999px; font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; background: rgba(5, 150, 105, .1); color: var(--ok); }
    .badge--refund { background: rgba(180, 83, 9, .1); color: var(--refund); }
    .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 40px; }
    .party__label { font-size: 11px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); margin: 0 0 8px; }
    .party__body { margin: 0; line-height: 1.6; }
    .party__note { margin: 14px 0 0; color: var(--muted); font-size: 13px; }
    table.lines { width: 100%; border-collapse: collapse; }
    table.lines thead th { font-size: 11px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); text-align: left; padding: 0 0 12px; border-bottom: 2px solid var(--line); }
    table.lines th.num, table.lines td.num { text-align: right; white-space: nowrap; }
    table.lines tbody td { padding: 15px 0; border-bottom: 1px solid var(--line); vertical-align: top; }
    table.lines .desc { font-weight: 500; }
    .totals { width: 300px; margin: 24px 0 0 auto; }
    .totals__row { display: flex; justify-content: space-between; padding: 9px 0; color: var(--muted); }
    .totals__row span:last-child { color: var(--ink); }
    .totals__row.grand { margin-top: 6px; padding: 16px 0 0; border-top: 2px solid var(--ink); font-size: 19px; font-weight: 700; }
    .doc__foot { display: flex; justify-content: space-between; gap: 16px; margin-top: 52px; padding-top: 22px; border-top: 1px solid var(--line); color: var(--muted); font-size: 13px; }
    @media print {
        body { background: #fff; }
        .doc { margin: 0; max-width: none; border-radius: 0; box-shadow: none; padding: 0; }
        @page { margin: 18mm; }
    }
</style>
