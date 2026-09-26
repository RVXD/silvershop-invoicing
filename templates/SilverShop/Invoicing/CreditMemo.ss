<%-- Default credit-memo template. Override at the same path in your app or theme. --%>
<div class="shop-creditmemo">
    <header class="shop-creditmemo__header">
        <h1 class="shop-creditmemo__title">Credit Memo $Number</h1>
        <p class="shop-creditmemo__issued">Issued $Issued.Nice</p>
    </header>

    <div class="shop-creditmemo__meta">
        <div class="shop-creditmemo__order">
            <strong>Order</strong> $Order.Reference<br />
            $Order.Name<br />
            $Order.LatestEmail
        </div>
        <% if $Invoice %><p class="shop-creditmemo__against">Against invoice $Invoice.Number</p><% end_if %>
        <% if $Reason %><p class="shop-creditmemo__reason">$Reason</p><% end_if %>
    </div>

    <table class="shop-creditmemo__lines">
        <thead>
            <tr>
                <th class="shop-creditmemo__col-desc">Description</th>
                <th class="shop-creditmemo__col-qty">Qty</th>
                <th class="shop-creditmemo__col-amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            <% loop $Lines %>
                <tr class="shop-creditmemo__line shop-creditmemo__line--{$Kind}">
                    <td class="shop-creditmemo__col-desc">$Title</td>
                    <td class="shop-creditmemo__col-qty"><% if $Kind == 'Item' %>$Quantity<% end_if %></td>
                    <td class="shop-creditmemo__col-amount">$Amount.Nice</td>
                </tr>
            <% end_loop %>
        </tbody>
        <tfoot>
            <tr class="shop-creditmemo__total">
                <th class="shop-creditmemo__col-desc" colspan="2">Refunded</th>
                <th class="shop-creditmemo__col-amount">$Total.Nice</th>
            </tr>
        </tfoot>
    </table>
</div>
