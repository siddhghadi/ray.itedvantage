<?php
declare(strict_types=1);
require_once __DIR__.'/src/auth.php';
if(session_status()!==PHP_SESSION_ACTIVE){session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict']);session_start();}
$_SESSION['ins_csrf']??=bin2hex(random_bytes(32));
header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
function ie(mixed $v): string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function iu(string $page='dashboard',array $args=[]): string{return '/insurance/?'.http_build_query(['page'=>$page]+$args);}
function im(int $cents): string { $s=number_format(abs($cents)/100,2,'.','');[$whole,$fraction]=explode('.',$s);if(strlen($whole)>3){$tail=substr($whole,-3);$head=substr($whole,0,-3);$whole=preg_replace('/\B(?=(\d{2})+(?!\d))/',',',$head).','.$tail;}return ($cents<0?'-':'').'₹'.$whole.'.'.$fraction;}
function ins_redirect(string $page='dashboard',array $args=[]): never {header('Location: '.iu($page,$args));exit;}
$error='';$notice=$_SESSION['ins_notice']??'';unset($_SESSION['ins_notice']);$page=(string)($_GET['page']??'dashboard');$u=null;$a=null;$result=null;
try {
    ins_db();
    // A RAY owner may enter the platform control panel, never private agency records automatically.
    if(defined('RAY_INSURANCE_ENTRY')&&($_SESSION['authenticated']??false)) {
        $email=strtolower((string)(authConfig()['email']??''));$rayUser=ins_query("SELECT id FROM users WHERE email=? AND role='platform' AND active=1",[$email])->fetch();
        if(!$rayUser && filter_var($email,FILTER_VALIDATE_EMAIL)) {
            $platformId=ins_transaction(fn()=>ins_create_user(null,$email,'RAY owner',bin2hex(random_bytes(32)),'platform'));
            $rayUser=['id'=>$platformId];
        }
        if($rayUser && !isset($_SESSION['ins_user'])) {
            require_once __DIR__.'/src/meeting.php';
            $_SESSION['ins_user']=ins_transaction(fn()=>ins_seed_demo((int)$rayUser['id']));
            $_SESSION['ins_demo_owner']=(int)$rayUser['id'];
        }
    }
    if(isset($_SESSION['ins_user']))$u=ins_user((int)$_SESSION['ins_user']);
    if($u){$agency=$u['role']==='platform'?(int)($_SESSION['ins_agency']??0):(int)$u['agency_id'];if($agency)$a=ins_context($u,$agency);}
    if($_SERVER['REQUEST_METHOD']==='POST') {
        if(!hash_equals($_SESSION['ins_csrf'],(string)($_POST['csrf']??'')))throw new DomainException('Session expired. Refresh and retry.');
        $action=(string)($_POST['action']??'');
        if($action==='login'){$logged=ins_login((string)($_POST['email']??''),(string)($_POST['password']??''));session_regenerate_id(true);$_SESSION['ins_user']=(int)$logged['id'];unset($_SESSION['ins_agency']);ins_redirect();}
        if($action==='logout'){unset($_SESSION['ins_user'],$_SESSION['ins_agency']);session_regenerate_id(true);ins_redirect();}
        if($action==='reset') {
            ins_limit('reset:'.($_SERVER['REMOTE_ADDR']??'local'));$token=(string)($_POST['token']??'');$password=(string)($_POST['password']??'');
            ins_transaction(function()use($token,$password){$r=ins_query('SELECT * FROM resets WHERE hash=? AND used=0 AND expires>?',[hash('sha256',$token),time()])->fetch();if(!$r||strlen($password)<12)throw new DomainException('Invalid/expired reset token, or password shorter than 12 characters.');ins_query('UPDATE users SET password=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$r['user_id']]);ins_query('UPDATE resets SET used=1 WHERE user_id=?',[$r['user_id']]);ins_audit(null,(int)$r['user_id'],'password reset','account');});$_SESSION['ins_notice']='Password reset. Sign in with your new password.';ins_redirect();
        }
        if(!$u)throw new DomainException('Sign in first.');
        ins_limit('write:'.$u['id'],150);
        if($action==='exit_support'){unset($_SESSION['ins_agency']);ins_redirect();}
        if($action==='enter_support'){if($u['role']!=='platform')throw new DomainException('Access denied.');$target=(int)$_POST['agency_id'];ins_context($u,$target);$_SESSION['ins_agency']=$target;ins_redirect();}
        if(in_array($action,['agency_create','agency_status'],true)) {
            if($u['role']!=='platform'||$a)throw new DomainException('Platform access required.');
            ins_transaction(function()use($action,$u){
                if($action==='agency_status'){ins_query('UPDATE agencies SET active=? WHERE id=?',[(int)!empty($_POST['active']),(int)$_POST['agency_id']]);ins_audit(null,(int)$u['id'],'agency status changed','agency:'.(int)$_POST['agency_id']);return;}
                $name=trim((string)$_POST['agency_name']);if(!$name)throw new DomainException('Agency name required.');
                ins_query('INSERT INTO agencies(name,settings,demo) VALUES(?,?,?)',[$name,ins_json(ins_defaults()),(int)!empty($_POST['demo'])]);$id=(int)ins_db()->lastInsertId();
                ins_create_user($id,(string)$_POST['email'],(string)$_POST['name'],(string)$_POST['password'],'admin');ins_audit(null,(int)$u['id'],'agency created','agency:'.$id);
            });$_SESSION['ins_notice']='Agency account updated.';ins_redirect();
        }
        if(!$a)throw new DomainException('Choose an agency with support permission.');
        ins_transaction(function()use($action,$u,$a,&$result){
            if($action==='save'){ins_save($u,$a,(string)$_POST['kind'],$_POST,(int)($_POST['id']??0));}
            elseif($action==='renew_period') {
                $r=ins_record($u,$a,(int)$_POST['id'],'edit');
                if($r['kind']!=='policies'||$r['archived'])throw new DomainException('Select an active policy record.');
                if((int)$_POST['version']!==(int)$r['version'])throw new DomainException('Policy changed. Refresh before renewing.');
                $d=$r['data'];$start=ins_date((string)$_POST['start']);$expiry=ins_date((string)$_POST['expiry']);
                if(!$start||!$expiry||$expiry<=$start||(!empty($d['expiry'])&&$start<=$d['expiry']))throw new DomainException('New coverage must start after the previous expiry and finish after its start.');
                ins_allow($u,$a,'dues','edit');
                foreach(ins_fields('policies') as $k=>$type)if($type==='money')$d[$k]/=100;
                $d['start']=$start;$d['expiry']=$expiry;$d['next_due']=ins_date((string)$_POST['next_due']);$d['premium']=$_POST['premium'];$d['status']='active';$d['client_id']=$r['client_id'];$d['assigned_id']=$r['assigned_id'];$d['version']=$r['version'];
                ins_query("UPDATE events SET status='renewed',revision=revision+1 WHERE agency_id=? AND record_id=? AND type='renewal' AND status='open'",[$a['id'],$r['id']]);
                ins_save($u,$a,'policies',$d,(int)$r['id']);ins_audit((int)$a['id'],(int)$u['id'],'coverage renewed; previous period retained','policies:'.$r['id']);
            }
            elseif($action==='archive'){$r=ins_record($u,$a,(int)$_POST['id'],'delete');if($r['kind']==='clients' && ins_query('SELECT id FROM records WHERE agency_id=? AND client_id=? AND archived=0',[$a['id'],$r['id']])->fetch())throw new DomainException('Archive linked records first.');$archived=(int)!$r['archived'];ins_query('UPDATE records SET archived=?,version=version+1 WHERE agency_id=? AND id=?',[$archived,$a['id'],$r['id']]);ins_audit((int)$a['id'],(int)$u['id'],$archived?'archive':'restore','record:'.$r['id']);}
            elseif($action==='payment')ins_pay($u,$a,(int)$_POST['event_id'],ins_money($_POST['amount']),ins_date($_POST['paid_at']),(string)$_POST['reference']);
            elseif($action==='contacted'){$e=ins_event($u,$a,(int)$_POST['event_id'],'edit');ins_query('UPDATE events SET contacted=1 WHERE id=?',[$e['id']]);ins_audit((int)$a['id'],(int)$u['id'],'marked contacted','event:'.$e['id']);}
            elseif($action==='renew') {
                $e=ins_event($u,$a,(int)$_POST['event_id'],'edit');if($e['type']!=='renewal'||$e['status']!=='open')throw new DomainException('Select an open coverage renewal.');
                $r=ins_record($u,$a,(int)$e['record_id'],'edit');$d=$r['data'];$d['start']=ins_date($_POST['start']);$d['expiry']=ins_date($_POST['expiry']);if(!$d['start']||!$d['expiry']||$d['start']<=$e['due']||$d['expiry']<=$d['start'])throw new DomainException('New coverage must start after the prior expiry and end after its start.');
                foreach(ins_fields('policies') as $key=>$type)if($type==='money')$d[$key]=$d[$key]/100;
                $d['premium']=$_POST['premium'];$d['next_due']=ins_date($_POST['next_due']);$d['status']='active';$d['client_id']=$r['client_id'];$d['assigned_id']=$r['assigned_id'];$d['name'].=' · renewal';$d['notes'].="\nRenewed from policy record #".$r['id'];
                $new=ins_save($u,$a,'policies',$d);ins_query("UPDATE events SET status='renewed',revision=revision+1 WHERE id=?",[$e['id']]);ins_audit((int)$a['id'],(int)$u['id'],'renewed; prior policy retained','policy:'.$r['id'].'->'.$new);
            }
            elseif($action==='convert') {
                $r=ins_record($u,$a,(int)$_POST['id'],'edit');if($r['kind']!=='leads'||$r['data']['stage']==='converted')throw new DomainException('Lead already converted or invalid.');
                $d=$r['data'];$match=null;foreach(ins_records($u,$a,'clients') as $c)if(($d['email']&&strtolower($d['email'])===strtolower($c['data']['email']))||($d['phone']&&preg_replace('/\D/','',$d['phone'])===preg_replace('/\D/','',$c['data']['phone'])))$match=$c['id'];
                $client=$match??ins_save($u,$a,'clients',$d+['consent'=>'none','assigned_id'=>$r['assigned_id']]);$d['stage']='converted';ins_query('UPDATE records SET data=?,client_id=?,version=version+1 WHERE id=?',[ins_json($d),$client,$r['id']]);ins_query("UPDATE events SET status='cancelled',revision=revision+1 WHERE record_id=? AND status='open'",[$r['id']]);ins_audit((int)$a['id'],(int)$u['id'],'lead converted','client:'.$client);
            }
            elseif($action==='settings') {
                ins_allow($u,$a,'settings','settings');$s=$a['settings'];$s['modules']=array_values(array_intersect(INS_MODULES,$_POST['modules']??[]));$s['categories']=array_values(array_unique(array_filter(array_map('trim',explode("\n",$_POST['categories'])))));if(!$s['categories'])throw new DomainException('Enable at least one category.');
                $s['timezone']=(string)$_POST['timezone'];if(!in_array($s['timezone'],DateTimeZone::listIdentifiers(),true))throw new DomainException('Unknown timezone.');$s['reminders']=ins_reminder_days($_POST['reminders']);$s['delivery_time']=$_POST['delivery_time'];if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$s['delivery_time']))throw new DomainException('Invalid delivery time.');
                $s['widgets']=array_values(array_intersect(explode(',',$_POST['widgets']),ins_defaults()['widgets']));$s['contact']=substr($_POST['contact'],0,1000);$s['due_window']=max(1,min(365,(int)$_POST['due_window']));
                ins_query('UPDATE agencies SET name=?,settings=? WHERE id=?',[trim($_POST['agency_name'])?:$a['name'],ins_json($s),$a['id']]);ins_audit((int)$a['id'],(int)$u['id'],'settings updated','agency');
            }
            elseif($action==='staff') {ins_allow($u,$a,'settings','settings');$permissions=[];foreach(INS_MODULES as $m)$permissions[$m]=array_values(array_intersect(['view','edit','delete','export'],$_POST['perm'][$m]??[]));ins_create_user((int)$a['id'],$_POST['email'],$_POST['name'],$_POST['password'],'staff',$permissions);ins_audit((int)$a['id'],(int)$u['id'],'staff created','account');}
            elseif($action==='support') {if($u['role']!=='admin')throw new DomainException('Only agency admins may grant support access.');$admin=(int)$_POST['admin_id'];if(!ins_query("SELECT id FROM users WHERE id=? AND role='platform'",[$admin])->fetch())throw new DomainException('Invalid platform admin.');ins_query('INSERT INTO support(agency_id,admin_id,expires_at) VALUES(?,?,?)',[$a['id'],$admin,gmdate('c',time()+3600)]);ins_audit((int)$a['id'],(int)$u['id'],'support granted for one hour','admin:'.$admin);}
            elseif($action==='revoke_support') {if($u['role']!=='admin')throw new DomainException('Agency admin required.');ins_query('UPDATE support SET revoked=1 WHERE agency_id=?',[$a['id']]);ins_audit((int)$a['id'],(int)$u['id'],'support revoked','agency');}
            elseif($action==='quote') {
                ins_allow($u,$a,'plans','edit');$client=ins_record($u,$a,(int)$_POST['client_id']);$plan=ins_record($u,$a,(int)$_POST['plan_id']);if($client['kind']!=='clients'||$plan['kind']!=='plans')throw new DomainException('Select a client and plan.');
                if(!trim($_POST['source'])||!trim($_POST['assumptions']))throw new DomainException('Official quote source and coverage assumptions are required.');
                $data=['name'=>$plan['data']['name'],'label'=>'Manually recorded quote','amount'=>ins_money($_POST['amount']),'source'=>substr($_POST['source'],0,2000),'assumptions'=>substr($_POST['assumptions'],0,10000),'valid_until'=>ins_date($_POST['valid_until']),'plan_snapshot'=>$plan['data'],'plan_version'=>$plan['version'],'client_name'=>$client['data']['name'],'agency_name'=>$a['name'],'agency_contact'=>$a['settings']['contact']??'','timestamp'=>gmdate('c')];
                ins_query('INSERT INTO records(agency_id,kind,client_id,assigned_id,data,created_at,updated_at) VALUES(?,?,?,?,?,?,?)',[$a['id'],'plans',$client['id'],$client['assigned_id'],ins_json($data),gmdate('c'),gmdate('c')]);ins_audit((int)$a['id'],(int)$u['id'],'quote saved','client:'.$client['id']);
            }
            elseif($action==='upload') {require __DIR__.'/src/documents.php';ins_upload($u,$a,$_FILES['document']??[],(int)$_POST['client_id'],(string)$_POST['label']);}
            elseif($action==='import_preview'||$action==='import_commit') {require __DIR__.'/src/import.php';$result=ins_import($u,$a,$action);}
            else throw new DomainException('Unknown action.');
        });
        if(!$result){$_SESSION['ins_notice']='Saved successfully.';if(!empty($_POST['return_client'])){$returnClient=ins_record($u,$a,(int)$_POST['return_client']);if($returnClient['kind']==='clients')ins_redirect('clients',['id'=>$returnClient['id']]);}ins_redirect($page);}
    }
    if($u&&$a&&isset($_GET['download'])) {require __DIR__.'/src/documents.php';ins_download($u,$a,(int)$_GET['download']);}
    if($u&&$a&&isset($_GET['export'])) {
        $kind=(string)$_GET['export'];ins_allow($u,$a,$kind,'export');$rows=ins_records($u,$a,$kind);ins_audit((int)$a['id'],(int)$u['id'],'export',$kind);
        header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="insurance-'.preg_replace('/[^a-z]/','',$kind).'.csv"');$out=fopen('php://output','w');$keys=array_keys(ins_fields($kind));fputcsv($out,array_merge(['id'],$keys));foreach($rows as $r){$line=[$r['id']];foreach($keys as $key){$v=(string)($r['data'][$key]??'');if((ins_fields($kind)[$key]??'')==='money')$v=(string)((int)$v/100);$line[]=preg_match('/^[=+@\-\t\r]/',$v)?"'".$v:$v;}fputcsv($out,$line);}exit;
    }
} catch(DomainException $ex){$error=$ex->getMessage();if($_SERVER['REQUEST_METHOD']==='GET')http_response_code(403);}catch(Throwable $ex){$error='The request could not be completed. Check the local setup and try again. No success has been recorded.';http_response_code(500);}
require __DIR__.'/src/view.php';
