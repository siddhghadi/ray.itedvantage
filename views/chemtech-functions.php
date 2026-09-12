<?php
declare(strict_types=1);

const CHEMTECH_SETTINGS_FILE = __DIR__ . '/../storage/chemtech-settings.json';
const CHEMTECH_CUSTOMERS_FILE = __DIR__ . '/../storage/chemtech-customers.json';
const CHEMTECH_PRODUCTS_FILE = __DIR__ . '/../storage/chemtech-products.json';
const CHEMTECH_ORDERS_FILE = __DIR__ . '/../storage/chemtech-orders.json';
const CHEMTECH_INVOICES_FILE = __DIR__ . '/../storage/chemtech-invoices.json';
const CHEMTECH_PAYMENTS_FILE = __DIR__ . '/../storage/chemtech-payments.json';
const CHEMTECH_ENQUIRIES_FILE = __DIR__ . '/../storage/chemtech-enquiries.json';
const CHEMTECH_DEMO_MARKER_FILE = __DIR__ . '/../storage/chemtech-demo-v1.json';

function chemtechDefaults(): array
{
    return [
        'company_name' => 'ChemTech CRM',
        'short_name' => 'CT',
        'accent' => '#faa61d',
        'legal_name' => 'ChemTech Trading Company',
        'gstin' => '',
        'pan' => '',
        'state' => 'Maharashtra',
        'state_code' => '27',
        'address' => '',
        'phone' => '',
        'email' => '',
        'bank_name' => '',
        'account_number' => '',
        'ifsc' => '',
        'invoice_prefix' => 'CT',
    ];
}

function chemtechSettings(): array
{
    return array_replace(chemtechDefaults(), loadJsonFile(CHEMTECH_SETTINGS_FILE));
}

function chemtechRedirect(string $page): never
{
    header('Location: ?business=chemtech&page=' . rawurlencode($page));
    exit;
}

function chemtechText(mixed $value, int $length = 160): string
{
    return mb_substr(trim((string) $value), 0, $length);
}

function chemtechMoneyToPaise(mixed $value): int
{
    $normalized = preg_replace('/[^0-9.\-]/', '', (string) $value) ?? '0';
    return (int) round(((float) $normalized) * 100);
}

function chemtechMoney(int $paise): string
{
    return '₹' . number_format($paise / 100, 2);
}

function chemtechFinancialYear(?DateTimeImmutable $date = null): string
{
    $date ??= new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    $year = (int) $date->format('Y');
    $start = (int) $date->format('n') >= 4 ? $year : $year - 1;
    return substr((string) $start, -2) . '-' . substr((string) ($start + 1), -2);
}

function chemtechInvoiceNumber(array $invoices, string $prefix): string
{
    $fy = chemtechFinancialYear();
    $highest = 0;
    foreach ($invoices as $invoice) {
        if (($invoice['financial_year'] ?? '') === $fy) $highest = max($highest, (int) ($invoice['sequence'] ?? 0));
    }
    return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $prefix) ?: 'CT') . '/' . $fy . '/' . str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
}

function chemtechFind(array $records, string $id): ?array
{
    foreach ($records as $record) if (hash_equals((string) ($record['id'] ?? ''), $id)) return $record;
    return null;
}

function chemtechSeed(): void
{
    if (!is_file(CHEMTECH_SETTINGS_FILE)) saveJsonFile(CHEMTECH_SETTINGS_FILE, chemtechDefaults());
    if (!is_file(CHEMTECH_CUSTOMERS_FILE)) saveJsonFile(CHEMTECH_CUSTOMERS_FILE, [
        ['id'=>'ct-customer-sanket','name'=>'Sanket Chemicals','contact_person'=>'Sanket','phone'=>'','email'=>'','gstin'=>'27ABCDE1234F1Z5','state'=>'Maharashtra','state_code'=>'27','billing_cycle'=>'monthly','credit_days'=>30,'address'=>'Mumbai, Maharashtra','created_at'=>gmdate('c')],
        ['id'=>'ct-customer-demo','name'=>'Demo Industries','contact_person'=>'Purchase Team','phone'=>'','email'=>'','gstin'=>'24ABCDE1234F1Z5','state'=>'Gujarat','state_code'=>'24','billing_cycle'=>'per_order','credit_days'=>15,'address'=>'Ahmedabad, Gujarat','created_at'=>gmdate('c')],
        ['id'=>'ct-customer-alpha','name'=>'Alpha Chemical Solutions','contact_person'=>'Accounts Team','phone'=>'','email'=>'','gstin'=>'27ABCDE1234F1Z6','state'=>'Maharashtra','state_code'=>'27','billing_cycle'=>'fortnightly','credit_days'=>30,'address'=>'Pune, Maharashtra','created_at'=>gmdate('c')],
    ]);
    if (!is_file(CHEMTECH_PRODUCTS_FILE)) saveJsonFile(CHEMTECH_PRODUCTS_FILE, [
        ['id'=>'ct-product-a','name'=>'Chemical A','category'=>'Industrial Chemicals','sku'=>'CHEM-A','hsn'=>'2901','unit'=>'kg','gst_rate_bps'=>1800,'sale_price_paise'=>12500,'stock_milli'=>145000,'reorder_milli'=>50000,'created_at'=>gmdate('c')],
        ['id'=>'ct-product-b','name'=>'Chemical B','category'=>'Solvents','sku'=>'CHEM-B','hsn'=>'2905','unit'=>'litre','gst_rate_bps'=>1800,'sale_price_paise'=>9800,'stock_milli'=>38000,'reorder_milli'=>40000,'created_at'=>gmdate('c')],
        ['id'=>'ct-product-c','name'=>'Chemical C','category'=>'Speciality Chemicals','sku'=>'CHEM-C','hsn'=>'3824','unit'=>'kg','gst_rate_bps'=>1200,'sale_price_paise'=>21000,'stock_milli'=>72000,'reorder_milli'=>25000,'created_at'=>gmdate('c')],
    ]);
    if (!is_file(CHEMTECH_ORDERS_FILE)) saveJsonFile(CHEMTECH_ORDERS_FILE, []);
    if (!is_file(CHEMTECH_INVOICES_FILE)) saveJsonFile(CHEMTECH_INVOICES_FILE, []);
    if (!is_file(CHEMTECH_PAYMENTS_FILE)) saveJsonFile(CHEMTECH_PAYMENTS_FILE, []);
    if (!is_file(CHEMTECH_ENQUIRIES_FILE)) saveJsonFile(CHEMTECH_ENQUIRIES_FILE, []);
}

function chemtechSeedDemoData(): void
{
    if (is_file(CHEMTECH_DEMO_MARKER_FILE)) return;
    $lock = fopen(__DIR__ . '/../storage/chemtech-write.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Demo data is busy. Please try again.');
    try {
        if (is_file(CHEMTECH_DEMO_MARKER_FILE)) return;
        $settings = chemtechSettings();
        $customers = loadJsonFile(CHEMTECH_CUSTOMERS_FILE);
        $products = loadJsonFile(CHEMTECH_PRODUCTS_FILE);
        $orders = loadJsonFile(CHEMTECH_ORDERS_FILE);
        $invoices = loadJsonFile(CHEMTECH_INVOICES_FILE);
        $payments = loadJsonFile(CHEMTECH_PAYMENTS_FILE);
        $enquiries = loadJsonFile(CHEMTECH_ENQUIRIES_FILE);
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
        $states = [
            ['Maharashtra','27'], ['Gujarat','24'], ['Karnataka','29'], ['Delhi','07'], ['Tamil Nadu','33'],
            ['Telangana','36'], ['Rajasthan','08'], ['Madhya Pradesh','23'], ['Uttar Pradesh','09'], ['West Bengal','19'],
        ];
        $companyRoots = ['Aarav','Apex','Arham','Avon','Bluepeak','Crest','Delta','Eastern','Evergreen','Galaxy','Global','Indus','Jupiter','Kaveri','Lotus','Metro','Navkar','Nova','Orbit','Pioneer','Prime','Reliable','Shakti','Sterling','Sunrise','Supreme','Trident','Unity','Vertex','Western','Zenith','Meridian','Spectrum','Prism','Crystal','Royal','Dynamic','Accurate','National','Progressive','Modern','Allied','Bright','Classic','Elite','Frontier','Grand','Ideal','Keystone','Liberty'];
        $companyTypes = ['Chemicals','Industries','Pharma Solutions','Speciality Materials','Process Solutions'];
        $cycles = ['per_order','weekly','fortnightly','monthly','custom'];
        for ($i = 1; $i <= 50; $i++) {
            $id = 'ct-demo-customer-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            if (chemtechFind($customers, $id)) continue;
            [$state,$stateCode] = $states[($i - 1) % count($states)];
            $name = $companyRoots[$i - 1] . ' ' . $companyTypes[($i - 1) % count($companyTypes)];
            $customers[] = ['id'=>$id,'name'=>$name,'contact_person'=>'Purchase Desk '.$i,'phone'=>'','email'=>'accounts'.$i.'@example.invalid','gstin'=>$stateCode.'ABCDE'.str_pad((string)$i,4,'0',STR_PAD_LEFT).'F1Z5','state'=>$state,'state_code'=>$stateCode,'billing_cycle'=>$cycles[($i-1)%count($cycles)],'credit_days'=>[15,30,45][($i-1)%3],'address'=>'Demo industrial area, '.$state,'created_at'=>$now->modify('-'.(60-$i).' days')->format(DATE_ATOM)];
        }

        $chemicalNames = ['Acetone','Methanol','Isopropyl Alcohol','Ethyl Acetate','Toluene','Xylene','Caustic Soda','Hydrochloric Acid','Sulphuric Acid','Nitric Acid','Acetic Acid','Formic Acid','Citric Acid','Sodium Carbonate','Sodium Bicarbonate','Hydrogen Peroxide','Ammonium Chloride','Calcium Chloride','Magnesium Sulphate','Sodium Sulphate','Monoethylene Glycol','Diethylene Glycol','Propylene Glycol','Glycerine','Butyl Acetate','Methyl Ethyl Ketone','Cyclohexanone','Hexane','Heptane','Mineral Turpentine','Sodium Hypochlorite','Ferric Chloride','Aluminium Sulphate','Zinc Oxide','Titanium Dioxide','Calcium Carbonate','Activated Carbon','Sodium Metabisulphite','Potassium Hydroxide','Phosphoric Acid','Boric Acid','Oxalic Acid','Stearic Acid','Dextrose Monohydrate','Sorbitol Solution','Liquid Paraffin','White Petroleum Jelly','Sodium Benzoate','Potassium Sorbate','EDTA Disodium'];
        for ($i = 1; $i <= 50; $i++) {
            $id = 'ct-demo-product-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            if (chemtechFind($products, $id)) continue;
            $unit = $i % 4 === 0 ? 'litre' : 'kg';
            $products[] = ['id'=>$id,'name'=>$chemicalNames[$i-1],'category'=>['Solvents','Acids','Alkalis','Speciality Chemicals','Food & Pharma'][($i-1)%5],'sku'=>'CHM-'.str_pad((string)$i,3,'0',STR_PAD_LEFT),'hsn'=>(string)(2800+(($i*7)%100)),'unit'=>$unit,'gst_rate_bps'=>[500,1200,1800][($i-1)%3],'sale_price_paise'=>7000+($i*375),'stock_milli'=>(180+$i*4)*1000,'reorder_milli'=>(40+($i%5)*10)*1000,'created_at'=>$now->modify('-90 days')->format(DATE_ATOM)];
        }

        for ($i = 1; $i <= 50; $i++) {
            $id = 'ct-demo-enquiry-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            if (chemtechFind($enquiries, $id)) continue;
            $customerId = 'ct-demo-customer-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $productId = 'ct-demo-product-' . str_pad((string) ((($i * 7) % 50) + 1), 3, '0', STR_PAD_LEFT);
            $customer = chemtechFind($customers, $customerId);
            $product = chemtechFind($products, $productId);
            $enquiries[] = ['id'=>$id,'enquiry_number'=>'ENQ-'.str_pad((string)$i,4,'0',STR_PAD_LEFT),'customer_id'=>$customerId,'customer_name'=>$customer['name']??'Demo customer','product_id'=>$productId,'product_name'=>$product['name']??'Chemical','quantity_milli'=>(5+(($i*3)%40))*1000,'status'=>['new','contacted','quoted','converted','follow_up'][($i-1)%5],'source'=>['Website','Phone','Referral','IndiaMART','Email'][($i-1)%5],'follow_up_date'=>$now->modify('+'.($i%14).' days')->format('Y-m-d'),'created_at'=>$now->modify('-'.(55-$i).' days')->format(DATE_ATOM)];
        }

        for ($i = 1; $i <= 60; $i++) {
            $id = 'ct-demo-order-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            if (chemtechFind($orders, $id)) continue;
            $customerIndex = (($i - 1) % 50) + 1;
            $productIndex = (($i * 11 - 1) % 50) + 1;
            $customer = chemtechFind($customers, 'ct-demo-customer-' . str_pad((string) $customerIndex, 3, '0', STR_PAD_LEFT));
            $productId = 'ct-demo-product-' . str_pad((string) $productIndex, 3, '0', STR_PAD_LEFT);
            $productPosition = null;
            foreach ($products as $position=>$candidate) if (($candidate['id']??'')===$productId) { $productPosition=$position; break; }
            if (!$customer || $productPosition===null) continue;
            $quantityMilli = (5 + (($i * 3) % 16)) * 1000;
            $unitPrice = (int) $products[$productPosition]['sale_price_paise'];
            $totals = chemtechOrderTotals($products[$productPosition],$quantityMilli,$unitPrice,(string)$settings['state_code'],(string)$customer['state_code']);
            $products[$productPosition]['stock_milli'] = max(0,(int)$products[$productPosition]['stock_milli']-$quantityMilli);
            $created = $now->modify('-'.(61-$i).' days');
            $orders[] = array_merge($totals,['id'=>$id,'order_number'=>'SO-DEMO-'.str_pad((string)$i,4,'0',STR_PAD_LEFT),'customer_id'=>$customer['id'],'customer_name'=>$customer['name'],'product_id'=>$products[$productPosition]['id'],'product_name'=>$products[$productPosition]['name'],'hsn'=>$products[$productPosition]['hsn'],'unit'=>$products[$productPosition]['unit'],'gst_rate_bps'=>$products[$productPosition]['gst_rate_bps'],'quantity_milli'=>$quantityMilli,'unit_price_paise'=>$unitPrice,'status'=>'confirmed','billing_status'=>$i<=50?'billed':'unbilled','invoice_id'=>$i<=50?'ct-demo-invoice-'.str_pad((string)$i,3,'0',STR_PAD_LEFT):'','created_at'=>$created->format(DATE_ATOM)]);
        }

        $demoOrders = [];
        foreach ($orders as $order) if (str_starts_with((string)($order['id']??''),'ct-demo-order-')) $demoOrders[$order['id']]=$order;
        for ($i = 1; $i <= 50; $i++) {
            $id = 'ct-demo-invoice-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            if (chemtechFind($invoices, $id)) continue;
            $order = $demoOrders['ct-demo-order-'.str_pad((string)$i,3,'0',STR_PAD_LEFT)]??null;
            if (!$order) continue;
            $invoiceNumber = chemtechInvoiceNumber($invoices,(string)$settings['invoice_prefix']);
            $invoiceDate = (new DateTimeImmutable((string)$order['created_at']))->modify('+1 day');
            $paymentRatio = [1,.75,.5,.9][($i-1)%4];
            $paid = (int) round((int)$order['total_paise']*$paymentRatio);
            $invoice = ['id'=>$id,'invoice_number'=>$invoiceNumber,'sequence'=>(int)substr($invoiceNumber,strrpos($invoiceNumber,'/')+1),'financial_year'=>chemtechFinancialYear($invoiceDate),'customer_id'=>$order['customer_id'],'customer_name'=>$order['customer_name'],'order_ids'=>[$order['id']],'subtotal_paise'=>$order['subtotal_paise'],'cgst_paise'=>$order['cgst_paise'],'sgst_paise'=>$order['sgst_paise'],'igst_paise'=>$order['igst_paise'],'total_paise'=>$order['total_paise'],'paid_paise'=>$paid,'status'=>$paid===(int)$order['total_paise']?'paid':'part_paid','invoice_date'=>$invoiceDate->format('Y-m-d'),'due_date'=>$invoiceDate->modify('+30 days')->format('Y-m-d'),'created_at'=>$invoiceDate->format(DATE_ATOM)];
            $invoices[] = $invoice;
            $payments[] = ['id'=>'ct-demo-payment-'.str_pad((string)$i,3,'0',STR_PAD_LEFT),'invoice_id'=>$id,'invoice_number'=>$invoiceNumber,'customer_name'=>$order['customer_name'],'amount_paise'=>$paid,'method'=>['bank','upi','cheque'][($i-1)%3],'reference'=>'DEMO-'.str_pad((string)$i,5,'0',STR_PAD_LEFT),'payment_date'=>$invoiceDate->modify('+'.(3+($i%12)).' days')->format('Y-m-d'),'created_at'=>$invoiceDate->modify('+'.(3+($i%12)).' days')->format(DATE_ATOM)];
        }

        usort($customers,static fn(array $a,array $b):int=>strnatcasecmp((string)$a['name'],(string)$b['name']));
        usort($products,static fn(array $a,array $b):int=>strnatcasecmp((string)$a['name'],(string)$b['name']));
        usort($enquiries,static fn(array $a,array $b):int=>strcmp((string)$b['created_at'],(string)$a['created_at']));
        usort($orders,static fn(array $a,array $b):int=>strcmp((string)$b['created_at'],(string)$a['created_at']));
        usort($invoices,static fn(array $a,array $b):int=>strcmp((string)$b['invoice_date'],(string)$a['invoice_date']));
        usort($payments,static fn(array $a,array $b):int=>strcmp((string)$b['payment_date'],(string)$a['payment_date']));
        saveJsonFile(CHEMTECH_CUSTOMERS_FILE,$customers);
        saveJsonFile(CHEMTECH_PRODUCTS_FILE,$products);
        saveJsonFile(CHEMTECH_ENQUIRIES_FILE,$enquiries);
        saveJsonFile(CHEMTECH_ORDERS_FILE,$orders);
        saveJsonFile(CHEMTECH_INVOICES_FILE,$invoices);
        saveJsonFile(CHEMTECH_PAYMENTS_FILE,$payments);
        saveJsonFile(CHEMTECH_DEMO_MARKER_FILE,['seeded_at'=>gmdate(DATE_ATOM),'version'=>1]);
    } finally {
        flock($lock,LOCK_UN);
        fclose($lock);
    }
}

function chemtechOrderTotals(array $product, int $quantityMilli, int $unitPricePaise, string $companyStateCode, string $customerStateCode): array
{
    $subtotal = (int) round(($quantityMilli / 1000) * $unitPricePaise);
    $gstBps = (int) ($product['gst_rate_bps'] ?? 0);
    $tax = intdiv(($subtotal * $gstBps) + 5000, 10000);
    $interstate = $companyStateCode !== '' && $customerStateCode !== '' && $companyStateCode !== $customerStateCode;
    return [
        'subtotal_paise' => $subtotal,
        'cgst_paise' => $interstate ? 0 : intdiv($tax, 2),
        'sgst_paise' => $interstate ? 0 : $tax - intdiv($tax, 2),
        'igst_paise' => $interstate ? $tax : 0,
        'total_paise' => $subtotal + $tax,
        'tax_type' => $interstate ? 'IGST' : 'CGST + SGST',
    ];
}
