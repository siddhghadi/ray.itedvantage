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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $isAuthenticated ? 'Dashboard' : ($isSetup ? 'Sign in' : 'Set up access') ?> · Ray CRM</title>
    <link rel="stylesheet" href="assets/styles.css">
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
    <header class="topbar">
        <a class="logo" href="./"><span>R</span> Ray CRM</a>
        <form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><button class="logout" type="submit" name="logout" value="1">Sign out</button></form>
    </header>
    <main class="dashboard">
        <section class="welcome"><span class="eyebrow">YOUR WORKSPACE</span><h1>Good to see you, Siddh.</h1><p>Choose a business to start working.</p></section>
        <section class="business-grid" aria-label="Businesses">
            <article class="business-card tech"><span class="card-icon">TD</span><div><h2>TechDecodes</h2><p>Digital marketing</p></div><span class="status">Coming next</span></article>
            <article class="business-card it"><span class="card-icon">IT</span><div><h2>ITedvantage</h2><p>Content & digital products</p></div><span class="status">Planned</span></article>
            <article class="business-card wool"><span class="card-icon">WR</span><div><h2>Woolen Rangoli</h2><p>Products & orders</p></div><span class="status">Planned</span></article>
        </section>
    </main>
<?php endif; ?>
</body>
</html>
