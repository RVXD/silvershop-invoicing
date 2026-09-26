<%-- Default invoice template. Override by placing a file at the same path in your app or theme:
     app/templates/SilverShop/Invoicing/Invoice.ss (or themes/<theme>/templates/...). --%>
<div class="shop-invoice">
    <header class="shop-invoice__header">
        <h1 class="shop-invoice__title">Invoice $Number</h1>
        <p class="shop-invoice__issued">Issued $Issued.Nice</p>
    </header>

    <div class="shop-invoice__meta">
        <div class="shop-invoice__order">
            <strong>Order</strong> $Order.Reference<br />
            $Order.Name<br />
            $Order.LatestEmail
        </div>
    </div>

    <table class="shop-invoice__lines">
        <thead>
            <tr>
                <th class="shop-invoice__col-desc">Description</th>
                <th class="shop-invoice__col-qty">Qty</th>
                <th class="shop-invoice__col-amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            <% loop $Lines %>
                <tr class="shop-invoice__line shop-invoice__line--{$Kind}">
                    <td class="shop-invoice__col-desc">$Title</td>
                    <td class="shop-invoice__col-qty"><% if $Kind == 'Item' %>$Quantity<% end_if %></td>
                    <td class="shop-invoice__col-amount">$Amount.Nice</td>
                </tr>
            <% end_loop %>
        </tbody>
        <tfoot>
            <tr class="shop-invoice__total">
                <th class="shop-invoice__col-desc" colspan="2">Total</th>
                <th class="shop-invoice__col-amount">$Total.Nice</th>
            </tr>
        </tfoot>
    </table>
</div>
