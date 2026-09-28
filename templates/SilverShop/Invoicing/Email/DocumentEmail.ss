<%-- Customer email accompanying an invoice / credit-memo PDF. Override at the same path to restyle. --%>
<div style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; color: #1f2430; line-height: 1.5;">
    <p><%t SilverShop\Invoicing\Document.EMAIL_DEAR "Dear {name}," name=$Order.Name %></p>

    <p><%t SilverShop\Invoicing\Document.EMAIL_INTRO "Please find your {label} {number} attached, for order {ref}." label=$Label number=$Document.Number ref=$Order.Reference %></p>

    <p><strong><%t SilverShop\Invoicing\Document.TOTAL "Total" %>:</strong> $Document.Total.Nice</p>

    <% if $SiteConfig.InvoiceFooterText %>
        <p style="color:#6b7280;">$SiteConfig.InvoiceFooterTextHTML</p>
    <% end_if %>

    <p style="margin-top:20px;"><%t SilverShop\Invoicing\Document.EMAIL_REGARDS "Kind regards," %><br />$SiteConfig.InvoiceSellerNameOrDefault</p>
</div>
