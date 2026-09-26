<%-- Customer email accompanying an invoice / credit-memo PDF. Override at the same path to restyle. --%>
<div style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; color: #1f2430; line-height: 1.5;">
    <p>Dear $Order.Name,</p>

    <p>Please find your $Label <strong>$Document.Number</strong> attached, for order $Order.Reference.</p>

    <p><strong>Total:</strong> $Document.Total.Nice</p>

    <% if $SiteConfig.InvoiceFooterText %>
        <p style="color:#6b7280;">$SiteConfig.InvoiceFooterTextHTML</p>
    <% end_if %>

    <p style="margin-top:20px;">Kind regards,<br />$SiteConfig.InvoiceSellerNameOrDefault</p>
</div>
