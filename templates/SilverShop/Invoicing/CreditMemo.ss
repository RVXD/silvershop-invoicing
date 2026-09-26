<%-- Default credit-memo document. Override at the same path in your app or theme. --%>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Credit Memo $Number</title>
    <% include SilverShop\Invoicing\Includes\DocumentStyle %>
</head>
<body>
<div class="doc">
    <div class="doc__head">
        <div class="brand">$SiteConfig.Title<small>Order $Order.Reference</small></div>
        <div class="doc__title">
            <h1>Credit Memo</h1>
            <p class="doc__ref">$Number &middot; $Issued.Nice</p>
            <span class="badge badge--refund">Refunded</span>
        </div>
    </div>

    <div class="parties">
        <div>
            <p class="party__label">Credited to</p>
            <p class="party__body">$Order.Name<br />$Order.LatestEmail<% if $Order.BillingAddress %><br />$Order.BillingAddress<% end_if %></p>
        </div>
        <div>
            <% if $Invoice %><p class="party__label">Against invoice</p><p class="party__body">$Invoice.Number</p><% end_if %>
            <% if $Reason %><p class="party__note">$Reason</p><% end_if %>
        </div>
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
        <div class="totals__row grand"><span>Refunded</span><span>$Total.Nice</span></div>
    </div>

    <div class="doc__foot">
        <span>Credit memo for order $Order.Reference.</span>
        <% if $Payment %><span>Refunded via $Payment.Gateway</span><% end_if %>
    </div>
</div>
</body>
</html>
