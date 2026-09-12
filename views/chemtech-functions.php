<?php
declare(strict_types=1);

const CHEMTECH_SETTINGS_FILE = __DIR__ . '/../storage/chemtech-settings.json';
const CHEMTECH_CUSTOMERS_FILE = __DIR__ . '/../storage/chemtech-customers.json';
const CHEMTECH_PRODUCTS_FILE = __DIR__ . '/../storage/chemtech-products.json';
const CHEMTECH_ORDERS_FILE = __DIR__ . '/../storage/chemtech-orders.json';
const CHEMTECH_INVOICES_FILE = __DIR__ . '/../storage/chemtech-invoices.json';
const CHEMTECH_PAYMENTS_FILE = __DIR__ . '/../storage/chemtech-payments.json';

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
