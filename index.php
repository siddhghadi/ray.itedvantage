<?php
declare(strict_types=1);

const OWNER_EMAIL = 'siddh.ghadi@gmail.com';
const AUTH_FILE = __DIR__ . '/storage/auth.php';
const LEADS_FILE = __DIR__ . '/storage/leads.json';
const ACTIVITIES_FILE = __DIR__ . '/storage/activities.json';
const CAMPAIGNS_FILE = __DIR__ . '/storage/campaigns.json';
const SENDER_EMAIL = 'contact@techdecodes.com';
const OWNER_CALLING_NUMBER = '+91 7039636906';

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'");

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

function findLeadIndex(array $leads, string $id): ?int
{
    foreach ($leads as $index => $lead) if (hash_equals((string) ($lead['id'] ?? ''), $id)) return $index;
    return null;
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
    $seen = [];
    foreach ($existing as $lead) $seen[strtolower(($lead['email'] ?? '') . '|' . ($lead['phone'] ?? '') . '|' . ($lead['business'] ?? ''))] = true;
    $added = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $value = static fn(string $field): string => trim((string) ($row[$columns[$field] ?? -1] ?? ''));
        $business = $value('business');
        if ($business === '') continue;
        $email = strtolower($value('email'));
        $phone = $value('phone');
        $dedupe = strtolower($email . '|' . $phone . '|' . $business);
        if (isset($seen[$dedupe])) continue;
        $existing[] = ['id' => bin2hex(random_bytes(8)), 'business' => $business, 'email' => $email, 'phone' => $phone, 'website' => $value('website'), 'address' => $value('address'), 'category' => $value('category'), 'status' => 'new', 'created_at' => gmdate('c')];
        $seen[$dedupe] = true;
        $added++;
    }
    fclose($handle);
    return [$existing, $added];
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
$view = $isAuthenticated && ($_GET['business'] ?? '') === 'techdecodes' ? 'techdecodes' : 'home';
$allowedPages = ['dashboard','leads','payments','revenue','email','activity','settings'];
$tdPage = in_array((string) ($_GET['page'] ?? 'dashboard'), $allowedPages, true) ? (string) ($_GET['page'] ?? 'dashboard') : 'dashboard';
$notice = '';
if ($isAuthenticated && $view === 'techdecodes' && $_SERVER['REQUEST_METHOD'] === 'POST' && validCsrf()) {
    try {
        $leadsForAction = loadLeads();
        if (isset($_POST['import_leads'])) {
            $file = $_FILES['lead_file'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 10 * 1024 * 1024) throw new RuntimeException('Choose a CSV file smaller than 10 MB.');
            if (strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'csv') throw new RuntimeException('Export your sheet as CSV first.');
            [$leadsForAction, $added] = importCsvLeads((string) $file['tmp_name'], $leadsForAction);
            saveLeads($leadsForAction);
            $_SESSION['notice'] = $added . ' new lead' . ($added === 1 ? '' : 's') . ' imported.';
            tdRedirect('leads');
        }
        if (isset($_POST['update_status'])) {
            $ids = array_map('strval', (array) ($_POST['lead_ids'] ?? []));
            $status = (string) ($_POST['status'] ?? '');
            if (!$ids || !in_array($status, ['new','pending','contacted','closed','lost'], true)) throw new RuntimeException('Select leads and a valid status.');
            $updated = 0;
            foreach ($leadsForAction as &$lead) if (in_array((string) ($lead['id'] ?? ''), $ids, true)) { $lead['status'] = $status; $lead['updated_at'] = gmdate('c'); $updated++; }
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
$leads = $isAuthenticated ? loadLeads() : [];
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
$campaigns = $isAuthenticated ? loadJsonFile(CAMPAIGNS_FILE) : [];
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
    <title><?= $isAuthenticated ? 'Dashboard' : ($isSetup ? 'Sign in' : 'Set up access') ?> · Ray CRM</title>
    <link rel="stylesheet" href="assets/styles.css?v=<?= (int) filemtime(__DIR__ . '/assets/styles.css') ?>">
    <script defer src="assets/app.js?v=<?= (int) filemtime(__DIR__ . '/assets/app.js') ?>"></script>
</head>
<body class="<?= $isAuthenticated ? 'app-page' : 'auth-page' ?>">
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
    <?php else: ?>
        <header class="topbar">
            <a class="logo" href="./"><span>R</span> Ray CRM</a>
            <form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><button class="logout" type="submit" name="logout" value="1">Sign out</button></form>
        </header>
        <main class="dashboard">
            <section class="welcome"><span class="eyebrow">YOUR WORKSPACE</span><h1>Good to see you, Siddh.</h1><p>Choose a business to start working.</p></section>
            <section class="business-grid" aria-label="Businesses">
                <a class="business-card tech" href="?business=techdecodes"><span class="card-icon">TD</span><div><h2>TechDecodes</h2><p>Digital marketing</p></div><span class="status">Open workspace →</span></a>
                <article class="business-card it"><span class="card-icon">IT</span><div><h2>ITedvantage</h2><p>Content & digital products</p></div><span class="status">Planned</span></article>
                <article class="business-card wool"><span class="card-icon">WR</span><div><h2>Woolen Rangoli</h2><p>Products & orders</p></div><span class="status">Planned</span></article>
            </section>
        </main>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>
