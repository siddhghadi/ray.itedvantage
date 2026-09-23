<?php
declare(strict_types=1);
if (!defined('RAY_CONSTRUCTION_ENTRY')) {
    $query = $_GET; $query['business'] = 'construction';
    header('Location: /?' . http_build_query($query), true, 308);
    exit;
}
require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/phase23.php';
require __DIR__ . '/src/phase4.php';
require __DIR__ . '/src/user_management.php';
$phase56Ready=false;$phase56LoadMessage='';try{require __DIR__ . '/src/phase56.php';$phase56Ready=true;}catch(Throwable $phase56LoadError){$phase56LoadMessage=$phase56LoadError->getMessage();error_log($phase56LoadMessage);}

$error = '';
$notice = (string)($_SESSION['notice'] ?? '');
unset($_SESSION['notice']);
$companyCount = (int)$db->query('SELECT COUNT(*) FROM companies')->fetchColumn();

// Keep older installations compatible as new administration capabilities are added.
if ($companyCount > 0) {
    foreach ([['projects', 'assign'], ['company', 'manage']] as [$module, $action]) {
        $key = $module . '.' . $action;
        $find = $db->prepare('SELECT id FROM permissions WHERE permission_key=? LIMIT 1');
        $find->execute([$key]);
        $permissionId = $find->fetchColumn();
        if (!$permissionId) {
            $permissionId = id26();
            $db->prepare('INSERT INTO permissions(id,module_name,action_name,permission_key) VALUES(?,?,?,?)')
                ->execute([$permissionId, $module, $action, $key]);
        }
        $db->prepare('INSERT IGNORE INTO role_permissions(role_id,permission_id) SELECT id,? FROM roles WHERE code="OWNER"')
            ->execute([$permissionId]);
    }
}
$migrationError='';
if($phase56LoadMessage)$migrationError='A management module update is temporarily unavailable.';
try { ensure_phase23($db); } catch(Throwable $migrationException) { $migrationError='A module update is temporarily unavailable.'; error_log($migrationException->getMessage()); }
try { ensure_phase4($db); } catch(Throwable $migrationException) { $migrationError='A module update is temporarily unavailable.'; error_log($migrationException->getMessage()); }
if($phase56Ready)try { ensure_phase56($db); } catch(Throwable $migrationException) { $migrationError='A module update is temporarily unavailable.'; error_log($migrationException->getMessage()); }
try { ensure_default_roles($db); } catch(Throwable $migrationException) { $migrationError='Default job roles are temporarily unavailable.'; error_log($migrationException->getMessage()); }

function audit(PDO $db, array $user, string $module, string $action, ?string $recordId = null): void {
    $db->prepare('INSERT INTO audit_logs(id,company_id,user_id,module_name,action_name,record_id,ip_address) VALUES(?,?,?,?,?,?,?)')
        ->execute([id26(),$user['company_id'],$user['id'],$module,$action,$recordId,$_SERVER['REMOTE_ADDR'] ?? null]);
}
function success(string $message, string $page): never { $_SESSION['notice']=$message; go('./?page='.$page); }
function field(string $key): string { return trim((string)($_POST[$key] ?? '')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) $error = 'Your session expired. Refresh and try again.';
    elseif (isset($_POST['setup']) || isset($_POST['login'])) { http_response_code(403); exit('Use your RAY login.'); }
    elseif (isset($_POST['logout'])) { $_SESSION=[]; session_destroy(); go('../'); }
    else {
        $user=require_user();
        if(isset($_POST['phase56_action'])){
            $phaseResult=phase56_action($db,$user);if(isset($phaseResult['error']))$error=$phaseResult['error'];
        } elseif(isset($_POST['phase4_action'])){
            $phaseResult=phase4_action($db,$user);if(isset($phaseResult['error']))$error=$phaseResult['error'];
        } elseif(isset($_POST['phase_action'])){
            $phaseResult=phase23_action($db,$user);if(isset($phaseResult['error']))$error=$phaseResult['error'];
        } elseif(isset($_POST['add_project'])){
            if(!can($db,$user,'projects.create')){http_response_code(403);exit('Forbidden');}
            $name=field('name');$location=field('location');$type=field('project_type');$developer=field('developer');$managerId=field('project_manager_id');$startDate=field('start_date')?:null;$expectedCompletion=field('expected_completion')?:null;$description=field('description');
            $managerCheck=$db->prepare('SELECT id FROM users WHERE id=? AND company_id=? AND status="active"');$managerCheck->execute([$managerId,$user['company_id']]);$validManager=(bool)$managerCheck->fetchColumn();
            if(strlen($name)<2||strlen($location)<2||strlen($developer)<2||!in_array($type,['residential','commercial','mixed'],true)||!$validManager)$error='Complete all required site fields and choose an active project manager.';
            else try{$db->beginTransaction();$prefix=substr(preg_replace('/[^A-Z0-9]/','',strtoupper($name)),0,6)?:'SITE';do{$code=$prefix.'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,6));$codeCheck=$db->prepare('SELECT 1 FROM projects WHERE company_id=? AND code=?');$codeCheck->execute([$user['company_id'],$code]);}while($codeCheck->fetchColumn());$id=id26();$db->prepare('INSERT INTO projects(id,company_id,name,code,location,project_type,developer,start_date,expected_completion,budget,description) VALUES(?,?,?,?,?,?,?,?,?,0,?)')->execute([$id,$user['company_id'],$name,$code,$location,$type,$developer,$startDate,$expectedCompletion,$description?:null]);$db->prepare('INSERT IGNORE INTO user_projects(user_id,project_id) VALUES(?,?)')->execute([$managerId,$id]);$managerRole=$db->prepare('SELECT id FROM roles WHERE company_id=? AND code="PROJECT_MANAGER" LIMIT 1');$managerRole->execute([$user['company_id']]);$managerRoleId=$managerRole->fetchColumn();if($managerRoleId){$roleCheck=$db->prepare('SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.company_id=? AND r.code IN("OWNER","PROJECT_MANAGER")');$roleCheck->execute([$managerId,$user['company_id']]);if(!$roleCheck->fetchColumn())$db->prepare('INSERT INTO user_roles(id,user_id,role_id,scope_type) VALUES(?,?,?,"company")')->execute([id26(),$managerId,$managerRoleId]);}$db->commit();audit($db,$user,'projects','create',$id);success('Site created with code '.$code.'.','projects');}catch(Throwable $e){if($db->inTransaction())$db->rollBack();$error='The site could not be created. Please check the information and try again.';}
        } elseif(isset($_POST['update_project'])){
            if(!can($db,$user,'projects.update')){http_response_code(403);exit('Forbidden');}
            $progress=min(100,max(0,(int)($_POST['progress']??0)));$status=field('status');$id=field('project_id');
            if(!in_array($status,['upcoming','ongoing','on_hold','completed','cancelled'],true))$error='Choose a valid project status.';
            else{$db->prepare('UPDATE projects SET progress=?,status=? WHERE id=? AND company_id=?')->execute([$progress,$status,$id,$user['company_id']]);audit($db,$user,'projects','update',$id);success('Project updated.','projects');}
        } elseif(isset($_POST['add_user'])){
            if(!can($db,$user,'users.create')){http_response_code(403);exit('Forbidden');}
            $name=field('name');$email=strtolower(field('email'));$designation=field('designation');$password=(string)($_POST['password']??'');
            if(strlen($name)<2||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<10)$error='Enter a valid user and a 10-character temporary password.';
            else try{$id=id26();$db->prepare('INSERT INTO users(id,company_id,name,email,password_hash,designation) VALUES(?,?,?,?,?,?)')->execute([$id,$user['company_id'],$name,$email,password_hash($password,PASSWORD_DEFAULT),$designation]);audit($db,$user,'users','create',$id);success('User added successfully.','users');}catch(Throwable $e){$error='That email already exists.';}
        } elseif(isset($_POST['save_user_access'])){
            $accessResult=save_user_access($db,$user);if(isset($accessResult['error']))$error=$accessResult['error'];else success($accessResult['success'],'users');
        } elseif(isset($_POST['toggle_user'])){
            if(!can($db,$user,'users.update')){http_response_code(403);exit('Forbidden');}$id=field('user_id');$status=field('status');
            if($id!==$user['id']&&in_array($status,['active','suspended'],true)){$db->prepare('UPDATE users SET status=? WHERE id=? AND company_id=?')->execute([$status,$id,$user['company_id']]);audit($db,$user,'users','status',$id);success('User status updated.','users');}
        } elseif(isset($_POST['add_role'])){
            if(!can($db,$user,'roles.manage')){http_response_code(403);exit('Forbidden');}$name=field('name');$code=strtoupper(field('code'));$description=field('description');$permissionIds=array_values(array_filter(array_map('strval',(array)($_POST['permission_ids']??[]))));
            if(strlen($name)<2||strlen($code)<2)$error='Enter a role name and code.';
            else try{$db->beginTransaction();$id=id26();$db->prepare('INSERT INTO roles(id,company_id,name,code,description) VALUES(?,?,?,?,?)')->execute([$id,$user['company_id'],$name,$code,$description]);$allowed=$db->prepare('SELECT id FROM permissions WHERE id=?');foreach($permissionIds as $permissionId){$allowed->execute([$permissionId]);if($allowed->fetchColumn())$db->prepare('INSERT INTO role_permissions(role_id,permission_id) VALUES(?,?)')->execute([$id,$permissionId]);}$db->commit();audit($db,$user,'roles','create',$id);success('Role created successfully.','roles');}catch(Throwable $e){if($db->inTransaction())$db->rollBack();$error='That role code already exists.';}
        } elseif(isset($_POST['assign_role'])){
            if(!can($db,$user,'roles.manage')){http_response_code(403);exit('Forbidden');}$userId=field('user_id');$roleId=field('role_id');
            $check=$db->prepare('SELECT 1 FROM users u JOIN roles r ON r.company_id=u.company_id WHERE u.id=? AND r.id=? AND u.company_id=?');$check->execute([$userId,$roleId,$user['company_id']]);
            if($check->fetchColumn()){$exists=$db->prepare('SELECT 1 FROM user_roles WHERE user_id=? AND role_id=? AND scope_type="company"');$exists->execute([$userId,$roleId]);if(!$exists->fetchColumn())$db->prepare('INSERT INTO user_roles(id,user_id,role_id,scope_type) VALUES(?,?,?,"company")')->execute([id26(),$userId,$roleId]);success('Role assigned successfully.','roles');}
        } elseif(isset($_POST['remove_role'])){
            if(!can($db,$user,'roles.manage')){http_response_code(403);exit('Forbidden');}$assignmentId=field('assignment_id');
            $check=$db->prepare('SELECT ur.id,ur.user_id,r.code FROM user_roles ur JOIN users u ON u.id=ur.user_id JOIN roles r ON r.id=ur.role_id WHERE ur.id=? AND u.company_id=? AND r.company_id=? AND ur.scope_type="company"');$check->execute([$assignmentId,$user['company_id'],$user['company_id']]);$assignment=$check->fetch();
            if(!$assignment){$error='Choose a valid assigned role.';}elseif($assignment['user_id']===$user['id']&&$assignment['code']==='OWNER'){$error='You cannot remove your own Company Owner role.';}else{$db->prepare('DELETE FROM user_roles WHERE id=?')->execute([$assignmentId]);audit($db,$user,'roles','remove',$assignmentId);success('Role removed successfully.','roles');}
        } elseif(isset($_POST['assign_project'])){
            if(!can($db,$user,'projects.assign')){http_response_code(403);exit('Forbidden');}$userId=field('user_id');$projectId=field('project_id');
            $check=$db->prepare('SELECT 1 FROM users u JOIN projects p ON p.company_id=u.company_id WHERE u.id=? AND p.id=? AND u.company_id=?');$check->execute([$userId,$projectId,$user['company_id']]);
            if($check->fetchColumn()){$db->prepare('INSERT IGNORE INTO user_projects(user_id,project_id) VALUES(?,?)')->execute([$userId,$projectId]);success('Project assigned successfully.','users');}
        } elseif(isset($_POST['update_company'])){
            if(!can($db,$user,'company.manage')){http_response_code(403);exit('Forbidden');}$name=field('company_name');
            if(strlen($name)<2)$error='Enter a valid company name.';else{$db->prepare('UPDATE companies SET name=? WHERE id=?')->execute([$name,$user['company_id']]);audit($db,$user,'company','update',$user['company_id']);success('Company name updated.','company');}
        }
    }
}

$user=principal();
if(!$user) go('../');

if(isset($_GET['export']))phase56_export($db,$user);

$phasePages=['construction','materials','purchases','vendors','inventory','transfers','reports','notifications','approvals','audit','system'];
$allowedPages=array_merge(['dashboard','projects','users','roles','company'],$phasePages);
$page=in_array((string)($_GET['page']??'dashboard'),$allowedPages,true)?(string)($_GET['page']??'dashboard'):'dashboard';
$allowedPeriods=['daily','monthly','yearly'];if(isset($_GET['period'])&&in_array((string)$_GET['period'],$allowedPeriods,true))$_SESSION['inventory_period']=(string)$_GET['period'];$inventoryPeriod=in_array((string)($_SESSION['inventory_period']??'daily'),$allowedPeriods,true)?(string)($_SESSION['inventory_period']??'daily'):'daily';
$companyStmt=$db->prepare('SELECT name FROM companies WHERE id=?');$companyStmt->execute([$user['company_id']]);$companyName=(string)($companyStmt->fetchColumn()?:'Company');
$projectsStmt=$db->prepare('SELECT p.* FROM projects p WHERE p.company_id=? AND (EXISTS(SELECT 1 FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.company_id=p.company_id AND r.code IN("OWNER","OPERATIONS_HEAD")) OR EXISTS(SELECT 1 FROM user_projects up WHERE up.user_id=? AND up.project_id=p.id)) ORDER BY p.updated_at DESC');$projectsStmt->execute([$user['company_id'],$user['id'],$user['id']]);$projects=$projectsStmt->fetchAll();
$usersStmt=$db->prepare('SELECT u.*,GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ", ") role_names,GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ", ") project_names FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id AND r.code IN("OWNER","OPERATIONS_HEAD","SITE_SUPERVISOR","SITE_OPERATOR") LEFT JOIN user_projects up ON up.user_id=u.id LEFT JOIN projects p ON p.id=up.project_id WHERE u.company_id=? GROUP BY u.id ORDER BY u.created_at');$usersStmt->execute([$user['company_id']]);$users=$usersStmt->fetchAll();
$rolesStmt=$db->prepare('SELECT r.*,COUNT(rp.permission_id) permission_count FROM roles r LEFT JOIN role_permissions rp ON rp.role_id=r.id WHERE r.company_id=? AND r.code IN("OWNER","OPERATIONS_HEAD","SITE_SUPERVISOR","SITE_OPERATOR") GROUP BY r.id ORDER BY FIELD(r.code,"OWNER","OPERATIONS_HEAD","SITE_SUPERVISOR","SITE_OPERATOR")');$rolesStmt->execute([$user['company_id']]);$roles=$rolesStmt->fetchAll();
$assignmentsStmt=$db->prepare('SELECT ur.id,u.id user_id,u.name user_name,r.name role_name,r.code role_code FROM user_roles ur JOIN users u ON u.id=ur.user_id JOIN roles r ON r.id=ur.role_id WHERE u.company_id=? AND r.company_id=? AND ur.scope_type="company" ORDER BY u.name,r.name');$assignmentsStmt->execute([$user['company_id'],$user['company_id']]);$roleAssignments=$assignmentsStmt->fetchAll();
$permissions=$db->query('SELECT * FROM permissions ORDER BY module_name,action_name')->fetchAll();
$leadsStmt=$db->prepare('SELECT id,name FROM leads WHERE company_id=? ORDER BY name');$leadsStmt->execute([$user['company_id']]);$leads=$leadsStmt->fetchAll();
$budget=array_sum(array_map(fn($p)=>(float)$p['budget'],$projects));$progress=$projects?(int)round(array_sum(array_column($projects,'progress'))/count($projects)):0;
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Construction · RAY</title><link rel="stylesheet" href="/construction/assets/app.css?v=105"><link rel="stylesheet" href="/construction/assets/phase1.css?v=105"><link rel="stylesheet" href="/construction/assets/phase23.css?v=105"><link rel="stylesheet" href="/construction/assets/phase4.css?v=105"><link rel="stylesheet" href="/construction/assets/phase4nav.css?v=105"><link rel="stylesheet" href="/construction/assets/phase56.css?v=105"><link rel="stylesheet" href="/construction/assets/roles.css?v=105"><link rel="stylesheet" href="/construction/assets/users.css?v=105"><link rel="stylesheet" href="/construction/assets/role-catalogue.css?v=105"><link rel="stylesheet" href="/construction/assets/projects.css?v=105"><script defer src="/construction/assets/app.js?v=105"></script></head><body>
<link rel="stylesheet" href="/construction/assets/projects.css?v=108"><link rel="stylesheet" href="/construction/assets/inventory.css?v=109"><aside class="side"><div class="brand"><span class="mark"><?=e(strtoupper(substr($companyName,0,1)))?></span><div><b>Construction</b><small><?=e($companyName)?></small></div></div><nav class="nav"><a href="/">← All businesses</a><small>MATERIAL CONTROL</small><a class="<?=$page==='dashboard'?'active':''?>" href="/?business=construction">Dashboard</a><a class="<?=$page==='projects'?'active':''?>" href="/?business=construction&page=projects">Construction Sites</a><a class="<?=$page==='inventory'?'active':''?>" href="/?business=construction&page=inventory">All Inventory</a><a class="<?=$page==='transfers'?'active':''?>" href="/?business=construction&page=transfers">Excess & Transfers</a><a class="<?=$page==='materials'?'active':''?>" href="/?business=construction&page=materials">Site Materials</a><a class="<?=$page==='reports'?'active':''?>" href="/?business=construction&page=reports">Monthly Reports</a><small>PROCUREMENT</small><a class="<?=$page==='purchases'?'active':''?>" href="/?business=construction&page=purchases">Purchases</a><a class="<?=$page==='vendors'?'active':''?>" href="/?business=construction&page=vendors">Vendors</a><small>ADMINISTRATION</small><a class="<?=$page==='users'?'active':''?>" href="/?business=construction&page=users">Team</a><a class="<?=$page==='roles'?'active':''?>" href="/?business=construction&page=roles">Roles & Permissions</a><a class="<?=$page==='company'?'active':''?>" href="/?business=construction&page=company">Company Settings</a></nav><div class="profile"><b><?=e($user['name'])?></b><small><?=e($user['email'])?></small></div></aside>
<main class="app"><header class="top"><button class="menu" data-menu>Menu</button><b><?=e($companyName)?> Inventory</b><div class="top-context">Construction Material Control</div><form class="period-switcher" method="get"><input type="hidden" name="business" value="construction"><input type="hidden" name="page" value="<?=e($page)?>"><?php if(isset($_GET['project'])):?><input type="hidden" name="project" value="<?=e((string)$_GET['project'])?>"><?php endif;?><label>View<select name="period" data-auto-submit><option value="daily" <?=$inventoryPeriod==='daily'?'selected':''?>>Daily</option><option value="monthly" <?=$inventoryPeriod==='monthly'?'selected':''?>>Monthly</option><option value="yearly" <?=$inventoryPeriod==='yearly'?'selected':''?>>Yearly</option></select></label></form><button class="push" data-fullscreen>Fullscreen</button><form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><button name="logout" value="1">Sign out</button></form></header><div class="content"><?php if($notice):?><p class="success-note"><?=e($notice)?></p><?php endif;?><?php if($error):?><p class="error"><?=e($error)?></p><?php endif;?><?php if($migrationError):?><p class="error">Setup check: <?=e($migrationError)?></p><?php endif;?>
<?php if($page==='dashboard'):?>
<?php require __DIR__ . '/src/inventory_hub.php';?>
<?php elseif($page==='projects'): try { require __DIR__ . '/src/projects_view.php'; } catch(Throwable $moduleError) { echo '<p class="error">Construction sites are temporarily unavailable.</p>'; error_log($moduleError->getMessage()); }?>
<?php elseif(in_array($page,['inventory','transfers','reports'],true)): try { require __DIR__ . '/src/inventory_hub.php'; } catch(Throwable $moduleError) { echo '<p class="error">Inventory workspace is temporarily unavailable.</p>'; error_log($moduleError->getMessage()); }?>
<?php elseif($page==='users'): try { require __DIR__ . '/src/users_view.php'; } catch(Throwable $moduleError) { echo '<p class="error">User management is temporarily unavailable.</p>'; error_log($moduleError->getMessage()); }?>
<?php elseif($page==='roles'): try { require __DIR__ . '/src/roles_view.php'; } catch(Throwable $moduleError) { echo '<p class="error">Role catalogue is temporarily unavailable.</p>'; error_log($moduleError->getMessage()); }?>
<?php elseif($page==='company'):?>
<section class="page-title"><div><span class="eyebrow">COMPANY</span><h1>Company settings</h1><p class="muted">This name becomes the product name throughout the CRM.</p></div></section><form class="form-card settings-card" method="post"><h2>Company identity</h2><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><label class="field">Company name<input name="company_name" value="<?=e($companyName)?>" required></label><button class="button" name="update_company" value="1">Update company name</button></form>
<?php elseif(in_array($page,['management','reports','notifications','approvals','audit','system'],true)): try { require __DIR__ . '/src/phase56_views.php'; } catch(Throwable $moduleError) { echo '<p class="error">This module is temporarily unavailable. Please try again.</p>'; error_log($moduleError->getMessage()); }?>
<?php elseif(in_array($page,['units','bookings','collections','brokers'],true)): try { require __DIR__ . '/src/phase4_views.php'; } catch(Throwable $moduleError) { echo '<p class="error">This module is temporarily unavailable. Please try again.</p>'; error_log($moduleError->getMessage()); }?>
<?php else: try { require __DIR__ . '/src/phase23_views.php'; } catch(Throwable $moduleError) { echo '<p class="error">This module is temporarily unavailable. Please try again.</p>'; error_log($moduleError->getMessage()); }?>
<?php endif;?></div></main></body></html>
