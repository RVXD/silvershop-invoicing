<%-- Default invoice document. Override by placing a file at the same path in your app or theme:
     app/templates/SilverShop/Invoicing/Invoice.ss (or themes/<theme>/templates/...).
     Table-based layout so it renders identically in the browser and in dompdf (PDF). --%>
<!DOCTYPE html>
<html lang="$DocumentLang">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><%t SilverShop\Invoicing\Document.INVOICE_TITLE "Invoice" %> $Number</title>
    <% include SilverShop\Invoicing\Includes\DocumentStyle %>
</head>
<body>
<div class="doc">
    <% if not $IsIssued %><div class="draft-banner"><%t SilverShop\Invoicing\Document.DRAFT_BANNER "DRAFT — not yet issued. This is a preview and has no number." %></div><% end_if %>
    <table class="head">
        <tr>
            <td>
                $SiteConfig.InvoiceLogoImageTag
                <div class="brand">$SiteConfig.InvoiceSellerNameOrDefault</div>
                <% if $SiteConfig.InvoiceSellerAddressHTML %><p class="brand__addr">$SiteConfig.InvoiceSellerAddressHTML</p><% end_if %>
                <% if $SiteConfig.InvoiceVatNumberOrDefault %><p class="brand__meta"><%t SilverShop\Invoicing\Document.VAT "VAT" %> $SiteConfig.InvoiceVatNumberOrDefault</p><% end_if %>
                <% if $SiteConfig.InvoiceRegistrationNumber %><p class="brand__meta"><%t SilverShop\Invoicing\Document.REG "Reg." %> $SiteConfig.InvoiceRegistrationNumber</p><% end_if %>
            </td>
            <td class="head__right">
                <div class="doc__title"><%t SilverShop\Invoicing\Document.INVOICE_TITLE "Invoice" %></div>
                <p class="doc__ref"><% if $IsIssued %>$Number &middot; $Issued.Nice<% else %><%t SilverShop\Invoicing\Document.DRAFT "Draft" %><% end_if %></p>
                <p class="doc__ref"><%t SilverShop\Invoicing\Document.ORDER "Order" %> $Order.Reference</p>
                <% if $IsIssued %><span class="badge"><%t SilverShop\Invoicing\Document.PAID "Paid" %></span><% else %><span class="badge badge--draft"><%t SilverShop\Invoicing\Document.DRAFT "Draft" %></span><% end_if %>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <p class="party__label"><%t SilverShop\Invoicing\Document.BILLED_TO "Billed to" %></p>
                <p class="party__body"><% if $Order.BillingAddress %>$Order.BillingAddress<br /><% else %>$Order.Name<br /><% end_if %>$Order.LatestEmail</p>
            </td>
            <td>
                <% if $Order.ShippingAddress %>
                    <p class="party__label"><%t SilverShop\Invoicing\Document.SHIPPED_TO "Shipped to" %></p>
                    <p class="party__body">$Order.ShippingAddress</p>
                <% end_if %>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th class="desc"><%t SilverShop\Invoicing\Document.DESCRIPTION "Description" %></th>
                <th class="num"><%t SilverShop\Invoicing\Document.QTY "Qty" %></th>
                <th class="num"><%t SilverShop\Invoicing\Document.UNIT "Unit" %></th>
                <th class="num"><%t SilverShop\Invoicing\Document.AMOUNT "Amount" %></th>
            </tr>
        </thead>
        <tbody>
            <% loop $ItemLines %>
                <tr>
                    <td class="desc">$Title<% if $SKU %><span class="sku"><%t SilverShop\Invoicing\Document.SKU "SKU" %> $SKU</span><% end_if %></td>
                    <td class="num">$Quantity</td>
                    <td class="num">$UnitAmount.Nice</td>
                    <td class="num">$Amount.Nice</td>
                </tr>
            <% end_loop %>
        </tbody>
    </table>

    <table class="totals-wrap">
        <tr>
            <td class="totals-spacer"></td>
            <td class="totals-cell">
                <table class="totals">
                    <tr><td><%t SilverShop\Invoicing\Document.SUBTOTAL "Subtotal" %></td><td class="num">$Subtotal.Nice</td></tr>
                    <% loop $ModifierLines %>
                        <tr><td>$Title</td><td class="num">$Amount.Nice</td></tr>
                    <% end_loop %>
                    <tr class="grand"><td><%t SilverShop\Invoicing\Document.TOTAL "Total" %></td><td class="num">$Total.Nice</td></tr>
                    <% loop $TaxLines %>
                        <tr class="tax-memo"><td><%t SilverShop\Invoicing\Document.INCLUDES "Includes {title}" title=$Title %></td><td class="num">$Amount.Nice</td></tr>
                    <% end_loop %>
                </table>
            </td>
        </tr>
    </table>

    <% if $CustomerNote %><div class="doc__note">$CustomerNote</div><% end_if %>

    <table class="foot">
        <tr>
            <td><% if $SiteConfig.InvoiceFooterText %>$SiteConfig.InvoiceFooterTextHTML<% else %><%t SilverShop\Invoicing\Document.THANK_YOU "Thank you for your business." %><% end_if %></td>
            <td class="foot__right"><% if $Payment %><%t SilverShop\Invoicing\Document.PAID_VIA "Paid via {gateway}" gateway=$Payment.Gateway %><% end_if %></td>
        </tr>
    </table>
</div>
</body>
</html>
