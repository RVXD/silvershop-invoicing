<%-- Overrides silvershop/core's AccountPage_order.ss to add a customer-facing "Documents" section with
     invoice / credit-memo PDF downloads. Each link is gated server-side by the document's canView()
     (the order's owner). Projects/themes may override this template further. --%>
<% if $SilverShopIncludeDefaultStyles %>
<% require css("silvershop/core:client/dist/css/silvershop-base.css") %>
<% require css("silvershop/core:client/dist/css/account.css") %>
<% end_if %>

<% include SilverShop\Includes\AccountNavigation %>
<div class="silvershop-account silvershop-account--order silvershop-typography">
    <% if $Order %>
        <% with $Order %>
            <h2 class="silvershop-account__order-headline"><%t SilverShop\Model\Order.OrderHeadline "Order #{OrderNo} {OrderDate}" OrderNo=$Reference OrderDate=$Created.Nice %></h2>
        <% end_with %>
    <% end_if %>
    <% if $Message %>
        <p class="silvershop-message silvershop-message--$MessageType">$Message</p>
    <% end_if %>
    <% if $Order %>
        <% with $Order %>
            <% include SilverShop\Model\Order %>

            <% if $CustomerInvoices.exists || $CustomerCreditMemos.exists %>
                <div class="silvershop-account__documents">
                    <h3><%t SilverShop\Invoicing.DOCUMENTS "Documents" %></h3>
                    <ul>
                        <% loop $CustomerInvoices %>
                            <li><a href="{$DocumentURL}/pdf" target="_blank" rel="noopener"><%t SilverShop\Invoicing.INVOICE "Invoice" %> $Number (PDF)</a></li>
                        <% end_loop %>
                        <% loop $CustomerCreditMemos %>
                            <li><a href="{$DocumentURL}/pdf" target="_blank" rel="noopener"><%t SilverShop\Invoicing.CREDITMEMO "Credit memo" %> $Number (PDF)</a></li>
                        <% end_loop %>
                    </ul>
                </div>
            <% end_if %>
        <% end_with %>
        $ActionsForm
    <% end_if %>
</div>
