<?php
$e = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$ctCustomerLink = static fn(mixed $id, mixed $name): string => '<a class="ct-record-link" href="?business=chemtech&amp;page=customers&amp;customer=' . rawurlencode((string) $id) . '" data-ct-customer="' . $e($id) . '">' . $e($name) . '</a>';
$ctInvoiceLink = static fn(mixed $id, string $label = 'View invoice'): string => '<a class="ct-table-action" href="?business=chemtech&amp;page=invoices&amp;invoice=' . rawurlencode((string) $id) . '" data-ct-invoice="' . $e($id) . '">' . $e($label) . '</a>';
$ctPageMeta = [
    'dashboard'=>['Dashboard','Sales, billing, collections and stock at a glance.'],
    'enquiries'=>['Enquiries','Capture enquiries and convert them into business.'],
    'customers'=>['Customers','Manage GST profiles, contacts and billing cycles.'],
    'quotations'=>['Quotations','Prepare, share and follow commercial quotations.'],
    'orders'=>['Orders','Manage confirmed sales and reserved stock.'],
    'invoices'=>['Invoices','Generate, open and print GST invoices from sales orders.'],
    'payments'=>['Payments','Track collections and outstanding balances.'],
    'products'=>['Products','Manage the chemical catalogue, tax and pricing.'],
    'inventory'=>['Inventory','Monitor stock and reorder requirements.'],
    'purchases'=>['Purchase','Manage suppliers, purchase orders and receipts.'],
    'dispatch'=>['Dispatch','Track delivery notes, transport and proof of delivery.'],
    'reports'=>['Reports','Review sales, tax, stock and receivables.'],
    'documents'=>['Documents','Keep commercial and compliance files organised.'],
    'settings'=>['Settings','Company, GST, bank and invoice configuration.'],
];
[$ctTitle,$ctSubtitle] = $ctPageMeta[$chemtechPage];
$ctNavGroups = [
    'Overview'=>['dashboard'=>'Dashboard'],
    'Sales'=>['enquiries'=>'Enquiries','customers'=>'Customers','orders'=>'Orders'],
    'Catalogue'=>['products'=>'Products','inventory'=>'Inventory'],
    'Money'=>['invoices'=>'Invoices','payments'=>'Payments'],
    'Admin'=>['settings'=>'Settings'],
];
$ctSales = array_sum(array_map(static fn(array $invoice): int => (int) ($invoice['total_paise'] ?? 0), $chemtechInvoices));
$ctCollected = array_sum(array_map(static fn(array $invoice): int => (int) ($invoice['paid_paise'] ?? 0), $chemtechInvoices));
$ctOutstanding = max(0, $ctSales - $ctCollected);
$ctUnbilled = array_values(array_filter($chemtechOrders, static fn(array $order): bool => ($order['billing_status'] ?? 'unbilled') === 'unbilled'));
$ctUnbilledValue = array_sum(array_map(static fn(array $order): int => (int) ($order['total_paise'] ?? 0), $ctUnbilled));
$ctLowStock = array_values(array_filter($chemtechProducts, static fn(array $product): bool => (int) ($product['stock_milli'] ?? 0) <= (int) ($product['reorder_milli'] ?? 0)));
$ctOpenInvoices = array_values(array_filter($chemtechInvoices, static fn(array $invoice): bool => (int) ($invoice['paid_paise'] ?? 0) < (int) ($invoice['total_paise'] ?? 0)));
$ctOverdue = array_values(array_filter($ctOpenInvoices, static fn(array $invoice): bool => (string) ($invoice['due_date'] ?? '') < date('Y-m-d')));
$ctRequestedInvoiceId = trim((string) ($_GET['invoice'] ?? ''));
$ctSelectedInvoice = $ctRequestedInvoiceId !== '' ? chemtechFind($chemtechInvoices, $ctRequestedInvoiceId) : null;
$ctSelectedCustomer = $ctSelectedInvoice ? chemtechFind($chemtechCustomers, (string) ($ctSelectedInvoice['customer_id'] ?? '')) : null;
$ctSelectedOrders = $ctSelectedInvoice ? array_values(array_filter($chemtechOrders, static fn(array $order): bool => in_array((string) ($order['id'] ?? ''), (array) ($ctSelectedInvoice['order_ids'] ?? []), true))) : [];
$ctCustomerProfileId = trim((string) ($_GET['customer'] ?? ''));
$ctCustomerProfile = $ctCustomerProfileId !== '' ? chemtechFind($chemtechCustomers, $ctCustomerProfileId) : null;
$ctCustomerOrders = $ctCustomerProfile ? array_values(array_filter($chemtechOrders, static fn(array $order): bool => (string) ($order['customer_id'] ?? '') === $ctCustomerProfileId)) : [];
$ctCustomerInvoices = $ctCustomerProfile ? array_values(array_filter($chemtechInvoices, static fn(array $invoice): bool => (string) ($invoice['customer_id'] ?? '') === $ctCustomerProfileId)) : [];
$ctCustomerInvoiceIds = array_map(static fn(array $invoice): string => (string) ($invoice['id'] ?? ''), $ctCustomerInvoices);
$ctCustomerPayments = $ctCustomerProfile ? array_values(array_filter($chemtechPayments, static fn(array $payment): bool => in_array((string) ($payment['invoice_id'] ?? ''), $ctCustomerInvoiceIds, true))) : [];
$ctCustomerEnquiries = $ctCustomerProfile ? array_values(array_filter($chemtechEnquiries, static fn(array $enquiry): bool => (string) ($enquiry['customer_id'] ?? '') === $ctCustomerProfileId)) : [];
$ctAccent = '#faa61d';
$ctAutoOpenCustomerId = trim((string) ($_GET['open_customer'] ?? ''));
$ctAutoOpenInvoiceId = trim((string) ($_GET['open_invoice'] ?? ''));
$ctCustomerRecords = [];
foreach ($chemtechCustomers as $customer) {
    $customerId = (string) ($customer['id'] ?? '');
    $customerOrders = array_values(array_filter($chemtechOrders, static fn(array $order): bool => (string) ($order['customer_id'] ?? '') === $customerId));
    $customerInvoices = array_values(array_filter($chemtechInvoices, static fn(array $invoice): bool => (string) ($invoice['customer_id'] ?? '') === $customerId));
    $customerInvoiced = array_sum(array_map(static fn(array $invoice): int => (int) ($invoice['total_paise'] ?? 0), $customerInvoices));
    $customerReceived = array_sum(array_map(static fn(array $invoice): int => (int) ($invoice['paid_paise'] ?? 0), $customerInvoices));
    $ctCustomerRecords[$customerId] = array_replace($customer, [
        'order_count'=>count($customerOrders),
        'invoice_count'=>count($customerInvoices),
        'invoiced_paise'=>$customerInvoiced,
        'received_paise'=>$customerReceived,
        'outstanding_paise'=>max(0, $customerInvoiced - $customerReceived),
    ]);
}
$ctInvoiceRecords = [];
foreach ($chemtechInvoices as $invoice) {
    $invoiceId = (string) ($invoice['id'] ?? '');
    $invoiceCustomer = chemtechFind($chemtechCustomers, (string) ($invoice['customer_id'] ?? ''));
    $invoiceOrders = array_values(array_filter($chemtechOrders, static fn(array $order): bool => in_array((string) ($order['id'] ?? ''), (array) ($invoice['order_ids'] ?? []), true)));
    $invoiceBalance = max(0, (int) ($invoice['total_paise'] ?? 0) - (int) ($invoice['paid_paise'] ?? 0));
    $ctInvoiceRecords[$invoiceId] = [
        'filename'=>'Invoice-'.preg_replace('/[^A-Za-z0-9_-]+/','-',(string) ($invoice['invoice_number'] ?? 'invoice')).'.pdf',
        'id'=>$invoiceId,
        'customer_id'=>(string) ($invoice['customer_id'] ?? ''),
        'company'=>[
            'name'=>(string) ($invoice['company_name'] ?? ($chemtechSettings['legal_name'] ?: $chemtechSettings['company_name'])),
            'address'=>(string) ($invoice['company_address'] ?? $chemtechSettings['address']),
            'gstin'=>(string) ($invoice['company_gstin'] ?? $chemtechSettings['gstin']),
            'phone'=>(string) ($chemtechSettings['phone'] ?? ''),
            'email'=>(string) ($chemtechSettings['email'] ?? ''),
        ],
        'customer'=>[
            'name'=>(string) ($invoice['customer_name'] ?? ($invoiceCustomer['name'] ?? '')),
            'address'=>(string) ($invoice['customer_address'] ?? ($invoiceCustomer['address'] ?? '')),
            'gstin'=>(string) ($invoice['customer_gstin'] ?? ($invoiceCustomer['gstin'] ?? '')),
        ],
        'invoice'=>[
            'number'=>(string) ($invoice['invoice_number'] ?? ''),
            'date'=>date('d M Y', strtotime((string) ($invoice['invoice_date'] ?? 'now'))),
            'due_date'=>date('d M Y', strtotime((string) ($invoice['due_date'] ?? 'now'))),
            'status'=>ucwords(str_replace('_',' ',(string) ($invoice['status'] ?? 'unpaid'))),
        ],
        'bank'=>[
            'name'=>(string) ($chemtechSettings['bank_name'] ?? ''),
            'account'=>(string) ($chemtechSettings['account_number'] ?? ''),
            'ifsc'=>(string) ($chemtechSettings['ifsc'] ?? ''),
        ],
        'lines'=>array_map(static fn(array $order): array => [
            'description'=>$order['product_name'] ?? '', 'order_number'=>$order['order_number'] ?? '', 'hsn'=>$order['hsn'] ?? '',
            'quantity'=>(int) ($order['quantity_milli'] ?? 0) / 1000, 'unit'=>$order['unit'] ?? '', 'rate_paise'=>(int) ($order['unit_price_paise'] ?? 0),
            'subtotal_paise'=>(int) ($order['subtotal_paise'] ?? 0), 'tax_paise'=>(int) ($order['cgst_paise'] ?? 0)+(int) ($order['sgst_paise'] ?? 0)+(int) ($order['igst_paise'] ?? 0),
            'tax_rate_bps'=>(int) ($order['gst_rate_bps'] ?? 0), 'total_paise'=>(int) ($order['total_paise'] ?? 0),
        ], $invoiceOrders),
        'totals'=>[
            'subtotal_paise'=>(int) ($invoice['subtotal_paise'] ?? 0), 'cgst_paise'=>(int) ($invoice['cgst_paise'] ?? 0),
            'sgst_paise'=>(int) ($invoice['sgst_paise'] ?? 0), 'igst_paise'=>(int) ($invoice['igst_paise'] ?? 0),
            'total_paise'=>(int) ($invoice['total_paise'] ?? 0), 'paid_paise'=>(int) ($invoice['paid_paise'] ?? 0), 'balance_paise'=>$invoiceBalance,
        ],
    ];
}
?>
<div class="ct-workspace">
    <aside class="ct-sidebar" data-ct-sidebar>
        <div class="ct-brand">
            <span><?= $e($chemtechSettings['short_name']) ?></span>
            <strong><?= $e($chemtechSettings['company_name']) ?></strong>
            <button type="button" data-ct-sidebar-toggle aria-label="Collapse navigation">‹</button>
        </div>
        <nav aria-label="ChemTech navigation">
            <?php foreach($ctNavGroups as $group=>$items): ?>
                <section><small><?= $e($group) ?></small><?php foreach($items as $page=>$label): ?><a class="<?= $chemtechPage===$page?'active':'' ?>" href="?business=chemtech&amp;page=<?= $e($page) ?>"><b><?= $e($label) ?></b></a><?php endforeach; ?></section>
            <?php endforeach; ?>
        </nav>
        <a class="ct-back" href="./"><span>←</span><b>All businesses</b></a>
    </aside>

    <main class="ct-main">
        <header class="ct-topbar">
            <div class="ct-heading"><button class="ct-mobile-menu" type="button" data-ct-sidebar-toggle aria-label="Open navigation">Menu</button><span class="ct-breadcrumb"><?= $e($chemtechSettings['company_name']) ?> / <?= $e($ctTitle) ?></span><h1><?= $e($ctTitle) ?></h1><p><?= $e($ctSubtitle) ?></p></div>
            <div class="ct-top-actions"><button class="ct-theme" data-theme-toggle type="button" aria-label="Switch theme"><span>☼</span></button><?php if($chemtechPage==='dashboard'): ?><a class="ct-button primary" href="?business=chemtech&amp;page=orders">Create order</a><?php endif; ?></div>
        </header>
        <?php if($notice): ?><div class="ct-notice" role="status"><?= $e($notice) ?></div><?php endif; ?>

        <?php if($chemtechPage==='dashboard'): ?>
            <section class="ct-card ct-workflow-guide" aria-label="How ChemTech CRM works">
                <header><div><span>START HERE</span><h2>One simple sales workflow</h2></div><p>Complete these steps from left to right.</p></header>
                <div>
                    <a href="?business=chemtech&amp;page=customers"><b>1</b><span><strong>Add customer</strong><small>Company, GST and credit details</small></span></a>
                    <a href="?business=chemtech&amp;page=products"><b>2</b><span><strong>Add product</strong><small>Chemical, price, GST and stock</small></span></a>
                    <a href="?business=chemtech&amp;page=orders"><b>3</b><span><strong>Create order</strong><small>Select customer and product</small></span></a>
                    <a href="?business=chemtech&amp;page=invoices"><b>4</b><span><strong>Generate invoice</strong><small>Choose an unbilled order</small></span></a>
                    <a href="?business=chemtech&amp;page=payments"><b>5</b><span><strong>Record payment</strong><small>Outstanding updates automatically</small></span></a>
                </div>
            </section>
            <section class="ct-summary" aria-label="Business overview">
                <a href="?business=chemtech&amp;page=invoices"><span>Invoiced sales</span><strong><?= chemtechMoney($ctSales) ?></strong><small><?= count($chemtechInvoices) ?> invoices</small></a>
                <a href="?business=chemtech&amp;page=payments"><span>Outstanding</span><strong><?= chemtechMoney($ctOutstanding) ?></strong><small><?= count($ctOpenInvoices) ?> open invoices</small></a>
                <a href="?business=chemtech&amp;page=invoices"><span>Unbilled orders</span><strong><?= chemtechMoney($ctUnbilledValue) ?></strong><small><?= count($ctUnbilled) ?> ready for billing</small></a>
                <a href="?business=chemtech&amp;page=inventory"><span>Low stock</span><strong><?= count($ctLowStock) ?></strong><small><?= count($chemtechProducts) ?> products tracked</small></a>
            </section>
            <section class="ct-dashboard-grid">
                <article class="ct-card ct-index">
                    <header class="ct-section-head"><div><h2>Recent orders</h2><p>Latest confirmed sales orders</p></div><a href="?business=chemtech&amp;page=orders">View all</a></header>
                    <?php if(!$chemtechOrders): ?><div class="ct-empty"><b>No orders yet</b><span>Create an order when you are ready to begin.</span><a class="ct-button" href="?business=chemtech&amp;page=orders">Open orders</a></div><?php else: ?><div class="ct-table-wrap"><table class="ct-table"><thead><tr><th>Order</th><th>Customer</th><th>Product</th><th>Total</th><th>Billing</th></tr></thead><tbody><?php foreach(array_slice($chemtechOrders,0,8) as $order): ?><tr><td><b><?= $e($order['order_number']) ?></b><small><?= $e(date('d M Y',strtotime((string)$order['created_at']))) ?></small></td><td><?= $ctCustomerLink($order['customer_id'],$order['customer_name']) ?></td><td><?= $e($order['product_name']) ?></td><td><?= chemtechMoney((int)$order['total_paise']) ?></td><td><?php if(($order['billing_status']??'unbilled')==='billed'&&!empty($order['invoice_id'])): ?><?= $ctInvoiceLink($order['invoice_id']) ?><?php else: ?><span class="ct-status <?= $e($order['billing_status']) ?>"><?= $e(ucfirst($order['billing_status'])) ?></span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                </article>
                <aside class="ct-stack">
                    <article class="ct-card ct-attention"><header class="ct-section-head"><div><h2>Needs attention</h2><p>Items requiring action</p></div></header><a href="?business=chemtech&amp;page=invoices"><span>Unbilled orders<small>Ready to invoice</small></span><strong><?= count($ctUnbilled) ?></strong></a><a href="?business=chemtech&amp;page=payments"><span>Overdue invoices<small>Past payment date</small></span><strong><?= count($ctOverdue) ?></strong></a><a href="?business=chemtech&amp;page=inventory"><span>Low-stock products<small>At or below reorder level</small></span><strong><?= count($ctLowStock) ?></strong></a></article>
                    <article class="ct-card ct-shortcuts"><header class="ct-section-head"><div><h2>Quick access</h2></div></header><nav><a href="?business=chemtech&amp;page=customers">Customers <span>→</span></a><a href="?business=chemtech&amp;page=orders">Sales orders <span>→</span></a><a href="?business=chemtech&amp;page=invoices">Invoices <span>→</span></a><a href="?business=chemtech&amp;page=payments">Payments <span>→</span></a></nav></article>
                </aside>
            </section>

        <?php elseif($chemtechPage==='enquiries'): ?>
            <section class="ct-summary ct-summary-three"><div><span>Total enquiries</span><strong><?= count($chemtechEnquiries) ?></strong><small>All sources</small></div><div><span>Converted</span><strong><?= count(array_filter($chemtechEnquiries,static fn(array $item):bool=>($item['status']??'')==='converted')) ?></strong><small>Linked customers</small></div><div><span>Follow-ups</span><strong><?= count(array_filter($chemtechEnquiries,static fn(array $item):bool=>in_array(($item['status']??''),['new','contacted','follow_up'],true))) ?></strong><small>Open opportunities</small></div></section>
            <section class="ct-card ct-index"><header class="ct-index-toolbar"><div><h2>All enquiries</h2><span><?= count($chemtechEnquiries) ?> connected leads</span></div><div class="ct-toolbar-actions"><label class="ct-search"><span>Search</span><input type="search" placeholder="Search enquiries" data-ct-table-filter="ct-enquiry-table"></label></div></header><div class="ct-table-wrap"><table class="ct-table" id="ct-enquiry-table" data-ct-paginated><thead><tr><th>Enquiry</th><th>Customer</th><th>Requirement</th><th>Source</th><th>Follow-up</th><th>Status</th></tr></thead><tbody><?php foreach($chemtechEnquiries as $enquiry): ?><tr><td><b><?= $e($enquiry['enquiry_number']) ?></b><small><?= $e(date('d M Y',strtotime((string)$enquiry['created_at']))) ?></small></td><td><?= $ctCustomerLink($enquiry['customer_id'],$enquiry['customer_name']) ?></td><td><?= $e($enquiry['product_name']) ?><small><?= number_format($enquiry['quantity_milli']/1000,3) ?> units</small></td><td><?= $e($enquiry['source']) ?></td><td><?= $e($enquiry['follow_up_date']) ?></td><td><span class="ct-status <?= $e($enquiry['status']) ?>"><?= $e(ucwords(str_replace('_',' ',$enquiry['status']))) ?></span></td></tr><?php endforeach; ?></tbody></table></div></section>

        <?php elseif($chemtechPage==='customers'): ?>
            <?php if($ctCustomerProfile): ?>
                <?php
                $ctCustomerInvoiced = array_sum(array_map(static fn(array $invoice): int => (int) ($invoice['total_paise'] ?? 0), $ctCustomerInvoices));
                $ctCustomerReceived = array_sum(array_map(static fn(array $invoice): int => (int) ($invoice['paid_paise'] ?? 0), $ctCustomerInvoices));
                $ctCustomerBalance = max(0, $ctCustomerInvoiced - $ctCustomerReceived);
                $ctCustomerPhoneDigits = preg_replace('/\D+/', '', (string) ($ctCustomerProfile['phone'] ?? '')) ?? '';
                ?>
                <div class="ct-customer-actions"><a class="ct-button" href="?business=chemtech&amp;page=customers">← All customers</a><button class="ct-button primary" type="button" data-dialog-open="ct-edit-customer-dialog">Edit customer</button></div>
                <section class="ct-card ct-customer-hero"><div class="ct-customer-avatar"><?= $e(strtoupper(substr((string)$ctCustomerProfile['name'],0,2))) ?></div><div><span>CUSTOMER PROFILE</span><h2><?= $e($ctCustomerProfile['name']) ?></h2><p><?= $e($ctCustomerProfile['contact_person'] ?: 'No contact person') ?> · <?= $e($ctCustomerProfile['state'] ?: 'State not set') ?></p></div><nav><?php if(!empty($ctCustomerProfile['phone'])): ?><a class="ct-button" href="tel:<?= $e($ctCustomerProfile['phone']) ?>">Call</a><?php endif; ?><?php if($ctCustomerPhoneDigits): ?><a class="ct-button" href="https://wa.me/<?= $e($ctCustomerPhoneDigits) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?><?php if(!empty($ctCustomerProfile['email'])): ?><a class="ct-button" href="mailto:<?= $e($ctCustomerProfile['email']) ?>">Email</a><?php endif; ?></nav></section>
                <section class="ct-summary"><div><span>Sales orders</span><strong><?= count($ctCustomerOrders) ?></strong><small>Connected orders</small></div><div><span>Invoiced</span><strong><?= chemtechMoney($ctCustomerInvoiced) ?></strong><small><?= count($ctCustomerInvoices) ?> invoices</small></div><div><span>Received</span><strong><?= chemtechMoney($ctCustomerReceived) ?></strong><small><?= count($ctCustomerPayments) ?> payments</small></div><div><span>Outstanding</span><strong><?= chemtechMoney($ctCustomerBalance) ?></strong><small><?= count($ctCustomerEnquiries) ?> enquiries</small></div></section>
                <section class="ct-customer-grid">
                    <article class="ct-card ct-customer-details"><header class="ct-section-head"><div><h2>Customer details</h2><p>Contact and tax profile</p></div></header><dl><dt>Contact person</dt><dd><?= $e($ctCustomerProfile['contact_person'] ?: '—') ?></dd><dt>Phone</dt><dd><?= $e($ctCustomerProfile['phone'] ?: '—') ?></dd><dt>Email</dt><dd><?= $e($ctCustomerProfile['email'] ?: '—') ?></dd><dt>GSTIN</dt><dd><?= $e($ctCustomerProfile['gstin'] ?: 'Unregistered') ?></dd><dt>State</dt><dd><?= $e(($ctCustomerProfile['state'] ?: '—').' · code '.($ctCustomerProfile['state_code'] ?: '—')) ?></dd><dt>Billing cycle</dt><dd><?= $e(ucwords(str_replace('_',' ',(string)$ctCustomerProfile['billing_cycle']))) ?></dd><dt>Credit period</dt><dd><?= (int)$ctCustomerProfile['credit_days'] ?> days</dd><dt>Billing address</dt><dd><?= nl2br($e($ctCustomerProfile['address'] ?: '—')) ?></dd></dl></article>
                    <article class="ct-card ct-index"><header class="ct-section-head"><div><h2>Recent orders</h2><p>Create or open invoices directly from each order</p></div><a href="?business=chemtech&amp;page=orders">All orders</a></header><?php if(!$ctCustomerOrders): ?><div class="ct-empty compact"><b>No orders yet</b><span>Create an order for this customer.</span></div><?php else: ?><div class="ct-table-wrap"><table class="ct-table"><thead><tr><th>Order</th><th>Product</th><th>Total</th><th>Invoice</th></tr></thead><tbody><?php foreach(array_slice($ctCustomerOrders,0,8) as $order): ?><tr><td><b><?= $e($order['order_number']) ?></b><small><?= $e(date('d M Y',strtotime((string)$order['created_at']))) ?></small></td><td><?= $e($order['product_name']) ?></td><td><?= chemtechMoney((int)$order['total_paise']) ?></td><td><?php if(($order['billing_status']??'unbilled')==='billed'&&!empty($order['invoice_id'])): ?><?= $ctInvoiceLink($order['invoice_id']) ?><?php else: ?><form class="ct-inline-form" method="post"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><input type="hidden" name="order_ids[]" value="<?= $e($order['id']) ?>"><button class="ct-table-action primary" name="create_chemtech_invoice" value="1">Create invoice</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></article>
                </section>
            <?php else: ?>
                <section class="ct-card ct-index"><header class="ct-index-toolbar"><div><h2>All customers</h2><span><?= count($chemtechCustomers) ?> total · select a customer to view or edit</span></div><div class="ct-toolbar-actions"><label class="ct-search"><span>Search</span><input type="search" placeholder="Search customers" data-ct-table-filter="ct-customer-table"></label><button class="ct-button primary" type="button" data-dialog-open="ct-customer-dialog">Add customer</button></div></header><div class="ct-table-wrap"><table class="ct-table" id="ct-customer-table" data-ct-paginated><thead><tr><th>Company</th><th>Contact</th><th>GST profile</th><th>Billing cycle</th><th>Credit</th><th></th></tr></thead><tbody><?php foreach($chemtechCustomers as $customer): ?><tr><td><b><?= $ctCustomerLink($customer['id'],$customer['name']) ?></b><small><?= $e($customer['state']?:'State not set') ?></small></td><td><?= $e($customer['contact_person']?:'—') ?><small><?= $e($customer['email']?:$customer['phone']) ?></small></td><td><?= $e($customer['gstin']?:'Unregistered') ?><small>State code <?= $e($customer['state_code']?:'—') ?></small></td><td><?= $e(ucwords(str_replace('_',' ',$customer['billing_cycle']))) ?></td><td><?= (int)$customer['credit_days'] ?> days</td><td><?= $ctCustomerLink($customer['id'],'View / edit') ?></td></tr><?php endforeach; ?></tbody></table></div></section>
            <?php endif; ?>

        <?php elseif($chemtechPage==='products'): ?>
            <section class="ct-card ct-index"><header class="ct-index-toolbar"><div><h2>Product catalogue</h2><span><?= count($chemtechProducts) ?> products</span></div><div class="ct-toolbar-actions"><label class="ct-search"><span>Search</span><input type="search" placeholder="Search products" data-ct-table-filter="ct-product-table"></label><button class="ct-button primary" type="button" data-dialog-open="ct-product-dialog">Add product</button></div></header><div class="ct-table-wrap"><table class="ct-table" id="ct-product-table"><thead><tr><th>Product</th><th>Category</th><th>HSN / GST</th><th>Selling price</th><th>Available stock</th></tr></thead><tbody><?php foreach($chemtechProducts as $product): $low=(int)$product['stock_milli']<=(int)$product['reorder_milli']; ?><tr><td><b><?= $e($product['name']) ?></b><small><?= $e($product['sku']) ?></small></td><td><?= $e($product['category']?:'Uncategorised') ?></td><td><?= $e($product['hsn']?:'—') ?><small><?= number_format($product['gst_rate_bps']/100,2) ?>% GST</small></td><td><?= chemtechMoney((int)$product['sale_price_paise']) ?> / <?= $e($product['unit']) ?></td><td><span class="ct-status <?= $low?'warning':'ready' ?>"><?= number_format($product['stock_milli']/1000,3) ?> <?= $e($product['unit']) ?></span></td></tr><?php endforeach; ?></tbody></table></div></section>

        <?php elseif($chemtechPage==='orders'): ?>
            <section class="ct-card ct-index"><header class="ct-index-toolbar"><div><h2>Sales orders</h2><span><?= count($chemtechOrders) ?> total · prices remain editable until invoiced</span></div><div class="ct-toolbar-actions"><label class="ct-search"><span>Search</span><input type="search" placeholder="Search orders" data-ct-table-filter="ct-order-table"></label><button class="ct-button primary" type="button" data-dialog-open="ct-order-dialog">Create order</button></div></header><?php if(!$chemtechOrders): ?><div class="ct-empty"><b>No sales orders</b><span>Orders will appear here after creation.</span><button class="ct-button" type="button" data-dialog-open="ct-order-dialog">Create first order</button></div><?php else: ?><div class="ct-table-wrap"><table class="ct-table" id="ct-order-table"><thead><tr><th>Order</th><th>Customer</th><th>Item</th><th>Unit price</th><th>Tax</th><th>Total</th><th>Billing</th><th></th></tr></thead><tbody><?php foreach($chemtechOrders as $order): ?><tr><td><b><?= $e($order['order_number']) ?></b><small><?= $e(date('d M Y',strtotime((string)$order['created_at']))) ?></small></td><td><?= $ctCustomerLink($order['customer_id'],$order['customer_name']) ?></td><td><?= $e($order['product_name']) ?><small><?= number_format($order['quantity_milli']/1000,3).' '.$e($order['unit']) ?></small></td><td><?= chemtechMoney((int)$order['unit_price_paise']) ?><small>per <?= $e($order['unit']) ?></small></td><td><?= $e($order['tax_type']) ?></td><td><b><?= chemtechMoney((int)$order['total_paise']) ?></b></td><td><span class="ct-status <?= $e($order['billing_status']) ?>"><?= $e(ucfirst($order['billing_status'])) ?></span></td><td><?php if(($order['billing_status']??'unbilled')==='unbilled'): ?><button class="ct-table-action" type="button" data-ct-edit-order data-order-id="<?= $e($order['id']) ?>" data-order-number="<?= $e($order['order_number']) ?>" data-unit-price="<?= number_format($order['unit_price_paise']/100,2,'.','') ?>">Edit price</button><?php elseif(!empty($order['invoice_id'])): ?><?= $ctInvoiceLink($order['invoice_id']) ?><?php else: ?><span class="ct-locked">Locked</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>

        <?php elseif($chemtechPage==='invoices'): ?>
            <?php if($ctSelectedInvoice): ?>
                <?php
                $ctInvoiceCompanyName = (string) ($ctSelectedInvoice['company_name'] ?? ($chemtechSettings['legal_name'] ?: $chemtechSettings['company_name']));
                $ctInvoiceCompanyAddress = (string) ($ctSelectedInvoice['company_address'] ?? $chemtechSettings['address']);
                $ctInvoiceCompanyGstin = (string) ($ctSelectedInvoice['company_gstin'] ?? $chemtechSettings['gstin']);
                $ctInvoiceCustomerAddress = (string) ($ctSelectedInvoice['customer_address'] ?? ($ctSelectedCustomer['address'] ?? ''));
                $ctInvoiceCustomerGstin = (string) ($ctSelectedInvoice['customer_gstin'] ?? ($ctSelectedCustomer['gstin'] ?? ''));
                $ctInvoiceBalance = max(0, (int) $ctSelectedInvoice['total_paise'] - (int) $ctSelectedInvoice['paid_paise']);
                $ctInvoiceDownloadData = [
                    'filename'=>'Invoice-'.preg_replace('/[^A-Za-z0-9_-]+/','-',(string)$ctSelectedInvoice['invoice_number']).'.pdf',
                    'company'=>['name'=>$ctInvoiceCompanyName,'address'=>$ctInvoiceCompanyAddress,'gstin'=>$ctInvoiceCompanyGstin,'phone'=>$chemtechSettings['phone'],'email'=>$chemtechSettings['email']],
                    'customer'=>['name'=>$ctSelectedInvoice['customer_name'],'address'=>$ctInvoiceCustomerAddress,'gstin'=>$ctInvoiceCustomerGstin],
                    'invoice'=>['number'=>$ctSelectedInvoice['invoice_number'],'date'=>date('d M Y',strtotime((string)$ctSelectedInvoice['invoice_date'])),'due_date'=>date('d M Y',strtotime((string)$ctSelectedInvoice['due_date'])),'status'=>ucwords(str_replace('_',' ',(string)$ctSelectedInvoice['status']))],
                    'bank'=>['name'=>$chemtechSettings['bank_name'],'account'=>$chemtechSettings['account_number'],'ifsc'=>$chemtechSettings['ifsc']],
                    'lines'=>array_map(static fn(array $order):array=>['description'=>$order['product_name'],'order_number'=>$order['order_number'],'hsn'=>$order['hsn'],'quantity'=>$order['quantity_milli']/1000,'unit'=>$order['unit'],'rate_paise'=>$order['unit_price_paise'],'subtotal_paise'=>$order['subtotal_paise'],'tax_paise'=>(int)$order['cgst_paise']+(int)$order['sgst_paise']+(int)$order['igst_paise'],'tax_rate_bps'=>$order['gst_rate_bps'],'total_paise'=>$order['total_paise']],$ctSelectedOrders),
                    'totals'=>['subtotal_paise'=>$ctSelectedInvoice['subtotal_paise'],'cgst_paise'=>$ctSelectedInvoice['cgst_paise'],'sgst_paise'=>$ctSelectedInvoice['sgst_paise'],'igst_paise'=>$ctSelectedInvoice['igst_paise'],'total_paise'=>$ctSelectedInvoice['total_paise'],'paid_paise'=>$ctSelectedInvoice['paid_paise'],'balance_paise'=>$ctInvoiceBalance],
                ];
                ?>
                <div class="ct-invoice-actions">
                    <a class="ct-button" href="?business=chemtech&amp;page=invoices">← Back to invoices</a>
                    <div><button class="ct-button" type="button" data-print-invoice>Print</button><button class="ct-button primary" type="button" data-download-invoice>Download PDF</button></div>
                </div>
                <article class="ct-invoice-document" aria-label="GST invoice <?= $e($ctSelectedInvoice['invoice_number']) ?>">
                    <header class="ct-invoice-header">
                        <div><span>TAX INVOICE</span><h2><?= $e($ctInvoiceCompanyName) ?></h2><p><?= nl2br($e($ctInvoiceCompanyAddress ?: 'Add the registered address in Settings.')) ?></p><small>GSTIN: <?= $e($ctInvoiceCompanyGstin ?: 'Not configured') ?></small></div>
                        <div><strong><?= $e($ctSelectedInvoice['invoice_number']) ?></strong><dl><dt>Invoice date</dt><dd><?= $e(date('d M Y', strtotime((string) $ctSelectedInvoice['invoice_date']))) ?></dd><dt>Due date</dt><dd><?= $e(date('d M Y', strtotime((string) $ctSelectedInvoice['due_date']))) ?></dd><dt>Status</dt><dd><?= $e(ucwords(str_replace('_',' ',(string)$ctSelectedInvoice['status']))) ?></dd></dl></div>
                    </header>
                    <section class="ct-invoice-parties">
                        <div><span>BILL TO</span><strong><?= $ctCustomerLink($ctSelectedInvoice['customer_id'],$ctSelectedInvoice['customer_name']) ?></strong><p><?= nl2br($e($ctInvoiceCustomerAddress ?: 'Address not provided')) ?></p><small>GSTIN: <?= $e($ctInvoiceCustomerGstin ?: 'Unregistered') ?></small></div>
                        <div><span>PAYMENT DETAILS</span><p><b><?= $e($chemtechSettings['bank_name'] ?: 'Bank not configured') ?></b><br>Account: <?= $e($chemtechSettings['account_number'] ?: '—') ?><br>IFSC: <?= $e($chemtechSettings['ifsc'] ?: '—') ?></p></div>
                    </section>
                    <div class="ct-table-wrap"><table class="ct-invoice-lines"><thead><tr><th>#</th><th>Description</th><th>HSN</th><th>Qty</th><th>Rate</th><th>Taxable</th><th>Tax</th><th>Total</th></tr></thead><tbody><?php foreach($ctSelectedOrders as $index=>$order): $lineTax=(int)$order['cgst_paise']+(int)$order['sgst_paise']+(int)$order['igst_paise']; ?><tr><td><?= $index+1 ?></td><td><b><?= $e($order['product_name']) ?></b><small><?= $e($order['order_number']) ?></small></td><td><?= $e($order['hsn'] ?: '—') ?></td><td><?= number_format($order['quantity_milli']/1000,3).' '.$e($order['unit']) ?></td><td><?= chemtechMoney((int)$order['unit_price_paise']) ?></td><td><?= chemtechMoney((int)$order['subtotal_paise']) ?></td><td><?= chemtechMoney($lineTax) ?><small><?= number_format($order['gst_rate_bps']/100,2) ?>%</small></td><td><b><?= chemtechMoney((int)$order['total_paise']) ?></b></td></tr><?php endforeach; ?></tbody></table></div>
                    <footer class="ct-invoice-totals"><div><span>Payment status</span><strong><?= $ctInvoiceBalance > 0 ? chemtechMoney($ctInvoiceBalance).' due' : 'Paid in full' ?></strong></div><dl><dt>Taxable amount</dt><dd><?= chemtechMoney((int)$ctSelectedInvoice['subtotal_paise']) ?></dd><?php if((int)$ctSelectedInvoice['cgst_paise']>0): ?><dt>CGST</dt><dd><?= chemtechMoney((int)$ctSelectedInvoice['cgst_paise']) ?></dd><dt>SGST</dt><dd><?= chemtechMoney((int)$ctSelectedInvoice['sgst_paise']) ?></dd><?php else: ?><dt>IGST</dt><dd><?= chemtechMoney((int)$ctSelectedInvoice['igst_paise']) ?></dd><?php endif; ?><dt class="total">Invoice total</dt><dd class="total"><?= chemtechMoney((int)$ctSelectedInvoice['total_paise']) ?></dd><dt>Amount received</dt><dd><?= chemtechMoney((int)$ctSelectedInvoice['paid_paise']) ?></dd><dt>Balance due</dt><dd><?= chemtechMoney($ctInvoiceBalance) ?></dd></dl></footer>
                    <p class="ct-invoice-note">Computer-generated tax invoice. Please verify company GST, address and bank details in Settings before using a real invoice.</p>
                </article>
                <script type="application/json" id="ct-invoice-data"><?= json_encode($ctInvoiceDownloadData,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES) ?></script>
            <?php else: ?>
                <section class="ct-summary ct-summary-three"><div><span>Invoiced</span><strong><?= chemtechMoney($ctSales) ?></strong><small><?= count($chemtechInvoices) ?> invoices</small></div><div><span>Outstanding</span><strong><?= chemtechMoney($ctOutstanding) ?></strong><small><?= count($ctOpenInvoices) ?> open</small></div><div><span>Ready to bill</span><strong><?= chemtechMoney($ctUnbilledValue) ?></strong><small><?= count($ctUnbilled) ?> unbilled orders</small></div></section>
                <section class="ct-card ct-index"><header class="ct-index-toolbar"><div><h2>Invoice register</h2><span>Step 4: turn an unbilled order into an invoice</span></div><div class="ct-toolbar-actions"><label class="ct-search"><span>Search</span><input type="search" placeholder="Search invoices" data-ct-table-filter="ct-invoice-table"></label><button class="ct-button primary" type="button" data-dialog-open="ct-invoice-dialog" <?= !$ctUnbilled?'disabled':'' ?>>Generate invoice</button></div></header><?php if(!$chemtechInvoices): ?><div class="ct-empty"><b>No invoices generated</b><span>First create a sales order, then return here.</span><a class="ct-button" href="?business=chemtech&amp;page=orders">Go to orders</a></div><?php else: ?><div class="ct-table-wrap"><table class="ct-table" id="ct-invoice-table"><thead><tr><th>Invoice</th><th>Customer</th><th>Orders</th><th>Total</th><th>Balance</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($chemtechInvoices as $invoice): $balance=max(0,(int)$invoice['total_paise']-(int)$invoice['paid_paise']); ?><tr><td><b><?= $e($invoice['invoice_number']) ?></b><small><?= $e($invoice['invoice_date'].' · due '.$invoice['due_date']) ?></small></td><td><?= $ctCustomerLink($invoice['customer_id'],$invoice['customer_name']) ?></td><td><?= count($invoice['order_ids']) ?></td><td><?= chemtechMoney((int)$invoice['total_paise']) ?></td><td><?= chemtechMoney($balance) ?></td><td><span class="ct-status <?= $e($invoice['status']) ?>"><?= $e(ucwords(str_replace('_',' ',$invoice['status']))) ?></span></td><td><?= $ctInvoiceLink($invoice['id'],'View') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
            <?php endif; ?>

        <?php elseif($chemtechPage==='payments'): ?>
            <section class="ct-summary ct-summary-three"><div><span>Receivables</span><strong><?= chemtechMoney($ctOutstanding) ?></strong><small><?= count($ctOpenInvoices) ?> open invoices</small></div><div><span>Collected</span><strong><?= chemtechMoney($ctCollected) ?></strong><small><?= count($chemtechPayments) ?> payments</small></div><div><span>Collection rate</span><strong><?= $ctSales?round($ctCollected/$ctSales*100):0 ?>%</strong><small>Against invoiced sales</small></div></section>
            <section class="ct-card ct-index"><header class="ct-index-toolbar"><div><h2>Payment history</h2><span>Latest collections</span></div><div class="ct-toolbar-actions"><label class="ct-search"><span>Search</span><input type="search" placeholder="Search payments" data-ct-table-filter="ct-payment-table"></label><button class="ct-button primary" type="button" data-dialog-open="ct-payment-dialog" <?= !$ctOpenInvoices?'disabled':'' ?>>Record payment</button></div></header><?php if(!$chemtechPayments): ?><div class="ct-empty"><b>No payments recorded</b><span>Payments appear here after they are allocated to an invoice.</span></div><?php else: ?><div class="ct-table-wrap"><table class="ct-table" id="ct-payment-table"><thead><tr><th>Date</th><th>Customer</th><th>Invoice</th><th>Method</th><th>Reference</th><th>Amount</th></tr></thead><tbody><?php foreach($chemtechPayments as $payment): $paymentInvoice=chemtechFind($chemtechInvoices,(string)($payment['invoice_id']??'')); ?><tr><td><?= $e($payment['payment_date']) ?></td><td><b><?= $paymentInvoice ? $ctCustomerLink($paymentInvoice['customer_id'],$payment['customer_name']) : $e($payment['customer_name']) ?></b></td><td><?= $paymentInvoice ? $ctInvoiceLink($payment['invoice_id'],$payment['invoice_number']) : $e($payment['invoice_number']) ?></td><td><?= $e(ucfirst($payment['method'])) ?></td><td><?= $e($payment['reference']?:'—') ?></td><td><b><?= chemtechMoney((int)$payment['amount_paise']) ?></b></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>

        <?php elseif($chemtechPage==='inventory'): ?>
            <section class="ct-card ct-index"><header class="ct-index-toolbar"><div><h2>Stock overview</h2><span><?= count($ctLowStock) ?> products need attention</span></div><div class="ct-toolbar-actions"><label class="ct-search"><span>Search</span><input type="search" placeholder="Search inventory" data-ct-table-filter="ct-inventory-table"></label><a class="ct-button" href="?business=chemtech&amp;page=products">Manage products</a></div></header><div class="ct-table-wrap"><table class="ct-table" id="ct-inventory-table"><thead><tr><th>Product</th><th>Category</th><th>Available</th><th>Reorder level</th><th>Stock health</th></tr></thead><tbody><?php foreach($chemtechProducts as $product): $low=(int)$product['stock_milli']<=(int)$product['reorder_milli']; ?><tr><td><b><?= $e($product['name']) ?></b><small><?= $e($product['sku']) ?></small></td><td><?= $e($product['category']) ?></td><td><?= number_format($product['stock_milli']/1000,3).' '.$e($product['unit']) ?></td><td><?= number_format($product['reorder_milli']/1000,3).' '.$e($product['unit']) ?></td><td><span class="ct-status <?= $low?'warning':'ready' ?>"><?= $low?'Reorder required':'In stock' ?></span></td></tr><?php endforeach; ?></tbody></table></div></section>

        <?php elseif($chemtechPage==='settings'): ?>
            <form class="ct-settings-form" method="post"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
                <section class="ct-settings-row"><div><h2>Company identity</h2><p>Used throughout the workspace and future documents.</p></div><article class="ct-card"><div class="ct-brand-preview"><span><?= $e($chemtechSettings['short_name']) ?></span><div><b><?= $e($chemtechSettings['company_name']) ?></b><small>Workspace identity</small></div></div><div class="ct-form-grid two"><label>Workspace name<input name="company_name" value="<?= $e($chemtechSettings['company_name']) ?>" required></label><label>Icon letters<input name="short_name" maxlength="3" value="<?= $e($chemtechSettings['short_name']) ?>" required></label></div><label>Legal company name<input name="legal_name" value="<?= $e($chemtechSettings['legal_name']) ?>"></label></article></section>
                <section class="ct-settings-row"><div><h2>Tax registration</h2><p>State codes control CGST/SGST and IGST automatically.</p></div><article class="ct-card"><div class="ct-form-grid two"><label>GSTIN<input name="gstin" maxlength="15" value="<?= $e($chemtechSettings['gstin']) ?>"></label><label>PAN<input name="pan" value="<?= $e($chemtechSettings['pan']) ?>"></label><label>State<input name="state" value="<?= $e($chemtechSettings['state']) ?>"></label><label>GST state code<input name="state_code" maxlength="2" value="<?= $e($chemtechSettings['state_code']) ?>"></label></div><label>Registered address<textarea name="address" rows="3"><?= $e($chemtechSettings['address']) ?></textarea></label></article></section>
                <section class="ct-settings-row"><div><h2>Contact and bank</h2><p>Shown on invoices and commercial documents.</p></div><article class="ct-card"><div class="ct-form-grid two"><label>Phone<input name="phone" value="<?= $e($chemtechSettings['phone']) ?>"></label><label>Email<input type="email" name="email" value="<?= $e($chemtechSettings['email']) ?>"></label><label>Bank name<input name="bank_name" value="<?= $e($chemtechSettings['bank_name']) ?>"></label><label>Account number<input name="account_number" value="<?= $e($chemtechSettings['account_number']) ?>"></label><label>IFSC<input name="ifsc" value="<?= $e($chemtechSettings['ifsc']) ?>"></label><label>Invoice prefix<input name="invoice_prefix" maxlength="10" value="<?= $e($chemtechSettings['invoice_prefix']) ?>"></label></div></article></section>
                <div class="ct-settings-save"><button class="ct-button primary" name="save_chemtech_settings" value="1">Save settings</button></div>
            </form>

        <?php else: ?>
            <section class="ct-card ct-module"><div class="ct-module-badge">In development</div><h2><?= $e($ctTitle) ?></h2><p><?= $e($ctSubtitle) ?></p><div class="ct-module-flow"><?php $moduleSteps=['enquiries'=>['Capture enquiry','Schedule follow-up','Convert to quotation'],'quotations'=>['Build quotation','Share PDF','Convert to order'],'purchases'=>['Create supplier','Issue purchase order','Record goods receipt'],'dispatch'=>['Create delivery note','Add transport details','Record proof of delivery'],'reports'=>['Sales and GST','Outstanding ageing','Stock and margin'],'documents'=>['Invoices','Certificates','Supporting files']][$chemtechPage]??['Configure','Review','Complete']; foreach($moduleSteps as $step): ?><span><?= $e($step) ?></span><?php endforeach; ?></div><p class="ct-help">This module will connect to the existing customer → order → billing → payment workflow.</p></section>
        <?php endif; ?>
    </main>
</div>

<dialog class="ct-dialog" id="ct-customer-view-dialog">
    <form class="ct-dialog-panel ct-dialog-panel-wide" method="post">
        <header><div><span>CUSTOMER</span><h2 data-ct-customer-title>Customer details</h2></div><button type="button" data-dialog-close aria-label="Close">×</button></header>
        <div class="ct-dialog-body">
            <input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>">
            <input type="hidden" name="customer_id" data-ct-customer-field="id">
            <input type="hidden" name="return_page" value="<?= $e($chemtechPage) ?>">
            <section class="ct-popup-customer-head"><div class="ct-customer-avatar" data-ct-customer-avatar>CT</div><div><strong data-ct-customer-name>Customer</strong><span data-ct-customer-summary>Contact and tax profile</span></div><nav><a class="ct-button" data-ct-customer-call hidden>Call</a><a class="ct-button" data-ct-customer-whatsapp target="_blank" rel="noopener" hidden>WhatsApp</a><a class="ct-button" data-ct-customer-email hidden>Email</a></nav></section>
            <section class="ct-popup-stats"><div><span>Orders</span><strong data-ct-customer-stat="order_count">0</strong></div><div><span>Invoiced</span><strong data-ct-customer-money="invoiced_paise">₹0.00</strong></div><div><span>Received</span><strong data-ct-customer-money="received_paise">₹0.00</strong></div><div><span>Outstanding</span><strong data-ct-customer-money="outstanding_paise">₹0.00</strong></div></section>
            <div class="ct-form-grid two"><label>Business name<input name="name" data-ct-customer-field="name" required></label><label>Contact person<input name="contact_person" data-ct-customer-field="contact_person"></label><label>Phone / WhatsApp<input name="phone" inputmode="tel" data-ct-customer-field="phone"></label><label>Email<input type="email" name="email" data-ct-customer-field="email"></label><label>GSTIN<input name="gstin" maxlength="15" data-ct-customer-field="gstin"></label><label>State code<input name="state_code" inputmode="numeric" maxlength="2" data-ct-customer-field="state_code"></label><label>State<input name="state" data-ct-customer-field="state"></label><label>Billing cycle<select name="billing_cycle" data-ct-customer-field="billing_cycle"><?php foreach(['per_order'=>'Per order','weekly'=>'Weekly','fortnightly'=>'Fortnightly','monthly'=>'Monthly','custom'=>'Custom'] as $value=>$label): ?><option value="<?= $e($value) ?>"><?= $e($label) ?></option><?php endforeach; ?></select></label><label>Credit days<input type="number" name="credit_days" min="0" max="365" data-ct-customer-field="credit_days"></label></div>
            <label>Billing address<textarea name="address" rows="4" data-ct-customer-field="address"></textarea></label>
        </div>
        <footer><button class="ct-button" type="button" data-dialog-close>Cancel</button><button class="ct-button primary" name="update_chemtech_customer" value="1">Save changes</button></footer>
    </form>
</dialog>

<?php if(!$ctSelectedInvoice): ?>
<dialog class="ct-dialog ct-invoice-view-dialog" id="ct-invoice-view-dialog">
    <div class="ct-dialog-panel ct-dialog-panel-invoice">
        <header><div><span>TAX INVOICE</span><h2 data-ct-invoice-title>Invoice</h2></div><button type="button" data-dialog-close aria-label="Close">×</button></header>
        <div class="ct-dialog-body ct-invoice-popup-body"><article class="ct-invoice-document" data-ct-invoice-document></article></div>
        <footer><button class="ct-button" type="button" data-dialog-close>Close</button><button class="ct-button primary" type="button" data-download-invoice>Download PDF</button></footer>
    </div>
</dialog>
<script type="application/json" id="ct-invoice-data">{}</script>
<?php endif; ?>
<script type="application/json" id="ct-customer-records"><?= json_encode($ctCustomerRecords,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES) ?></script>
<script type="application/json" id="ct-invoice-records"><?= json_encode($ctInvoiceRecords,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES) ?></script>
<script type="application/json" id="ct-popup-context"><?= json_encode(['customer'=>$ctAutoOpenCustomerId,'invoice'=>$ctAutoOpenInvoiceId],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>

<dialog class="ct-dialog" id="ct-customer-dialog"><form class="ct-dialog-panel" method="post"><header><div><span>Customers</span><h2>Add customer</h2></div><button type="button" data-dialog-close aria-label="Close">×</button></header><div class="ct-dialog-body"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><label>Business name<input name="name" required></label><div class="ct-form-grid two"><label>Contact person<input name="contact_person"></label><label>Phone<input name="phone" inputmode="tel"></label></div><label>Email<input type="email" name="email"></label><div class="ct-form-grid two"><label>GSTIN<input name="gstin" maxlength="15"></label><label>State code<input name="state_code" inputmode="numeric" maxlength="2" placeholder="27"></label><label>State<input name="state"></label><label>Billing cycle<select name="billing_cycle"><option value="per_order">Per order</option><option value="weekly">Weekly</option><option value="fortnightly">Fortnightly</option><option value="monthly">Monthly</option><option value="custom">Custom</option></select></label></div><label>Credit days<input type="number" name="credit_days" min="0" max="365" value="30"></label><label>Billing address<textarea name="address" rows="3"></textarea></label></div><footer><button class="ct-button" type="button" data-dialog-close>Cancel</button><button class="ct-button primary" name="add_chemtech_customer" value="1">Save customer</button></footer></form></dialog>

<?php if($ctCustomerProfile): ?>
<dialog class="ct-dialog" id="ct-edit-customer-dialog"><form class="ct-dialog-panel" method="post"><header><div><span>CUSTOMER PROFILE</span><h2>Edit customer</h2></div><button type="button" data-dialog-close aria-label="Close">×</button></header><div class="ct-dialog-body"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><input type="hidden" name="customer_id" value="<?= $e($ctCustomerProfile['id']) ?>"><label>Business name<input name="name" value="<?= $e($ctCustomerProfile['name']) ?>" required></label><div class="ct-form-grid two"><label>Contact person<input name="contact_person" value="<?= $e($ctCustomerProfile['contact_person']) ?>"></label><label>Phone / WhatsApp<input name="phone" inputmode="tel" value="<?= $e($ctCustomerProfile['phone']) ?>"></label></div><label>Email<input type="email" name="email" value="<?= $e($ctCustomerProfile['email']) ?>"></label><div class="ct-form-grid two"><label>GSTIN<input name="gstin" maxlength="15" value="<?= $e($ctCustomerProfile['gstin']) ?>"></label><label>State code<input name="state_code" inputmode="numeric" maxlength="2" value="<?= $e($ctCustomerProfile['state_code']) ?>"></label><label>State<input name="state" value="<?= $e($ctCustomerProfile['state']) ?>"></label><label>Billing cycle<select name="billing_cycle"><?php foreach(['per_order'=>'Per order','weekly'=>'Weekly','fortnightly'=>'Fortnightly','monthly'=>'Monthly','custom'=>'Custom'] as $value=>$label): ?><option value="<?= $e($value) ?>" <?= ($ctCustomerProfile['billing_cycle']??'per_order')===$value?'selected':'' ?>><?= $e($label) ?></option><?php endforeach; ?></select></label></div><label>Credit days<input type="number" name="credit_days" min="0" max="365" value="<?= (int)$ctCustomerProfile['credit_days'] ?>"></label><label>Billing address<textarea name="address" rows="4"><?= $e($ctCustomerProfile['address']) ?></textarea></label></div><footer><button class="ct-button" type="button" data-dialog-close>Cancel</button><button class="ct-button primary" name="update_chemtech_customer" value="1">Save changes</button></footer></form></dialog>
<?php endif; ?>

<dialog class="ct-dialog" id="ct-product-dialog"><form class="ct-dialog-panel" method="post"><header><div><span>Products</span><h2>Add chemical</h2></div><button type="button" data-dialog-close aria-label="Close">×</button></header><div class="ct-dialog-body"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><label>Product name<input name="name" required></label><div class="ct-form-grid two"><label>SKU<input name="sku" required></label><label>Category<input name="category"></label><label>HSN code<input name="hsn"></label><label>Unit<select name="unit"><option>kg</option><option>litre</option><option>drum</option><option>bag</option><option>unit</option></select></label><label>GST rate %<input type="number" name="gst_rate" min="0" max="50" step=".01" value="18"></label><label>Selling price<input type="number" name="sale_price" min="0" step=".01" required></label><label>Opening stock<input type="number" name="stock" min="0" step=".001"></label><label>Reorder level<input type="number" name="reorder_level" min="0" step=".001"></label></div></div><footer><button class="ct-button" type="button" data-dialog-close>Cancel</button><button class="ct-button primary" name="add_chemtech_product" value="1">Save product</button></footer></form></dialog>

<dialog class="ct-dialog" id="ct-order-dialog"><form class="ct-dialog-panel" method="post"><header><div><span>Orders</span><h2>Create sales order</h2></div><button type="button" data-dialog-close aria-label="Close">×</button></header><div class="ct-dialog-body"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><label>Customer<select name="customer_id" required><option value="">Choose customer…</option><?php foreach($chemtechCustomers as $customer): ?><option value="<?= $e($customer['id']) ?>"><?= $e($customer['name'].' · state '.$customer['state_code']) ?></option><?php endforeach; ?></select></label><label>Product<select name="product_id" required><option value="">Choose product…</option><?php foreach($chemtechProducts as $product): ?><option value="<?= $e($product['id']) ?>"><?= $e($product['name']) ?> · <?= number_format($product['stock_milli']/1000,3) ?> <?= $e($product['unit']) ?></option><?php endforeach; ?></select></label><div class="ct-form-grid two"><label>Quantity<input type="number" name="quantity" min=".001" step=".001" required></label><label>Unit price<input type="number" name="unit_price" min="0" step=".01" placeholder="Catalogue price"></label></div><div class="ct-inline-note">GST is selected from company and customer state codes. Confirming the order reduces available stock.</div></div><footer><button class="ct-button" type="button" data-dialog-close>Cancel</button><button class="ct-button primary" name="create_chemtech_order" value="1">Create order</button></footer></form></dialog>

<dialog class="ct-dialog" id="ct-edit-order-dialog"><form class="ct-dialog-panel" method="post"><header><div><span>UNBILLED ORDER</span><h2>Edit order price</h2></div><button type="button" data-dialog-close aria-label="Close">×</button></header><div class="ct-dialog-body"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><input type="hidden" name="order_id" data-ct-edit-order-id><div class="ct-inline-note"><b data-ct-edit-order-number>Order</b><br>The taxable value, GST and final total will be recalculated automatically.</div><label>Unit price<input type="number" name="unit_price" min=".01" step=".01" data-ct-edit-order-price required></label><p class="ct-field-help">This price is for one product unit. Invoiced orders are locked to protect issued invoices.</p></div><footer><button class="ct-button" type="button" data-dialog-close>Cancel</button><button class="ct-button primary" name="update_chemtech_order_price" value="1">Update price</button></footer></form></dialog>

<dialog class="ct-dialog" id="ct-invoice-dialog"><form class="ct-dialog-panel" method="post" data-ct-invoice-form><header><div><span>STEP 4 OF 5</span><h2>Generate GST invoice</h2></div><button type="button" data-dialog-close aria-label="Close">×</button></header><div class="ct-dialog-body"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><div class="ct-inline-note"><b>First choose a customer.</b> Then select their unbilled order below. GST and totals are calculated automatically.</div><?php if(!$ctUnbilled): ?><div class="ct-empty"><b>No unbilled orders</b><span>Create a sales order first.</span></div><?php else: ?><label>1. Customer<select data-ct-invoice-customer required><option value="">Choose customer…</option><?php foreach($chemtechCustomers as $customer): if(!array_filter($ctUnbilled,static fn(array $order):bool=>($order['customer_id']??'')===($customer['id']??''))) continue; ?><option value="<?= $e($customer['id']) ?>"><?= $e($customer['name']) ?> · <?= $e(ucwords(str_replace('_',' ',$customer['billing_cycle']))) ?></option><?php endforeach; ?></select></label><label class="ct-order-picker-title">2. Unbilled order</label><div class="ct-select-list" data-ct-invoice-orders><p>Choose a customer to see their orders.</p><?php foreach($ctUnbilled as $order): ?><label data-customer="<?= $e($order['customer_id']) ?>" hidden><input type="checkbox" name="order_ids[]" value="<?= $e($order['id']) ?>"><span><b><?= $e($order['order_number'].' · '.$order['product_name']) ?></b><small><?= number_format($order['quantity_milli']/1000,3).' '.$e($order['unit']) ?> · <?= chemtechMoney((int)$order['total_paise']) ?></small></span></label><?php endforeach; ?></div><?php endif; ?></div><footer><button class="ct-button" type="button" data-dialog-close>Cancel</button><button class="ct-button primary" name="create_chemtech_invoice" value="1" <?= !$ctUnbilled?'disabled':'' ?>>Create &amp; open invoice</button></footer></form></dialog>

<dialog class="ct-dialog" id="ct-payment-dialog"><form class="ct-dialog-panel" method="post"><header><div><span>Payments</span><h2>Record collection</h2></div><button type="button" data-dialog-close aria-label="Close">×</button></header><div class="ct-dialog-body"><input type="hidden" name="csrf" value="<?= $e($_SESSION['csrf']) ?>"><label>Invoice<select name="invoice_id" required><option value="">Choose invoice…</option><?php foreach($ctOpenInvoices as $invoice): ?><option value="<?= $e($invoice['id']) ?>"><?= $e($invoice['invoice_number'].' · '.$invoice['customer_name']) ?> · <?= chemtechMoney((int)$invoice['total_paise']-(int)$invoice['paid_paise']) ?></option><?php endforeach; ?></select></label><div class="ct-form-grid two"><label>Amount<input type="number" name="amount" min=".01" step=".01" required></label><label>Date<input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required></label><label>Method<select name="method"><option value="bank">Bank transfer</option><option value="upi">UPI</option><option value="cheque">Cheque</option><option value="cash">Cash</option></select></label><label>Reference<input name="reference"></label></div></div><footer><button class="ct-button" type="button" data-dialog-close>Cancel</button><button class="ct-button primary" name="record_chemtech_payment" value="1">Record payment</button></footer></form></dialog>
