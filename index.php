<?php
declare(strict_types=1);

const OWNER_EMAIL = 'siddh.ghadi@gmail.com';
const AUTH_FILE = __DIR__ . '/storage/auth.php';
const LEADS_FILE = __DIR__ . '/storage/leads.json';
const ACTIVITIES_FILE = __DIR__ . '/storage/activities.json';
const CAMPAIGNS_FILE = __DIR__ . '/storage/campaigns.json';
const CONTENT_CALENDAR_FILE = __DIR__ . '/storage/content-calendar.json';
const RANGOLI_PRODUCTS_FILE = __DIR__ . '/storage/rangoli-products.json';
const RANGOLI_CONTACTS_FILE = __DIR__ . '/storage/rangoli-contacts.json';
const RANGOLI_ORDERS_FILE = __DIR__ . '/storage/rangoli-orders.json';
const SENDER_EMAIL = 'contact@techdecodes.com';
const OWNER_CALLING_NUMBER = '+91 7039636906';
require_once __DIR__ . '/views/rangoli-product-functions.php';
require_once __DIR__ . '/views/chemtech-functions.php';

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$cspNonce = base64_encode(random_bytes(18));
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'nonce-" . $cspNonce . "'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'");

if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function authConfig(): ?array
{
    if (!is_file(AUTH_FILE)) return null;
    $config = require AUTH_FILE;
    return is_array($config) ? $config : null;
}

function validCsrf(): bool
{
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', (string) $_POST['csrf']);
}

function redirectHome(): never
{
    header('Location: ./');
    exit;
}

function loadLeads(): array
{
    if (!is_file(LEADS_FILE)) return [];
    $decoded = json_decode((string) file_get_contents(LEADS_FILE), true);
    return is_array($decoded) ? $decoded : [];
}

function saveLeads(array $leads): void
{
    $json = json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $temporary = LEADS_FILE . '.tmp';
    if (file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, LEADS_FILE)) {
        @unlink($temporary);
        throw new RuntimeException('Could not save the leads file.');
    }
    @chmod(LEADS_FILE, 0640);
}

function loadJsonFile(string $file): array
{
    if (!is_file($file)) return [];
    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? $decoded : [];
}

function saveJsonFile(string $file, array $records): void
{
    $json = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $temporary = $file . '.tmp';
    if (file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $file)) {
        @unlink($temporary);
        throw new RuntimeException('Could not save your changes.');
    }
    @chmod($file, 0640);
}

function tdRedirect(string $page): never
{
    header('Location: ?business=techdecodes&page=' . rawurlencode($page));
    exit;
}

function rangoliRedirect(string $page): never
{
    header('Location: ?business=rangoli&page=' . rawurlencode($page));
    exit;
}

function findLeadIndex(array $leads, string $id): ?int
{
    foreach ($leads as $index => $lead) if (hash_equals((string) ($lead['id'] ?? ''), $id)) return $index;
    return null;
}

function normalizedLeadEmail(string $email): string
{
    $email = strtolower(trim($email));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function normalizedLeadPhone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($digits) < 7) return '';
    return strlen($digits) > 10 ? substr($digits, -10) : $digits;
}

function whatsappCandidatePhone(string $phone): string
{
    $raw = trim($phone);
    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    if ($digits === '') return '';

    if (strlen($digits) === 12 && str_starts_with($digits, '91')) $digits = substr($digits, 2);
    if (strlen($digits) === 11 && str_starts_with($digits, '0')) $digits = substr($digits, 1);
    if (strlen($digits) === 10) {
        return preg_match('/^[6-9]/', $digits) ? '+91' . $digits : '';
    }

    // Preserve clearly international numbers; availability on WhatsApp is checked when opened.
    if (str_starts_with($raw, '+') && strlen($digits) >= 8 && strlen($digits) <= 15 && !str_starts_with($digits, '91')) return '+' . $digits;
    return '';
}

function importCsvLeads(string $path, array $existing): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) throw new RuntimeException('Could not read that CSV file.');
    $header = fgetcsv($handle);
    if (!$header) { fclose($handle); throw new RuntimeException('The CSV file is empty.'); }
    $keys = array_map(static fn($value) => strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', (string) $value), '_')), $header);
    $aliases = [
        'business' => ['business_name','business','company','company_name','title','name','place_name'],
        'email' => ['email','email_address','mail'],
        'phone' => ['phone','phone_number','mobile','telephone','contact_number'],
        'website' => ['website','site','domain','url'],
        'address' => ['address','full_address','location'],
        'category' => ['category','type','business_category','industry'],
    ];
    $columns = [];
    foreach ($aliases as $field => $names) {
        foreach ($names as $name) {
            $position = array_search($name, $keys, true);
            if ($position !== false) { $columns[$field] = $position; break; }
        }
    }
    if (!isset($columns['business'])) { fclose($handle); throw new RuntimeException('Your CSV needs a Name, Business, Company, or Title column.'); }
    $seenEmails = []; $seenPhones = [];
    foreach ($existing as $lead) {
        $knownEmail = normalizedLeadEmail((string) ($lead['email'] ?? ''));
        $knownPhone = normalizedLeadPhone((string) ($lead['phone'] ?? ''));
        if ($knownEmail !== '') $seenEmails[$knownEmail] = true;
        if ($knownPhone !== '') $seenPhones[$knownPhone] = true;
    }
    $added = 0; $duplicates = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $value = static fn(string $field): string => trim((string) ($row[$columns[$field] ?? -1] ?? ''));
        $business = $value('business');
        if ($business === '') continue;
        $email = strtolower($value('email'));
        $phone = whatsappCandidatePhone($value('phone'));
        $emailKey = normalizedLeadEmail($email);
        $phoneKey = normalizedLeadPhone($phone);
        if (($emailKey !== '' && isset($seenEmails[$emailKey])) || ($phoneKey !== '' && isset($seenPhones[$phoneKey]))) { $duplicates++; continue; }
        $createdAt = gmdate('c');
        $existing[] = ['id' => bin2hex(random_bytes(8)), 'business' => $business, 'email' => $email, 'phone' => $phone, 'website' => $value('website'), 'address' => $value('address'), 'category' => $value('category'), 'status' => 'new', 'created_at' => $createdAt, 'status_changed_at' => $createdAt];
        if ($emailKey !== '') $seenEmails[$emailKey] = true;
        if ($phoneKey !== '') $seenPhones[$phoneKey] = true;
        $added++;
    }
    fclose($handle);
    return [$existing, $added, $duplicates];
}

$auth = authConfig();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrf()) {
        http_response_code(400);
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (isset($_POST['setup'])) {
        if ($auth !== null) {
            $error = 'Owner access is already configured.';
        } else {
            $password = (string) ($_POST['password'] ?? '');
            $confirmation = (string) ($_POST['password_confirmation'] ?? '');
            if (strlen($password) < 10) {
                $error = 'Use at least 10 characters for your password.';
            } elseif ($password !== $confirmation) {
                $error = 'The passwords do not match.';
            } else {
                $directory = dirname(AUTH_FILE);
                if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
                    $error = 'Could not create secure storage. Please contact support.';
                } else {
                    $data = "<?php\nif (!defined('OWNER_EMAIL')) { http_response_code(404); exit; }\nreturn " . var_export([
                        'email' => OWNER_EMAIL,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'created_at' => gmdate('c'),
                    ], true) . ";\n";
                    $temporary = AUTH_FILE . '.tmp';
                    if (file_put_contents($temporary, $data, LOCK_EX) === false || !rename($temporary, AUTH_FILE)) {
                        @unlink($temporary);
                        $error = 'Could not save owner access. Please contact support.';
                    } else {
                        @chmod(AUTH_FILE, 0640);
                        session_regenerate_id(true);
                        $_SESSION['authenticated'] = true;
                        redirectHome();
                    }
                }
            }
        }
    } elseif (isset($_POST['login'])) {
        $attempts = (int) ($_SESSION['login_attempts'] ?? 0);
        $lockedUntil = (int) ($_SESSION['locked_until'] ?? 0);
        if ($lockedUntil > time()) {
            $error = 'Too many attempts. Try again in a few minutes.';
        } else {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $valid = $auth !== null && hash_equals(strtolower((string) $auth['email']), $email)
                && password_verify($password, (string) $auth['password_hash']);
            if ($valid) {
                session_regenerate_id(true);
                $_SESSION['authenticated'] = true;
                $_SESSION['login_attempts'] = 0;
                unset($_SESSION['locked_until']);
                redirectHome();
            }
            $attempts++;
            $_SESSION['login_attempts'] = $attempts;
            if ($attempts >= 5) {
                $_SESSION['locked_until'] = time() + 300;
                $_SESSION['login_attempts'] = 0;
            }
            $error = 'Email or password is incorrect.';
        }
    } elseif (isset($_POST['logout'])) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], '', $params['secure'], $params['httponly']);
        }
        session_destroy();
        redirectHome();
    }
}

$isAuthenticated = ($_SESSION['authenticated'] ?? false) === true;
$isSetup = $auth !== null;
$requestedBusiness = (string) ($_GET['business'] ?? '');
$view = $isAuthenticated && in_array($requestedBusiness, ['techdecodes','itedvantage','rangoli','chemtech'], true) ? $requestedBusiness : 'home';
$allowedPages = ['dashboard','leads','payments','revenue','email','activity','calendar','settings'];
$tdPage = in_array((string) ($_GET['page'] ?? 'dashboard'), $allowedPages, true) ? (string) ($_GET['page'] ?? 'dashboard') : 'dashboard';
$notice = '';
if ($isAuthenticated && $view === 'techdecodes' && $_SERVER['REQUEST_METHOD'] === 'POST' && validCsrf()) {
    try {
        $leadsForAction = loadLeads();
        if (isset($_POST['export_techdecodes'])) {
            $backup = [
                'format' => 'ray-techdecodes-backup', 'version' => 1,
                'exported_at' => gmdate('c'), 'lead_count' => count($leadsForAction),
                'leads' => $leadsForAction,
                'activities' => loadJsonFile(ACTIVITIES_FILE),
                'campaigns' => loadJsonFile(CAMPAIGNS_FILE),
                'calendar' => array_values(array_filter(loadJsonFile(CONTENT_CALENDAR_FILE), static fn(array $item): bool => ($item['business'] ?? '') === 'techdecodes')),
            ];
            $payload = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="techdecodes-backup-' . gmdate('Y-m-d-His') . '.json"');
            header('Cache-Control: no-store, private');
            echo $payload;
            exit;
        }
        if (isset($_POST['import_leads'])) {
            $file = $_FILES['lead_file'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 10 * 1024 * 1024) throw new RuntimeException('Choose a CSV file smaller than 10 MB.');
            if (strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'csv') throw new RuntimeException('Export your sheet as CSV first.');
            [$leadsForAction, $added, $duplicates] = importCsvLeads((string) $file['tmp_name'], $leadsForAction);
            saveLeads($leadsForAction);
            $_SESSION['notice'] = $added . ' new lead' . ($added === 1 ? '' : 's') . ' imported' . ($duplicates ? '; ' . $duplicates . ' duplicate' . ($duplicates === 1 ? '' : 's') . ' skipped by phone/email.' : '.');
            tdRedirect('leads');
        }
        if (isset($_POST['update_status'])) {
            $ids = array_map('strval', (array) ($_POST['lead_ids'] ?? []));
            $status = (string) ($_POST['status'] ?? '');
            if (!$ids || !in_array($status, ['new','pending','contacted','closed','lost'], true)) throw new RuntimeException('Select leads and a valid status.');
            $updated = 0;
            foreach ($leadsForAction as &$lead) if (in_array((string) ($lead['id'] ?? ''), $ids, true)) { if (($lead['status'] ?? 'new') !== $status) $lead['status_changed_at'] = gmdate('c'); $lead['status'] = $status; $lead['updated_at'] = gmdate('c'); $updated++; }
            unset($lead);
            saveLeads($leadsForAction);
            $_SESSION['notice'] = $updated . ' lead status updated.';
            tdRedirect('leads');
        }
        if (isset($_POST['delete_leads'])) {
            $ids = array_values(array_filter(array_map('strval', (array) ($_POST['lead_ids'] ?? []))));
            if (!$ids) throw new RuntimeException('Select at least one lead to delete.');
            if (($_POST['confirm_delete'] ?? '') !== 'yes') throw new RuntimeException('Tick the delete confirmation before removing leads.');
            $before = count($leadsForAction);
            $leadsForAction = array_values(array_filter($leadsForAction, static fn(array $lead): bool => !in_array((string) ($lead['id'] ?? ''), $ids, true)));
            saveLeads($leadsForAction);
            $activitiesForAction = array_values(array_filter(loadJsonFile(ACTIVITIES_FILE), static fn(array $activity): bool => !in_array((string) ($activity['lead_id'] ?? ''), $ids, true)));
            saveJsonFile(ACTIVITIES_FILE, $activitiesForAction);
            $_SESSION['notice'] = ($before - count($leadsForAction)) . ' lead' . (($before - count($leadsForAction)) === 1 ? '' : 's') . ' deleted.';
            tdRedirect('leads');
        }
        if (isset($_POST['save_payment'])) {
            $leadId = (string) ($_POST['lead_id'] ?? '');
            $index = findLeadIndex($leadsForAction, $leadId);
            if ($index === null) throw new RuntimeException('Select a valid lead.');
            $total = max(0, (float) ($_POST['total_value'] ?? 0));
            $received = min($total, max(0, (float) ($_POST['amount_received'] ?? 0)));
            $leadsForAction[$index]['total_value'] = $total;
            $leadsForAction[$index]['amount_received'] = $received;
            if ($total > 0) $leadsForAction[$index]['status'] = 'closed';
            $leadsForAction[$index]['updated_at'] = gmdate('c');
            saveLeads($leadsForAction);
            $_SESSION['notice'] = 'Payment updated for ' . $leadsForAction[$index]['business'] . '.';
            tdRedirect('payments');
        }
        if (isset($_POST['add_activity'])) {
            $leadId = (string) ($_POST['lead_id'] ?? '');
            $index = findLeadIndex($leadsForAction, $leadId);
            $note = trim((string) ($_POST['note'] ?? ''));
            if ($index === null || $note === '') throw new RuntimeException('Select a lead and add a note.');
            $activities = loadJsonFile(ACTIVITIES_FILE);
            array_unshift($activities, ['id' => bin2hex(random_bytes(8)), 'lead_id' => $leadId, 'business' => $leadsForAction[$index]['business'], 'note' => substr($note, 0, 500), 'due_date' => (string) ($_POST['due_date'] ?? ''), 'created_at' => gmdate('c')]);
            saveJsonFile(ACTIVITIES_FILE, array_slice($activities, 0, 1000));
            $_SESSION['notice'] = 'Activity added.';
            tdRedirect('activity');
        }
        if (isset($_POST['send_campaign'])) {
            $recipientCategory = trim((string) ($_POST['recipient_category'] ?? ''));
            $subject = trim(str_replace(["\r","\n"], '', (string) ($_POST['subject'] ?? '')));
            $message = trim((string) ($_POST['message'] ?? ''));
            if ($recipientCategory === '' || $subject === '' || $message === '') throw new RuntimeException('Choose a category and complete the subject and message.');
            $recipients = array_values(array_filter($leadsForAction, static function (array $lead) use ($recipientCategory): bool {
                $category = trim((string) ($lead['category'] ?? '')) ?: 'Uncategorized';
                return ($recipientCategory === '__all__' || strcasecmp($category, $recipientCategory) === 0) && filter_var($lead['email'] ?? '', FILTER_VALIDATE_EMAIL);
            }));
            if (!$recipients) throw new RuntimeException('No valid email addresses found in that category.');
            if (count($recipients) > 25) throw new RuntimeException('This category has more than 25 email leads. Filter it into a smaller category before sending.');
            $sent = 0; $failed = 0;
            $headers = ['From: TechDecodes <' . SENDER_EMAIL . '>', 'Reply-To: ' . SENDER_EMAIL, 'Content-Type: text/plain; charset=UTF-8', 'X-Mailer: Ray CRM'];
            foreach ($recipients as $lead) {
                $body = str_replace(['{{business}}','{{email}}'], [(string) $lead['business'], (string) $lead['email']], $message) . "\n\n— TechDecodes\n" . SENDER_EMAIL;
                if (mail((string) $lead['email'], $subject, $body, implode("\r\n", $headers))) $sent++; else $failed++;
            }
            $campaigns = loadJsonFile(CAMPAIGNS_FILE);
            array_unshift($campaigns, ['id' => bin2hex(random_bytes(8)), 'subject' => $subject, 'category' => $recipientCategory === '__all__' ? 'All categories' : $recipientCategory, 'sent' => $sent, 'failed' => $failed, 'created_at' => gmdate('c'), 'sender' => SENDER_EMAIL]);
            saveJsonFile(CAMPAIGNS_FILE, array_slice($campaigns, 0, 250));
            $_SESSION['notice'] = $sent . ' email' . ($sent === 1 ? '' : 's') . ' sent' . ($failed ? '; ' . $failed . ' failed.' : '.');
            tdRedirect('email');
        }
    } catch (Throwable $actionError) { $notice = $actionError->getMessage(); }
}
if (isset($_SESSION['notice'])) { $notice = (string) $_SESSION['notice']; unset($_SESSION['notice']); }
$rangoliPage = in_array((string) ($_GET['page'] ?? 'dashboard'), ['dashboard','products','contacts','orders','payments'], true) ? (string) ($_GET['page'] ?? 'dashboard') : 'dashboard';
if ($isAuthenticated && $view === 'rangoli' && $_SERVER['REQUEST_METHOD'] === 'POST' && !validCsrf()) $notice = 'Your session expired. Please reload the page and try again.';
if ($isAuthenticated && $view === 'rangoli' && $_SERVER['REQUEST_METHOD'] === 'POST' && validCsrf()) {
    $rangoliLock = null;
    try {
        $rangoliLock = fopen(__DIR__ . '/storage/rangoli-write.lock', 'c');
        if (!$rangoliLock || !flock($rangoliLock, LOCK_EX)) throw new RuntimeException('Products are busy. Please try again.');
        $productsForAction = loadJsonFile(RANGOLI_PRODUCTS_FILE);
        $contactsForAction = loadJsonFile(RANGOLI_CONTACTS_FILE);
        $ordersForAction = loadJsonFile(RANGOLI_ORDERS_FILE);
        if (isset($_POST['import_catalogue_products'])) {
            $import = rangoliImportProducts($productsForAction, (string) ($_POST['product_rows'] ?? ''));
            saveJsonFile(RANGOLI_PRODUCTS_FILE, $import['products']);
            $_SESSION['notice'] = $import['added'] . ' products added. ' . $import['skipped'] . ' existing products kept unchanged.';
            rangoliRedirect('products');
        }
        if (isset($_POST['save_catalogue_product'])) {
            $productsForAction = rangoliSaveProduct($productsForAction, $_POST);
            saveJsonFile(RANGOLI_PRODUCTS_FILE, $productsForAction);
            $_SESSION['notice'] = 'Product saved.';
            rangoliRedirect('products');
        }
        if (isset($_POST['add_product'])) {
            $name = trim((string) ($_POST['name'] ?? ''));
            $retail = max(0, (float) ($_POST['retail_price'] ?? 0));
            $dealer = max(0, (float) ($_POST['dealer_price'] ?? 0));
            if ($name === '' || $retail <= 0) throw new RuntimeException('Add a product name and retail price.');
            if ($dealer > $retail) throw new RuntimeException('Dealer price cannot be higher than retail price.');
            array_unshift($productsForAction, ['id'=>bin2hex(random_bytes(8)), 'name'=>substr($name,0,120), 'sku'=>substr(trim((string)($_POST['sku']??'')),0,50), 'cost_price'=>max(0,(float)($_POST['cost_price']??0)), 'retail_price'=>$retail, 'dealer_price'=>$dealer, 'stock'=>max(0,(int)($_POST['stock']??0)), 'low_stock'=>max(0,(int)($_POST['low_stock']??3)), 'created_at'=>gmdate('c')]);
            saveJsonFile(RANGOLI_PRODUCTS_FILE, $productsForAction); $_SESSION['notice']='Product added.'; rangoliRedirect('products');
        }
        if (isset($_POST['adjust_stock'])) {
            $productId=(string)($_POST['product_id']??''); $change=(int)($_POST['stock_change']??0); $found=false;
            foreach($productsForAction as &$product) if(hash_equals((string)$product['id'],$productId)){ $product['stock']=max(0,(int)$product['stock']+$change); $found=true; break; } unset($product);
            if(!$found || $change===0) throw new RuntimeException('Select a product and enter a stock change.');
            saveJsonFile(RANGOLI_PRODUCTS_FILE,$productsForAction); $_SESSION['notice']='Stock updated.'; rangoliRedirect('products');
        }
        if (isset($_POST['add_contact'])) {
            $name=trim((string)($_POST['name']??'')); $type=(string)($_POST['type']??'customer');
            if($name==='' || !in_array($type,['customer','dealer','partner'],true)) throw new RuntimeException('Add a valid name and contact type.');
            array_unshift($contactsForAction,['id'=>bin2hex(random_bytes(8)),'name'=>substr($name,0,120),'type'=>$type,'phone'=>whatsappCandidatePhone((string)($_POST['phone']??'')),'address'=>substr(trim((string)($_POST['address']??'')),0,300),'discount'=>min(100,max(0,(float)($_POST['discount']??0))),'created_at'=>gmdate('c')]);
            saveJsonFile(RANGOLI_CONTACTS_FILE,$contactsForAction); $_SESSION['notice']=ucfirst($type).' added.'; rangoliRedirect('contacts');
        }
        if (isset($_POST['create_order'])) {
            $productId=(string)($_POST['product_id']??''); $contactId=(string)($_POST['contact_id']??''); $quantity=max(1,(int)($_POST['quantity']??1));
            $productIndex=null; foreach($productsForAction as $i=>$product) if(hash_equals((string)$product['id'],$productId)){$productIndex=$i;break;}
            $contact=null; foreach($contactsForAction as $candidate) if(hash_equals((string)$candidate['id'],$contactId)){$contact=$candidate;break;}
            if($productIndex===null || $contact===null) throw new RuntimeException('Select a valid product and buyer.');
            if((int)$productsForAction[$productIndex]['stock']<$quantity) throw new RuntimeException('Not enough stock for this order.');
            $channel=($contact['type']??'customer')==='dealer'?'dealer':'retail';
            $basePrice=(float)$productsForAction[$productIndex][$channel==='dealer'?'dealer_price':'retail_price'];
            if($basePrice<=0) $basePrice=(float)$productsForAction[$productIndex]['retail_price'];
            $manualPrice=(float)($_POST['unit_price']??0); $unitPrice=$manualPrice>0?$manualPrice:$basePrice;
            if ($manualPrice <= 0 && $basePrice <= 0 && ($productsForAction[$productIndex]['retail_price'] ?? null) === null) throw new RuntimeException('Add a selling price or enter a unit price before creating this order.');
            $total=$unitPrice*$quantity; $paid=min($total,max(0,(float)($_POST['paid']??0)));
            $productsForAction[$productIndex]['stock']=(int)$productsForAction[$productIndex]['stock']-$quantity;
            array_unshift($ordersForAction,['id'=>bin2hex(random_bytes(8)),'contact_id'=>$contactId,'contact_name'=>$contact['name'],'contact_phone'=>$contact['phone']??'','channel'=>$channel,'product_id'=>$productId,'product_name'=>$productsForAction[$productIndex]['name'],'quantity'=>$quantity,'unit_price'=>$unitPrice,'total'=>$total,'paid'=>$paid,'status'=>'new','created_at'=>gmdate('c')]);
            saveJsonFile(RANGOLI_PRODUCTS_FILE,$productsForAction); saveJsonFile(RANGOLI_ORDERS_FILE,$ordersForAction); $_SESSION['notice']='Order created and stock reduced.'; rangoliRedirect('orders');
        }
        if (isset($_POST['update_order'])) {
            $orderId=(string)($_POST['order_id']??''); $status=(string)($_POST['status']??''); $found=false;
            foreach($ordersForAction as &$order) if(hash_equals((string)$order['id'],$orderId)){ if(!in_array($status,['new','preparing','ready','dispatched','delivered','cancelled'],true)) throw new RuntimeException('Choose a valid order status.'); $order['status']=$status; $order['paid']=min((float)$order['total'],max(0,(float)($_POST['paid']??$order['paid']))); $order['updated_at']=gmdate('c'); $found=true; break; } unset($order);
            if(!$found) throw new RuntimeException('Order not found.'); saveJsonFile(RANGOLI_ORDERS_FILE,$ordersForAction); $_SESSION['notice']='Order updated.'; rangoliRedirect('orders');
        }
    } catch(Throwable $rangoliError) { $notice=$rangoliError->getMessage(); }
    finally { if (is_resource($rangoliLock)) { flock($rangoliLock, LOCK_UN); fclose($rangoliLock); } }
}
$chemtechPages = ['dashboard','enquiries','customers','quotations','orders','invoices','payments','products','inventory','purchases','dispatch','reports','documents','settings'];
$chemtechPage = in_array((string) ($_GET['page'] ?? 'dashboard'), $chemtechPages, true) ? (string) ($_GET['page'] ?? 'dashboard') : 'dashboard';
if ($isAuthenticated && $view === 'chemtech') {
    try { chemtechSeed(); chemtechSeedDemoData(); } catch (Throwable $seedError) { $notice = 'ChemTech storage could not be prepared.'; }
}
if ($isAuthenticated && $view === 'chemtech' && $_SERVER['REQUEST_METHOD'] === 'POST' && !validCsrf()) $notice = 'Your session expired. Please reload and try again.';
if ($isAuthenticated && $view === 'chemtech' && $_SERVER['REQUEST_METHOD'] === 'POST' && validCsrf()) {
    $chemtechLock = null;
    try {
        $chemtechLock = fopen(__DIR__ . '/storage/chemtech-write.lock', 'c');
        if (!$chemtechLock || !flock($chemtechLock, LOCK_EX)) throw new RuntimeException('ChemTech data is busy. Please try again.');
        $ctSettingsForAction = chemtechSettings();
        $ctCustomersForAction = loadJsonFile(CHEMTECH_CUSTOMERS_FILE);
        $ctProductsForAction = loadJsonFile(CHEMTECH_PRODUCTS_FILE);
        $ctOrdersForAction = loadJsonFile(CHEMTECH_ORDERS_FILE);
        $ctInvoicesForAction = loadJsonFile(CHEMTECH_INVOICES_FILE);
        $ctPaymentsForAction = loadJsonFile(CHEMTECH_PAYMENTS_FILE);
        $ctEnquiriesForAction = loadJsonFile(CHEMTECH_ENQUIRIES_FILE);

        if (isset($_POST['save_chemtech_settings'])) {
            $companyName = chemtechText($_POST['company_name'] ?? '', 80);
            $shortName = strtoupper(preg_replace('/[^A-Z0-9]/i', '', chemtechText($_POST['short_name'] ?? '', 3)) ?? '');
            $stateCode = preg_replace('/\D+/', '', (string) ($_POST['state_code'] ?? '')) ?? '';
            if ($companyName === '' || $shortName === '') throw new RuntimeException('Add a company name and a 1–3 character icon.');
            if ($stateCode !== '' && strlen($stateCode) !== 2) throw new RuntimeException('GST state code must contain two digits.');
            foreach (array_keys(chemtechDefaults()) as $field) {
                if (array_key_exists($field, $_POST)) $ctSettingsForAction[$field] = chemtechText($_POST[$field], $field === 'address' ? 500 : 120);
            }
            $ctSettingsForAction['company_name'] = $companyName;
            $ctSettingsForAction['short_name'] = $shortName;
            $ctSettingsForAction['accent'] = '#faa61d';
            $ctSettingsForAction['state_code'] = $stateCode;
            saveJsonFile(CHEMTECH_SETTINGS_FILE, $ctSettingsForAction);
            $_SESSION['notice'] = 'Company branding and settings updated.';
            chemtechRedirect('settings');
        }

        if (isset($_POST['add_chemtech_customer'])) {
            $name = chemtechText($_POST['name'] ?? '', 120);
            $gstin = strtoupper(chemtechText($_POST['gstin'] ?? '', 15));
            $stateCode = preg_replace('/\D+/', '', (string) ($_POST['state_code'] ?? '')) ?? '';
            $cycle = (string) ($_POST['billing_cycle'] ?? 'per_order');
            if ($name === '' || ($stateCode !== '' && strlen($stateCode) !== 2)) throw new RuntimeException('Add a customer name and valid two-digit state code.');
            if (!in_array($cycle, ['per_order','weekly','fortnightly','monthly','custom'], true)) $cycle = 'per_order';
            if ($gstin !== '' && array_filter($ctCustomersForAction, static fn(array $customer): bool => strcasecmp((string) ($customer['gstin'] ?? ''), $gstin) === 0)) throw new RuntimeException('A customer with this GSTIN already exists.');
            array_unshift($ctCustomersForAction, [
                'id'=>bin2hex(random_bytes(8)), 'name'=>$name, 'contact_person'=>chemtechText($_POST['contact_person'] ?? '', 120),
                'phone'=>whatsappCandidatePhone((string) ($_POST['phone'] ?? '')), 'email'=>strtolower(chemtechText($_POST['email'] ?? '', 160)),
                'gstin'=>$gstin, 'state'=>chemtechText($_POST['state'] ?? '', 80), 'state_code'=>$stateCode,
                'billing_cycle'=>$cycle, 'credit_days'=>max(0, min(365, (int) ($_POST['credit_days'] ?? 0))),
                'address'=>chemtechText($_POST['address'] ?? '', 500), 'created_at'=>gmdate('c')
            ]);
            saveJsonFile(CHEMTECH_CUSTOMERS_FILE, $ctCustomersForAction);
            $_SESSION['notice'] = 'Customer added.';
            chemtechRedirect('customers');
        }

        if (isset($_POST['update_chemtech_customer'])) {
            $customerId = (string) ($_POST['customer_id'] ?? '');
            $customerIndex = null;
            foreach ($ctCustomersForAction as $index => $customer) {
                if (hash_equals((string) ($customer['id'] ?? ''), $customerId)) { $customerIndex = $index; break; }
            }
            if ($customerIndex === null) throw new RuntimeException('Customer not found.');
            $name = chemtechText($_POST['name'] ?? '', 120);
            $gstin = strtoupper(chemtechText($_POST['gstin'] ?? '', 15));
            $stateCode = preg_replace('/\D+/', '', (string) ($_POST['state_code'] ?? '')) ?? '';
            $cycle = (string) ($_POST['billing_cycle'] ?? 'per_order');
            if ($name === '' || ($stateCode !== '' && strlen($stateCode) !== 2)) throw new RuntimeException('Add a customer name and valid two-digit state code.');
            if (!in_array($cycle, ['per_order','weekly','fortnightly','monthly','custom'], true)) $cycle = 'per_order';
            if ($gstin !== '' && array_filter($ctCustomersForAction, static fn(array $customer): bool => (string) ($customer['id'] ?? '') !== $customerId && strcasecmp((string) ($customer['gstin'] ?? ''), $gstin) === 0)) throw new RuntimeException('Another customer already uses this GSTIN.');
            $ctCustomersForAction[$customerIndex] = array_replace($ctCustomersForAction[$customerIndex], [
                'name'=>$name, 'contact_person'=>chemtechText($_POST['contact_person'] ?? '', 120),
                'phone'=>whatsappCandidatePhone((string) ($_POST['phone'] ?? '')), 'email'=>strtolower(chemtechText($_POST['email'] ?? '', 160)),
                'gstin'=>$gstin, 'state'=>chemtechText($_POST['state'] ?? '', 80), 'state_code'=>$stateCode,
                'billing_cycle'=>$cycle, 'credit_days'=>max(0, min(365, (int) ($_POST['credit_days'] ?? 0))),
                'address'=>chemtechText($_POST['address'] ?? '', 500), 'updated_at'=>gmdate('c')
            ]);
            foreach ($ctOrdersForAction as &$order) if (($order['customer_id'] ?? '') === $customerId) $order['customer_name'] = $name;
            unset($order);
            foreach ($ctInvoicesForAction as &$invoice) if (($invoice['customer_id'] ?? '') === $customerId) $invoice['customer_name'] = $name;
            unset($invoice);
            foreach ($ctPaymentsForAction as &$payment) if (in_array((string) ($payment['invoice_id'] ?? ''), array_column(array_filter($ctInvoicesForAction, static fn(array $invoice): bool => ($invoice['customer_id'] ?? '') === $customerId), 'id'), true)) $payment['customer_name'] = $name;
            unset($payment);
            foreach ($ctEnquiriesForAction as &$enquiry) if (($enquiry['customer_id'] ?? '') === $customerId) $enquiry['customer_name'] = $name;
            unset($enquiry);
            saveJsonFile(CHEMTECH_CUSTOMERS_FILE, $ctCustomersForAction);
            saveJsonFile(CHEMTECH_ORDERS_FILE, $ctOrdersForAction);
            saveJsonFile(CHEMTECH_INVOICES_FILE, $ctInvoicesForAction);
            saveJsonFile(CHEMTECH_PAYMENTS_FILE, $ctPaymentsForAction);
            saveJsonFile(CHEMTECH_ENQUIRIES_FILE, $ctEnquiriesForAction);
            $_SESSION['notice'] = 'Customer details updated.';
            $returnPage = (string) ($_POST['return_page'] ?? '');
            if ($returnPage !== '' && in_array($returnPage, $chemtechPages, true)) {
                header('Location: ?business=chemtech&page=' . rawurlencode($returnPage) . '&open_customer=' . rawurlencode($customerId));
                exit;
            }
            header('Location: ?business=chemtech&page=customers&customer=' . rawurlencode($customerId));
            exit;
        }

        if (isset($_POST['add_chemtech_product'])) {
            $name = chemtechText($_POST['name'] ?? '', 120);
            $sku = strtoupper(chemtechText($_POST['sku'] ?? '', 50));
            if ($name === '' || $sku === '') throw new RuntimeException('Add a product name and SKU.');
            if (array_filter($ctProductsForAction, static fn(array $product): bool => strcasecmp((string) ($product['sku'] ?? ''), $sku) === 0)) throw new RuntimeException('This SKU already exists.');
            $gstRate = max(0, min(50, (float) ($_POST['gst_rate'] ?? 18)));
            array_unshift($ctProductsForAction, [
                'id'=>bin2hex(random_bytes(8)), 'name'=>$name, 'category'=>chemtechText($_POST['category'] ?? '', 80), 'sku'=>$sku,
                'hsn'=>chemtechText($_POST['hsn'] ?? '', 20), 'unit'=>chemtechText($_POST['unit'] ?? 'kg', 20),
                'gst_rate_bps'=>(int) round($gstRate * 100), 'sale_price_paise'=>max(0, chemtechMoneyToPaise($_POST['sale_price'] ?? 0)),
                'stock_milli'=>(int) round(max(0, (float) ($_POST['stock'] ?? 0)) * 1000),
                'reorder_milli'=>(int) round(max(0, (float) ($_POST['reorder_level'] ?? 0)) * 1000), 'created_at'=>gmdate('c')
            ]);
            saveJsonFile(CHEMTECH_PRODUCTS_FILE, $ctProductsForAction);
            $_SESSION['notice'] = 'Product added.';
            chemtechRedirect('products');
        }

        if (isset($_POST['create_chemtech_order'])) {
            $customerMode = (string) ($_POST['customer_mode'] ?? 'existing');
            $customerId = (string) ($_POST['customer_id'] ?? '');
            $customer = $customerMode === 'one_time' ? null : chemtechFind($ctCustomersForAction, $customerId);
            $pendingCustomer = null;
            if ($customerMode === 'one_time') {
                $oneTimeName = chemtechText($_POST['one_time_name'] ?? '', 120);
                $oneTimeStateCode = preg_replace('/\D+/', '', (string) ($_POST['one_time_state_code'] ?? '')) ?? '';
                if ($oneTimeName === '') throw new RuntimeException('Enter the one-time buyer name.');
                if ($oneTimeStateCode !== '' && strlen($oneTimeStateCode) !== 2) throw new RuntimeException('One-time buyer state code must contain two digits.');
                $customerId = bin2hex(random_bytes(8));
                $pendingCustomer = [
                    'id'=>$customerId, 'name'=>$oneTimeName, 'contact_person'=>'', 'phone'=>whatsappCandidatePhone((string) ($_POST['one_time_phone'] ?? '')),
                    'email'=>'', 'gstin'=>'', 'state'=>'', 'state_code'=>$oneTimeStateCode ?: (string) ($ctSettingsForAction['state_code'] ?? ''),
                    'billing_cycle'=>'per_order', 'credit_days'=>0, 'address'=>'', 'one_time'=>true, 'created_at'=>gmdate('c'),
                ];
                $customer = $pendingCustomer;
            }
            if (!$customer) throw new RuntimeException('Choose an existing customer or enter a one-time buyer.');

            $itemInputs = array_values(array_filter((array) ($_POST['items'] ?? []), 'is_array'));
            if (!$itemInputs && isset($_POST['product_id'])) $itemInputs[] = ['product_id'=>$_POST['product_id'], 'quantity'=>$_POST['quantity'] ?? 0, 'unit_price'=>$_POST['unit_price'] ?? 0];
            if (!$itemInputs) throw new RuntimeException('Add at least one product to the order.');
            $orderItems = [];
            $stockDemand = [];
            foreach ($itemInputs as $itemNumber=>$itemInput) {
                $productId = (string) ($itemInput['product_id'] ?? '');
                $productIndex = null;
                foreach ($ctProductsForAction as $index=>$product) if (hash_equals((string) ($product['id'] ?? ''), $productId)) { $productIndex=$index; break; }
                $quantityMilli = (int) round(max(0, (float) ($itemInput['quantity'] ?? 0)) * 1000);
                if ($productIndex === null || $quantityMilli <= 0) throw new RuntimeException('Choose a product and valid quantity for every order line.');
                $unitPrice = chemtechMoneyToPaise($itemInput['unit_price'] ?? 0);
                if ($unitPrice <= 0) $unitPrice = (int) ($ctProductsForAction[$productIndex]['sale_price_paise'] ?? 0);
                if ($unitPrice <= 0) throw new RuntimeException('Add a valid unit price for every product.');
                $stockDemand[$productIndex] = ($stockDemand[$productIndex] ?? 0) + $quantityMilli;
                $lineTotals = chemtechOrderTotals($ctProductsForAction[$productIndex], $quantityMilli, $unitPrice, (string) ($ctSettingsForAction['state_code'] ?? ''), (string) ($customer['state_code'] ?? ''));
                $orderItems[] = array_merge($lineTotals, [
                    'product_id'=>$productId, 'product_name'=>$ctProductsForAction[$productIndex]['name'], 'hsn'=>$ctProductsForAction[$productIndex]['hsn'],
                    'unit'=>$ctProductsForAction[$productIndex]['unit'], 'gst_rate_bps'=>$ctProductsForAction[$productIndex]['gst_rate_bps'],
                    'quantity_milli'=>$quantityMilli, 'unit_price_paise'=>$unitPrice,
                ]);
            }
            foreach ($stockDemand as $productIndex=>$quantityMilli) {
                if ((int) ($ctProductsForAction[$productIndex]['stock_milli'] ?? 0) < $quantityMilli) throw new RuntimeException('Not enough stock for ' . ($ctProductsForAction[$productIndex]['name'] ?? 'one selected product') . '.');
            }
            foreach ($stockDemand as $productIndex=>$quantityMilli) $ctProductsForAction[$productIndex]['stock_milli'] -= $quantityMilli;
            if ($pendingCustomer) array_unshift($ctCustomersForAction, $pendingCustomer);
            $totals = chemtechOrderAggregate($orderItems);
            $firstItem = $orderItems[0];
            $orderNumber = 'SO-' . date('ymd') . '-' . str_pad((string) (count($ctOrdersForAction) + 1), 3, '0', STR_PAD_LEFT);
            array_unshift($ctOrdersForAction, array_merge($totals, [
                'id'=>bin2hex(random_bytes(8)), 'order_number'=>$orderNumber, 'customer_id'=>$customerId, 'customer_name'=>$customer['name'],
                'product_id'=>count($orderItems)===1?$firstItem['product_id']:'', 'product_name'=>count($orderItems)===1?$firstItem['product_name']:count($orderItems).' products',
                'hsn'=>count($orderItems)===1?$firstItem['hsn']:'', 'unit'=>count($orderItems)===1?$firstItem['unit']:'mixed',
                'gst_rate_bps'=>count($orderItems)===1?$firstItem['gst_rate_bps']:0, 'quantity_milli'=>array_sum(array_column($orderItems,'quantity_milli')),
                'unit_price_paise'=>count($orderItems)===1?$firstItem['unit_price_paise']:0, 'items'=>$orderItems,
                'status'=>'confirmed', 'billing_status'=>'unbilled', 'invoice_id'=>'',
                'created_at'=>gmdate('c')
            ]));
            if ($pendingCustomer) saveJsonFile(CHEMTECH_CUSTOMERS_FILE, $ctCustomersForAction);
            saveJsonFile(CHEMTECH_PRODUCTS_FILE, $ctProductsForAction);
            saveJsonFile(CHEMTECH_ORDERS_FILE, $ctOrdersForAction);
            $_SESSION['notice'] = $orderNumber . ' created with ' . count($orderItems) . ' product' . (count($orderItems)===1?'':'s') . ' and stock updated.';
            chemtechRedirect('orders');
        }

        if (isset($_POST['update_chemtech_order_price'])) {
            $orderId = (string) ($_POST['order_id'] ?? '');
            $orderIndex = null;
            foreach ($ctOrdersForAction as $index => $order) {
                if (hash_equals((string) ($order['id'] ?? ''), $orderId)) { $orderIndex = $index; break; }
            }
            if ($orderIndex === null) throw new RuntimeException('Order not found.');
            if (($ctOrdersForAction[$orderIndex]['billing_status'] ?? 'unbilled') !== 'unbilled') throw new RuntimeException('This order is already invoiced. Its price is locked to protect the invoice.');
            $customer = chemtechFind($ctCustomersForAction, (string) ($ctOrdersForAction[$orderIndex]['customer_id'] ?? ''));
            if (!$customer) throw new RuntimeException('Customer not found.');
            $orderItems = chemtechOrderItems($ctOrdersForAction[$orderIndex]);
            $postedPrices = array_values((array) ($_POST['item_prices'] ?? []));
            if (!$postedPrices && isset($_POST['unit_price'])) $postedPrices[] = $_POST['unit_price'];
            if (count($postedPrices) !== count($orderItems)) throw new RuntimeException('Enter a price for every product in this order.');
            foreach ($orderItems as $itemIndex=>&$item) {
                $unitPrice = chemtechMoneyToPaise($postedPrices[$itemIndex] ?? 0);
                if ($unitPrice <= 0) throw new RuntimeException('Enter a valid unit price for every product.');
                $itemTotals = chemtechOrderTotals(['gst_rate_bps'=>(int) ($item['gst_rate_bps'] ?? 0)], (int) ($item['quantity_milli'] ?? 0), $unitPrice, (string) ($ctSettingsForAction['state_code'] ?? ''), (string) ($customer['state_code'] ?? ''));
                $item = array_replace($item, $itemTotals, ['unit_price_paise'=>$unitPrice]);
            }
            unset($item);
            $totals = chemtechOrderAggregate($orderItems);
            $firstItem = $orderItems[0];
            $ctOrdersForAction[$orderIndex] = array_replace($ctOrdersForAction[$orderIndex], $totals, [
                'items'=>$orderItems, 'product_id'=>count($orderItems)===1?$firstItem['product_id']:'',
                'product_name'=>count($orderItems)===1?$firstItem['product_name']:count($orderItems).' products',
                'hsn'=>count($orderItems)===1?$firstItem['hsn']:'', 'unit'=>count($orderItems)===1?$firstItem['unit']:'mixed',
                'gst_rate_bps'=>count($orderItems)===1?$firstItem['gst_rate_bps']:0,
                'unit_price_paise'=>count($orderItems)===1?$firstItem['unit_price_paise']:0,
                'updated_at'=>gmdate('c'),
            ]);
            saveJsonFile(CHEMTECH_ORDERS_FILE, $ctOrdersForAction);
            $_SESSION['notice'] = ($ctOrdersForAction[$orderIndex]['order_number'] ?? 'Order') . ' price and GST totals updated.';
            chemtechRedirect('orders');
        }

        if (isset($_POST['create_chemtech_invoice'])) {
            $selectedIds = array_values(array_filter(array_map('strval', (array) ($_POST['order_ids'] ?? []))));
            if (!$selectedIds) throw new RuntimeException('Select one or more unbilled orders.');
            $selected = array_values(array_filter($ctOrdersForAction, static fn(array $order): bool => in_array((string) ($order['id'] ?? ''), $selectedIds, true) && ($order['billing_status'] ?? 'unbilled') === 'unbilled'));
            if (count($selected) !== count($selectedIds)) throw new RuntimeException('One selected order was already billed. Reload and try again.');
            $customerIds = array_unique(array_column($selected, 'customer_id'));
            if (count($customerIds) !== 1) throw new RuntimeException('A consolidated invoice can contain orders from only one customer.');
            $customer = chemtechFind($ctCustomersForAction, (string) $customerIds[0]);
            if (!$customer) throw new RuntimeException('Customer not found.');
            $invoiceNumber = chemtechInvoiceNumber($ctInvoicesForAction, (string) $ctSettingsForAction['invoice_prefix']);
            $invoiceId = bin2hex(random_bytes(8));
            $invoiceDate = new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata'));
            $creditDays = (int) ($customer['credit_days'] ?? 0);
            $invoice = [
                'id'=>$invoiceId, 'invoice_number'=>$invoiceNumber, 'sequence'=>(int) substr($invoiceNumber, strrpos($invoiceNumber, '/') + 1),
                'financial_year'=>chemtechFinancialYear($invoiceDate), 'customer_id'=>$customer['id'], 'customer_name'=>$customer['name'],
                'customer_gstin'=>$customer['gstin'] ?? '', 'customer_state'=>$customer['state'] ?? '', 'customer_state_code'=>$customer['state_code'] ?? '',
                'customer_address'=>$customer['address'] ?? '', 'company_name'=>$ctSettingsForAction['legal_name'] ?: $ctSettingsForAction['company_name'],
                'company_gstin'=>$ctSettingsForAction['gstin'] ?? '', 'company_state'=>$ctSettingsForAction['state'] ?? '',
                'company_state_code'=>$ctSettingsForAction['state_code'] ?? '', 'company_address'=>$ctSettingsForAction['address'] ?? '',
                'order_ids'=>$selectedIds, 'subtotal_paise'=>array_sum(array_column($selected, 'subtotal_paise')),
                'cgst_paise'=>array_sum(array_column($selected, 'cgst_paise')), 'sgst_paise'=>array_sum(array_column($selected, 'sgst_paise')),
                'igst_paise'=>array_sum(array_column($selected, 'igst_paise')), 'total_paise'=>array_sum(array_column($selected, 'total_paise')),
                'paid_paise'=>0, 'status'=>'unpaid', 'invoice_date'=>$invoiceDate->format('Y-m-d'), 'due_date'=>$invoiceDate->modify('+' . $creditDays . ' days')->format('Y-m-d'),
                'created_at'=>gmdate('c')
            ];
            array_unshift($ctInvoicesForAction, $invoice);
            foreach ($ctOrdersForAction as &$order) if (in_array((string) ($order['id'] ?? ''), $selectedIds, true)) { $order['billing_status']='billed'; $order['invoice_id']=$invoiceId; }
            unset($order);
            saveJsonFile(CHEMTECH_INVOICES_FILE, $ctInvoicesForAction);
            saveJsonFile(CHEMTECH_ORDERS_FILE, $ctOrdersForAction);
            $_SESSION['notice'] = $invoiceNumber . ' created from ' . count($selectedIds) . ' order(s).';
            header('Location: ?business=chemtech&page=invoices&open_invoice=' . rawurlencode($invoiceId));
            exit;
        }

        if (isset($_POST['record_chemtech_payment'])) {
            $invoiceId = (string) ($_POST['invoice_id'] ?? '');
            $amount = max(0, chemtechMoneyToPaise($_POST['amount'] ?? 0));
            $invoiceIndex = null;
            foreach ($ctInvoicesForAction as $index => $invoice) if (hash_equals((string) ($invoice['id'] ?? ''), $invoiceId)) { $invoiceIndex = $index; break; }
            if ($invoiceIndex === null || $amount <= 0) throw new RuntimeException('Choose an invoice and enter a payment amount.');
            $outstanding = max(0, (int) $ctInvoicesForAction[$invoiceIndex]['total_paise'] - (int) $ctInvoicesForAction[$invoiceIndex]['paid_paise']);
            if ($amount > $outstanding) throw new RuntimeException('Payment cannot be higher than the invoice balance.');
            $ctInvoicesForAction[$invoiceIndex]['paid_paise'] += $amount;
            $ctInvoicesForAction[$invoiceIndex]['status'] = $amount === $outstanding ? 'paid' : 'part_paid';
            array_unshift($ctPaymentsForAction, ['id'=>bin2hex(random_bytes(8)), 'invoice_id'=>$invoiceId, 'invoice_number'=>$ctInvoicesForAction[$invoiceIndex]['invoice_number'], 'customer_name'=>$ctInvoicesForAction[$invoiceIndex]['customer_name'], 'amount_paise'=>$amount, 'method'=>chemtechText($_POST['method'] ?? 'bank', 30), 'reference'=>chemtechText($_POST['reference'] ?? '', 80), 'payment_date'=>(string) ($_POST['payment_date'] ?? date('Y-m-d')), 'created_at'=>gmdate('c')]);
            saveJsonFile(CHEMTECH_INVOICES_FILE, $ctInvoicesForAction);
            saveJsonFile(CHEMTECH_PAYMENTS_FILE, $ctPaymentsForAction);
            $_SESSION['notice'] = 'Payment recorded.';
            chemtechRedirect('payments');
        }
    } catch (Throwable $chemtechError) { $notice = $chemtechError->getMessage(); }
    finally { if (is_resource($chemtechLock)) { flock($chemtechLock, LOCK_UN); fclose($chemtechLock); } }
}
$calendarItems = $isAuthenticated ? loadJsonFile(CONTENT_CALENDAR_FILE) : [];
if ($isAuthenticated && in_array($view, ['techdecodes','itedvantage'], true) && $_SERVER['REQUEST_METHOD'] === 'POST' && validCsrf() && isset($_POST['add_calendar_item'])) {
    try {
        $calendarDate = (string) ($_POST['calendar_date'] ?? '');
        $calendarTitle = trim((string) ($_POST['calendar_title'] ?? ''));
        $calendarChannel = trim((string) ($_POST['calendar_channel'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $calendarDate) || $calendarTitle === '') throw new RuntimeException('Add a valid date and content title.');
        array_unshift($calendarItems, ['id'=>bin2hex(random_bytes(8)), 'business'=>$view, 'date'=>$calendarDate, 'title'=>substr($calendarTitle,0,180), 'channel'=>substr($calendarChannel,0,60), 'status'=>'planned', 'created_at'=>gmdate('c')]);
        saveJsonFile(CONTENT_CALENDAR_FILE, array_slice($calendarItems,0,2000));
        $_SESSION['notice'] = 'Content added to the calendar.';
        $month = substr($calendarDate, 0, 7);
        header('Location: ?business=' . rawurlencode($view) . '&page=calendar&month=' . rawurlencode($month)); exit;
    } catch (Throwable $calendarError) { $notice = $calendarError->getMessage(); }
}
$leads = $isAuthenticated ? loadLeads() : [];
$cleanedPhoneCount = 0;
if ($isAuthenticated && $leads) {
    foreach ($leads as &$leadToClean) {
        $originalPhone = trim((string) ($leadToClean['phone'] ?? ''));
        $cleanPhone = whatsappCandidatePhone($originalPhone);
        if ($originalPhone !== $cleanPhone) { $leadToClean['phone'] = $cleanPhone; $cleanedPhoneCount++; }
    }
    unset($leadToClean);
    if ($cleanedPhoneCount > 0) {
        try {
            saveLeads($leads);
            if ($notice === '') $notice = $cleanedPhoneCount . ' phone number' . ($cleanedPhoneCount === 1 ? '' : 's') . ' cleaned; obvious landlines were left blank.';
        } catch (Throwable $cleanupError) {
            if ($notice === '') $notice = 'Phone cleanup could not be saved yet.';
        }
    }
}
$leadCount = count($leads);
$statusCounts = array_fill_keys(['new','pending','contacted','closed','lost'], 0);
$totalRevenue = 0.0; $receivedRevenue = 0.0;
foreach ($leads as &$lead) {
    $lead += ['email'=>'','phone'=>'','website'=>'','address'=>'','category'=>'','status'=>'new','total_value'=>0,'amount_received'=>0];
    if (isset($statusCounts[$lead['status']])) $statusCounts[$lead['status']]++;
    $totalRevenue += (float) $lead['total_value']; $receivedRevenue += (float) $lead['amount_received'];
}
unset($lead);
$pendingRevenue = max(0, $totalRevenue - $receivedRevenue);
$activities = $isAuthenticated ? loadJsonFile(ACTIVITIES_FILE) : [];
$today = date('Y-m-d');
$overdueActivities = array_values(array_filter($activities, static fn(array $activity): bool => ($activity['due_date'] ?? '') !== '' && (string) $activity['due_date'] < date('Y-m-d')));
$todayActivities = array_values(array_filter($activities, static fn(array $activity): bool => (string) ($activity['due_date'] ?? '') === date('Y-m-d')));
$upcomingActivities = array_values(array_filter($activities, static fn(array $activity): bool => (string) ($activity['due_date'] ?? '') > date('Y-m-d')));
$pendingFollowups = count($overdueActivities) + count($todayActivities);
$campaigns = $isAuthenticated ? loadJsonFile(CAMPAIGNS_FILE) : [];
$rangoliProducts = $isAuthenticated ? loadJsonFile(RANGOLI_PRODUCTS_FILE) : [];
$rangoliContacts = $isAuthenticated ? loadJsonFile(RANGOLI_CONTACTS_FILE) : [];
$rangoliOrders = $isAuthenticated ? loadJsonFile(RANGOLI_ORDERS_FILE) : [];
$chemtechSettings = $isAuthenticated ? chemtechSettings() : chemtechDefaults();
$chemtechCustomers = $isAuthenticated && $view === 'chemtech' ? loadJsonFile(CHEMTECH_CUSTOMERS_FILE) : [];
$chemtechProducts = $isAuthenticated && $view === 'chemtech' ? loadJsonFile(CHEMTECH_PRODUCTS_FILE) : [];
$chemtechOrders = $isAuthenticated && $view === 'chemtech' ? loadJsonFile(CHEMTECH_ORDERS_FILE) : [];
$chemtechInvoices = $isAuthenticated && $view === 'chemtech' ? loadJsonFile(CHEMTECH_INVOICES_FILE) : [];
$chemtechPayments = $isAuthenticated && $view === 'chemtech' ? loadJsonFile(CHEMTECH_PAYMENTS_FILE) : [];
$chemtechEnquiries = $isAuthenticated && $view === 'chemtech' ? loadJsonFile(CHEMTECH_ENQUIRIES_FILE) : [];
$categoryCounts = [];
foreach ($leads as $lead) {
    $category = trim((string) ($lead['category'] ?? '')) ?: 'Uncategorized';
    $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
}
uksort($categoryCounts, 'strnatcasecmp');
$categoryFilter = trim((string) ($_GET['category'] ?? ''));
$filteredLeads = $categoryFilter === '' ? $leads : array_values(array_filter($leads, static fn(array $lead): bool => strcasecmp(trim((string) ($lead['category'] ?? '')) ?: 'Uncategorized', $categoryFilter) === 0));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#faa61d">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/assets/ray-192.png">
    <script defer src="/assets/install.js?v=1"></script>
    <title><?= $isAuthenticated ? 'Dashboard' : ($isSetup ? 'Sign in' : 'Set up access') ?> · Ray CRM</title>
    <link rel="stylesheet" href="assets/styles.css?v=<?= (int) filemtime(__DIR__ . '/assets/styles.css') ?>">
    <?php if($isAuthenticated): $safeChemtechAccent = '#faa61d'; ?>
    <style nonce="<?= htmlspecialchars($cspNonce) ?>">.ct-workspace{--ct-accent:<?= htmlspecialchars($safeChemtechAccent) ?>}.business-card.chem{--chemtech-accent:<?= htmlspecialchars($safeChemtechAccent) ?>}</style>
    <?php endif; ?>
    <script defer src="assets/app.js?v=<?= (int) filemtime(__DIR__ . '/assets/app.js') ?>"></script>
    <?php if($isAuthenticated && $view === 'techdecodes'): ?>
    <link rel="stylesheet" href="assets/chemtech-mobile.css?v=<?= (int) filemtime(__DIR__ . '/assets/chemtech-mobile.css') ?>">
    <link rel="stylesheet" href="assets/techdecodes-layout.css?v=<?= (int) filemtime(__DIR__ . '/assets/techdecodes-layout.css') ?>">
    <script defer src="assets/chemtech-mobile.js?v=<?= (int) filemtime(__DIR__ . '/assets/chemtech-mobile.js') ?>"></script>
    <?php endif; ?>
    <?php if($isAuthenticated && $view === 'rangoli' && $rangoliPage === 'products'): ?>
    <link rel="stylesheet" href="assets/rangoli-products.css?v=<?= (int) filemtime(__DIR__ . '/assets/rangoli-products.css') ?>">
    <script defer src="assets/pdf-lib.min.js"></script>
    <script defer src="assets/rangoli-pdf.js?v=<?= (int) filemtime(__DIR__ . '/assets/rangoli-pdf.js') ?>"></script>
    <script defer src="assets/rangoli-products.js?v=<?= (int) filemtime(__DIR__ . '/assets/rangoli-products.js') ?>"></script>
    <?php endif; ?>
    <?php if($isAuthenticated && $view === 'chemtech'): ?>
    <link rel="stylesheet" href="assets/chemtech-mobile.css?v=<?= (int) filemtime(__DIR__ . '/assets/chemtech-mobile.css') ?>">
    <script defer src="assets/chemtech-mobile.js?v=<?= (int) filemtime(__DIR__ . '/assets/chemtech-mobile.js') ?>"></script>
    <script defer src="assets/pdf-lib.min.js"></script>
    <script defer src="assets/chemtech-invoice.js?v=<?= (int) filemtime(__DIR__ . '/assets/chemtech-invoice.js') ?>"></script>
    <?php endif; ?>
</head>
<body class="<?= $isAuthenticated ? 'app-page' : 'auth-page' ?>">
<button id="ray-install" type="button" hidden>Install RAY CRM</button>
<?php if (!$isAuthenticated): ?>
    <main class="auth-shell">
        <section class="brand-panel">
            <div class="brand-mark">R</div>
            <div><span class="eyebrow">PRIVATE BUSINESS OS</span><h1>Ray CRM</h1><p>One calm place to run every part of your business.</p></div>
        </section>
        <section class="auth-card">
            <span class="eyebrow"><?= $isSetup ? 'WELCOME BACK' : 'FIRST-TIME SETUP' ?></span>
            <h2><?= $isSetup ? 'Sign in to continue' : 'Create your password' ?></h2>
            <p class="muted"><?= $isSetup ? 'Use your owner account to open the dashboard.' : 'This password stays securely on your Hostinger server.' ?></p>
            <?php if ($error !== ''): ?><div class="alert" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                <label>Email address</label>
                <input type="email" name="email" value="<?= OWNER_EMAIL ?>" readonly>
                <label for="password">Password</label>
                <input id="password" type="password" name="password" minlength="10" autocomplete="<?= $isSetup ? 'current-password' : 'new-password' ?>" required autofocus>
                <?php if (!$isSetup): ?>
                    <label for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" minlength="10" autocomplete="new-password" required>
                    <button type="submit" name="setup" value="1">Create secure access</button>
                <?php else: ?>
                    <button type="submit" name="login" value="1">Sign in</button>
                <?php endif; ?>
            </form>
        </section>
    </main>
<?php else: ?>
    <?php if ($view === 'techdecodes'): ?>
        <?php require __DIR__ . '/views/techdecodes.php'; ?>
        <?php if (false): ?>
        <div class="workspace-shell">
            <aside class="side-nav">
                <a class="side-logo" href="./" aria-label="Ray CRM home">R</a>
                <nav aria-label="TechDecodes navigation">
                    <a class="active" href="?business=techdecodes" title="Dashboard">⌂</a>
                    <a href="#leads" title="Leads">◎</a>
                    <a href="#payments" title="Payments">₹</a>
                    <a href="#activity" title="Activity">↗</a>
                </nav>
                <a class="side-bottom" href="./" title="All businesses">⌘</a>
            </aside>
            <main class="td-dashboard">
                <header class="td-header">
                    <div><a class="back-link" href="./">← All businesses</a><span class="eyebrow">TECHDECODES</span><h1>Lead Command Center</h1><p>Scrape. Qualify. Contact. Close.</p></div>
                    <div class="header-actions">
                        <button class="ghost-button" type="button" disabled>Bulk email</button>
                        <button class="primary-button" type="button" disabled>＋ Import leads</button>
                    </div>
                </header>

                <section class="metric-grid" aria-label="Lead overview">
                    <article class="metric-card"><span>Total leads</span><strong><?= $leadCount ?></strong><small><?= $leadCount ? 'Saved securely' : 'Ready for your first import' ?></small></article>
                    <article class="metric-card purple"><span>Pending follow-up</span><strong>0</strong><small>No pending leads</small></article>
                    <article class="metric-card green"><span>Total revenue</span><strong>₹0</strong><small>From closed leads</small></article>
                    <article class="metric-card orange"><span>Pending payment</span><strong>₹0</strong><small>Nothing outstanding</small></article>
                </section>

                <section class="td-grid">
                    <article class="panel leads-panel" id="leads">
                        <div class="panel-heading"><div><span class="eyebrow">PIPELINE</span><h2>Leads</h2></div><span class="soft-badge"><?= $leadCount ?> saved</span></div>
                        <?php if ($notice !== ''): ?><div class="td-notice"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
                        <div class="stage-tabs"><span class="selected">All <b><?= $leadCount ?></b></span><span>New <b><?= $leadCount ?></b></span><span>Pending <b>0</b></span><span>Contacted <b>0</b></span><span>Closed <b>0</b></span></div>
                        <form class="lead-upload" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><input id="lead-file" type="file" name="lead_file" accept=".csv,text/csv" required><label class="small-button" for="lead-file">Choose CSV</label><button class="primary-button" type="submit" name="import_leads" value="1">Upload leads</button></form>
                        <?php if (!$leads): ?><div class="empty-state"><div class="upload-icon">⇧</div><h3>Import your first lead sheet</h3><p>Export Google Sheets or Excel as CSV, then upload it here.</p></div><?php else: ?>
                        <div class="lead-table-wrap"><table class="lead-table"><thead><tr><th>Business</th><th>Contact</th><th>Status</th><th>Call</th></tr></thead><tbody><?php foreach (array_reverse($leads) as $lead): ?><tr><td><strong><?= htmlspecialchars((string) $lead['business']) ?></strong><small><?= htmlspecialchars((string) ($lead['category'] ?: ($lead['website'] ?: '—'))) ?></small></td><td><?= htmlspecialchars((string) ($lead['email'] ?: 'No email')) ?><small><?= htmlspecialchars((string) ($lead['phone'] ?: 'No phone')) ?></small></td><td><span class="lead-status new">New</span></td><td><?= $lead['phone'] ? '<a class="call-link" href="tel:' . htmlspecialchars(preg_replace('/[^0-9+]/', '', (string) $lead['phone'])) . '">Call</a>' : '—' ?></td></tr><?php endforeach; ?></tbody></table></div>
                        <?php endif; ?>
                    </article>

                    <article class="panel quick-panel" id="activity">
                        <div class="panel-heading"><div><span class="eyebrow">ONE-TAP ACTIONS</span><h2>Quick contact</h2></div></div>
                        <div class="quick-action"><span class="quick-icon call">☎</span><div><strong>Call from iPhone</strong><small>Tap a lead number to open your dialer</small></div></div>
                        <div class="quick-action"><span class="quick-icon mail">✉</span><div><strong>Bulk email</strong><small>Select leads and send one campaign</small></div></div>
                        <div class="quick-action"><span class="quick-icon note">✓</span><div><strong>Update status</strong><small>Move selected leads through the pipeline</small></div></div>
                    </article>

                    <article class="panel money-panel" id="payments">
                        <div class="panel-heading"><div><span class="eyebrow">MONEY</span><h2>Payments</h2></div><span class="soft-badge">No entries</span></div>
                        <div class="money-row"><span>Closed deal value</span><strong>₹0</strong></div>
                        <div class="money-row"><span>Amount received</span><strong>₹0</strong></div>
                        <div class="money-row pending"><span>Payment pending</span><strong>₹0</strong></div>
                        <p class="panel-note">When a lead closes, record the total value and received amount. The balance will update automatically.</p>
                    </article>

                    <article class="panel flow-panel">
                        <div class="panel-heading"><div><span class="eyebrow">WORKFLOW</span><h2>Lead journey</h2></div></div>
                        <div class="flow"><span>New</span><i>→</i><span>Pending</span><i>→</i><span>Contacted</span><i>→</i><span>Closed</span></div>
                        <p class="panel-note">Every uploaded lead starts as New. You can update one lead or many together.</p>
                    </article>
                </section>
            </main>
        </div>
        <?php endif; ?>
    <?php elseif ($view === 'itedvantage'): ?>
        <?php require __DIR__ . '/views/itedvantage.php'; ?>
    <?php elseif ($view === 'rangoli'): ?>
        <?php require __DIR__ . '/views/rangoli.php'; ?>
    <?php elseif ($view === 'chemtech'): ?>
        <?php require __DIR__ . '/views/chemtech.php'; ?>
    <?php else: ?>
        <header class="topbar">
            <a class="logo" href="./"><span>R</span> Ray CRM</a>
            <form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><button class="logout" type="submit" name="logout" value="1">Sign out</button></form>
        </header>
        <main class="dashboard">
            <section class="welcome"><span class="eyebrow">YOUR WORKSPACE</span><h1>Welcome back, Siddh 👋</h1><p>Choose a business to start working.</p></section>
            <section class="business-grid" aria-label="Businesses">
                <a class="business-card tech" href="?business=techdecodes"><span class="card-icon">TD</span><div><h2>TechDecodes</h2><p>Digital marketing</p></div><span class="status">Open workspace →</span></a>
                <a class="business-card it" href="?business=itedvantage"><span class="card-icon">IT</span><div><h2>ITedvantage</h2><p>Blogs & digital products</p></div><span class="status">Open workspace →</span></a>
                <a class="business-card wool" href="?business=rangoli"><span class="card-icon">WR</span><div><h2>Woollen Rangoli</h2><p>Products, dealers & orders</p></div><span class="status">Open workspace →</span></a>
                <a class="business-card chem" href="?business=chemtech"><span class="card-icon"><?= htmlspecialchars((string) $chemtechSettings['short_name']) ?></span><div><h2><?= htmlspecialchars((string) $chemtechSettings['company_name']) ?></h2><p>Chemical trading CRM demo</p></div><span class="status">Open workspace →</span></a>
            </section>
        </main>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>
