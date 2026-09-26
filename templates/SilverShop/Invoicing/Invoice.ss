<%-- Default invoice document. Override by placing a file at the same path in your app or theme:
     app/templates/SilverShop/Invoicing/Invoice.ss (or themes/<theme>/templates/...).
     Table-based layout so it renders identically in the browser and in dompdf (PDF). --%>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Invoice $Number</title>
    <% include SilverShop\Invoicing\Includes\DocumentStyle %>
</head>
<body>
<div class="doc">
    <table class="head">
        <tr>
            <td>
                <div class="brand">$SiteConfig.Title<small>Order $Order.Reference</small></div>
            </td>
            <td class="head__right">
                <div class="doc__title">Invoice</div>
                <p class="doc__ref">$Number &middot; $Issued.Nice</p>
                <span class="badge">Paid</span>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <p class="party__label">Billed to</p>
                <p class="party__body"><% if $Order.BillingAddress %>$Order.BillingAddress<br /><% else %>$Order.Name<br /><% end_if %>$Order.LatestEmail</p>
            </td>
            <td>
                <% if $Order.ShippingAddress %>
                    <p class="party__label">Shipped to</p>
                    <p class="party__body">$Order.ShippingAddress</p>
                <% end_if %>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th class="desc">Description</th>
                <th class="num">Qty</th>
                <th class="num">Unit</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            <% loop $ItemLines %>
                <tr>
                    <td class="desc">$Title</td>
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
                    <tr><td>Subtotal</td><td class="num">$Subtotal.Nice</td></tr>
                    <% loop $ModifierLines %>
                        <tr><td>$Title</td><td class="num">$Amount.Nice</td></tr>
                    <% end_loop %>
                    <tr class="grand"><td>Total</td><td class="num">$Total.Nice</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="foot">
        <tr>
            <td>Thank you for your business.</td>
            <td class="foot__right"><% if $Payment %>Paid via $Payment.Gateway<% end_if %></td>
        </tr>
    </table>
</div>
</body>
</html>
