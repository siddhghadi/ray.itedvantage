<?php
declare(strict_types=1);

const OWNER_EMAIL = 'siddh.ghadi@gmail.com';
const AUTH_FILE = __DIR__ . '/storage/auth.php';

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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $isAuthenticated ? 'Dashboard' : ($isSetup ? 'Sign in' : 'Set up access') ?> · Ray CRM</title>
    <link rel="stylesheet" href="assets/styles.css?v=<?= (int) filemtime(__DIR__ . '/assets/styles.css') ?>">
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
                    <article class="metric-card"><span>Total leads</span><strong>0</strong><small>Ready for your first import</small></article>
                    <article class="metric-card purple"><span>Pending follow-up</span><strong>0</strong><small>No pending leads</small></article>
                    <article class="metric-card green"><span>Total revenue</span><strong>₹0</strong><small>From closed leads</small></article>
                    <article class="metric-card orange"><span>Pending payment</span><strong>₹0</strong><small>Nothing outstanding</small></article>
                </section>

                <section class="td-grid">
                    <article class="panel leads-panel" id="leads">
                        <div class="panel-heading"><div><span class="eyebrow">PIPELINE</span><h2>Leads</h2></div><button class="small-button" type="button" disabled>Upload sheet</button></div>
                        <div class="stage-tabs"><span class="selected">All <b>0</b></span><span>New <b>0</b></span><span>Pending <b>0</b></span><span>Contacted <b>0</b></span><span>Closed <b>0</b></span></div>
                        <div class="empty-state"><div class="upload-icon">⇧</div><h3>Import your first lead sheet</h3><p>CSV and Excel uploads will create leads and separate them by status.</p><button class="primary-button" type="button" disabled>Upload spreadsheet</button></div>
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
