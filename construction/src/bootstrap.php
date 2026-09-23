<?php
declare(strict_types=1);

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Strict']);
session_start();
// Construction is a RAY workspace. Every request, including exports and POSTs,
// must use the existing RAY login; legacy Construction cookies grant no access.
header('Cache-Control: no-store, private');
if (($_SESSION['authenticated'] ?? false) !== true) {
    header('Location: ../');
    exit;
}
const CRM_ROOT = __DIR__ . '/..';
$configFile = CRM_ROOT . '/config.php';
if (!is_file($configFile)) { http_response_code(503); exit('Construction configuration is pending.'); }
$config = require $configFile;
$config['app_url'] = 'https://ray.itedvantage.com/construction';
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'");

try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',$config['db_host'],(int)$config['db_port'],$config['db_name']);
    $db = new PDO($dsn,$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
} catch (Throwable $error) { error_log($error->getMessage()); http_response_code(503); exit('CRM database connection is unavailable.'); }

// Match the configured RAY owner to exactly one active Construction owner.
// Fail closed for missing/ambiguous mappings; never pick the first company.
$rayAuthFile = dirname(__DIR__, 2) . '/storage/auth.php';
$rayAuth = is_file($rayAuthFile) ? require $rayAuthFile : null;
$ownerEmail = is_array($rayAuth) ? strtolower(trim((string)($rayAuth['email'] ?? ''))) : '';
$ownerQuery = $db->prepare('SELECT DISTINCT u.id,u.company_id,u.name,u.email FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id AND r.company_id=u.company_id WHERE LOWER(u.email)=? AND u.status="active" AND r.code="OWNER" AND ur.scope_type="company"');
$ownerQuery->execute([$ownerEmail]);
$owners = $ownerEmail !== '' ? $ownerQuery->fetchAll() : [];
if (count($owners) !== 1) { http_response_code(403); exit('Construction owner access is not configured. Return to RAY and contact the administrator.'); }
$constructionPrincipal = $owners[0];

$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
function e(?string $value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function id26():string{return strtoupper(substr(bin2hex(random_bytes(16)),0,26));}
function csrf_valid():bool{return isset($_POST['csrf'])&&hash_equals((string)($_SESSION['csrf']??''),(string)$_POST['csrf']);}
function go(string $path):never{header('Location: '.$path);exit;}
function principal():?array{global $constructionPrincipal;return $constructionPrincipal;}
function require_user():array{$user=principal();if(!$user)go('../');return $user;}
function can(PDO $db,array $user,string $permission,?string $projectId=null):bool{
    $sql='SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id AND r.company_id=:company JOIN role_permissions rp ON rp.role_id=r.id AND rp.allowed=1 JOIN permissions p ON p.id=rp.permission_id AND p.permission_key=:permission WHERE ur.user_id=:user AND (ur.scope_type="company" OR (ur.scope_type="project" AND ur.project_id=:project)) LIMIT 1';
    $statement=$db->prepare($sql);$statement->execute(['company'=>$user['company_id'],'permission'=>$permission,'user'=>$user['id'],'project'=>$projectId]);return(bool)$statement->fetchColumn();
}
