<%-- Default invoice document. Override by placing a file at the same path in your app or theme:
     app/templates/SilverShop/Invoicing/Invoice.ss (or themes/<theme>/templates/...). --%>
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
    <div class="doc__head">
        <div class="brand">$SiteConfig.Title<small>Order $Order.Reference</small></div>
        <div class="doc__title">
            <h1>Invoice</h1>
            <p class="doc__ref">$Number &middot; $Issued.Nice</p>
            <span class="badge">Paid</span>
        </div>
    </div>

    <div class="parties">
        <div>
            <p class="party__label">Billed to</p>
            <p class="party__body"><% if $Order.BillingAddress %>$Order.BillingAddress<br /><% else %>$Order.Name<br /><% end_if %>$Order.LatestEmail</p>
        </div>
        <% if $Order.ShippingAddress %>
            <div>
                <p class="party__label">Shipped to</p>
                <p class="party__body">$Order.ShippingAddress</p>
            </div>
        <% end_if %>
    </div>

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

    <div class="totals">
        <div class="totals__row"><span>Subtotal</span><span>$Subtotal.Nice</span></div>
        <% loop $ModifierLines %>
            <div class="totals__row"><span>$Title</span><span>$Amount.Nice</span></div>
        <% end_loop %>
        <div class="totals__row grand"><span>Total</span><span>$Total.Nice</span></div>
    </div>

    <div class="doc__foot">
        <span>Thank you for your business.</span>
        <% if $Payment %><span>Paid via $Payment.Gateway</span><% end_if %>
    </div>
</div>
</body>
</html>
