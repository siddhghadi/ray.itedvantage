<?php
declare(strict_types=1);
require_once __DIR__.'/db.php';

const INS_MODULES=['clients','policies','investments','dues','plans','calculators','leads','documents','reports','claims','commissions'];
function ins_categories(): array { return ['Health','Life / Term / LIC','Motor','Travel','Personal accident','Property / Business','Mutual funds / SIP','Lump sum','FD / RD','Retirement']; }
function ins_defaults(): array { return ['timezone'=>'Asia/Kolkata','currency'=>'INR','modules'=>array_diff(INS_MODULES,['claims','commissions']),'categories'=>ins_categories(),'reminders'=>[30,15,7,1,0,-7],'delivery_time'=>'09:00','widgets'=>['clients','policies','renewals','premiums','overdue','expired','sip','maturities','followups','collections','pending','leads'],'due_window'=>30]; }
function ins_user(int $id): array { $u=ins_query('SELECT * FROM users WHERE id=? AND active=1',[$id])->fetch(); if(!$u) throw new DomainException('Please sign in again.'); $u['permissions']=json_decode($u['permissions'],true); return $u; }
function ins_agency(int $id): array { $a=ins_query('SELECT * FROM agencies WHERE id=? AND active=1',[$id])->fetch(); if(!$a) throw new DomainException('Agency is unavailable or suspended.'); $a['settings']=array_replace(ins_defaults(),json_decode($a['settings'],true)); return $a; }
function ins_context(array $u, int $agency): array {
    $a=ins_agency($agency);
    if($u['role']==='platform') {
        if(!ins_query('SELECT id FROM support WHERE agency_id=? AND admin_id=? AND revoked=0 AND expires_at>?',[$agency,$u['id'],gmdate('c')])->fetch()) throw new DomainException('Agency support permission is required.');
        ins_audit($agency,(int)$u['id'],'support access','agency');
    } elseif((int)$u['agency_id']!==$agency) throw new DomainException('Access denied.');
    return $a;
}
function ins_allow(array $u,array $a,string $module,string $action='view'): void {
    if(!in_array($module,['settings','dashboard','notifications']) && !in_array($module,$a['settings']['modules'],true)) throw new DomainException('Module disabled. Existing records are preserved; ask your agency admin to re-enable it in Settings.');
    if($u['role']==='platform' && $action!=='view') throw new DomainException('Support access is read-only.');
    if($u['role']==='staff' && !in_array($action,$u['permissions'][$module]??[],true)) throw new DomainException('You do not have permission for this action.');
}
function ins_record(array $u,array $a,int $id,string $action='view'): array {
    $r=ins_query('SELECT * FROM records WHERE agency_id=? AND id=?',[$a['id'],$id])->fetch();
    if(!$r) throw new DomainException('Record not found.');
    ins_allow($u,$a,$r['kind'],$action);
    if($u['role']==='staff') {
        $client=$r['kind']==='clients'?$r:ins_query('SELECT assigned_id FROM records WHERE agency_id=? AND id=?',[$a['id'],$r['client_id']])->fetch();
        if(!$client || (int)$client['assigned_id']!==(int)$u['id']) throw new DomainException('Record not assigned to you.');
    }
    return ins_unpack($r);
}
function ins_records(array $u,array $a,string $kind,bool $archived=false): array {
    ins_allow($u,$a,$kind);
    $sql='SELECT r.* FROM records r WHERE r.agency_id=? AND r.kind=? AND r.archived=?'; $args=[$a['id'],$kind,(int)$archived];
    if($u['role']==='staff') {$sql.=" AND (CASE WHEN r.kind='clients' THEN r.assigned_id ELSE (SELECT c.assigned_id FROM records c WHERE c.agency_id=r.agency_id AND c.id=r.client_id) END)=?"; $args[]=$u['id'];}
    return array_map('ins_unpack',ins_query($sql.' ORDER BY r.id DESC',$args)->fetchAll());
}
function ins_date(string $value): string { if($value==='')return ''; $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value); if(!$d || $d->format('Y-m-d')!==$value)throw new DomainException('Enter a valid date.'); return $value; }
function ins_money(mixed $value): int { if(!is_numeric($value)|| !is_finite((float)$value)||(float)$value<0||(float)$value>10000000000)throw new DomainException('Amount must be between 0 and 10 billion.'); return (int)round((float)$value*100); }
function ins_next(string $anchor,string $frequency,int $occurrence): string {
    $date=new DateTimeImmutable(ins_date($anchor)); $months=['monthly'=>1,'quarterly'=>3,'half-yearly'=>6,'yearly'=>12];
    if($frequency==='weekly')return $date->modify('+'.($occurrence*7).' days')->format('Y-m-d');
    if(!isset($months[$frequency])) return $anchor;
    $first=$date->modify('first day of this month')->modify('+'.($months[$frequency]*$occurrence).' months');
    return $first->setDate((int)$first->format('Y'),(int)$first->format('m'),min((int)$date->format('d'),(int)$first->format('t')))->format('Y-m-d');
}
function ins_fields(string $kind): array {
    $common=['name'=>'text','notes'=>'textarea'];
    return match($kind) {
        'clients'=>$common+['dob'=>'date','phone'=>'tel','email'=>'email','address'=>'textarea','city'=>'text','pincode'=>'text','tags'=>'text','source'=>'text','consent'=>'select:none,email,whatsapp,both','family'=>'textarea'],
        'policies'=>$common+['category'=>'category','provider'=>'text','product'=>'text','policy_number'=>'text','policyholder'=>'text','insured_members'=>'textarea','coverage'=>'money','premium'=>'money','frequency'=>'frequency','start'=>'date','expiry'=>'date','next_due'=>'date','maturity'=>'date','payment_term'=>'text','nominee'=>'textarea','status'=>'select:active,pending,cancelled,lapsed,expired','specific_fields'=>'textarea','reminders'=>'text'],
        'investments'=>$common+['category'=>'category','provider'=>'text','scheme'=>'text','reference'=>'text','amount'=>'money','frequency'=>'frequency','start'=>'date','end'=>'date','next_due'=>'date','maturity'=>'date','status'=>'select:active,paused,completed,closed','reminders'=>'text'],
        'leads'=>$common+['phone'=>'tel','email'=>'email','source'=>'text','stage'=>'select:enquiry,contacted,quotation shared,follow-up,converted,lost','next_due'=>'date','next_action'=>'text'],
        'plans'=>$common+['category'=>'category','provider'=>'text','product_version'=>'text','eligibility'=>'textarea','coverage_options'=>'textarea','benefits'=>'textarea','exclusions'=>'textarea','conditions'=>'textarea','payment_options'=>'text','source_url'=>'url','verified_date'=>'date','effective_from'=>'date','effective_to'=>'date','pricing'=>'select:Quote required,Manually recorded official quote','status'=>'select:active,withdrawn'],
        'claims'=>$common+['policy_id'=>'text','reference'=>'text','submission_date'=>'date','amount'=>'money','status'=>'select:submitted,under review,approved,rejected,settled','next_due'=>'date'],
        'commissions'=>$common+['policy_id'=>'text','expected'=>'money','received'=>'money','next_due'=>'date','status'=>'select:pending,partial,received'],
        default=>throw new DomainException('Unknown module.')
    };
}
function ins_save(array $u,array $a,string $kind,array $input,int $id=0): int {
    ins_allow($u,$a,$kind,'edit');
    $old=$id?ins_record($u,$a,$id,'edit'):null;
    if($old && isset($old['data']['plan_snapshot']))throw new DomainException('Saved quotes are immutable. Create a new quote instead.');
    if($old && $old['client_id'] && (int)($input['client_id']??0)!==(int)$old['client_id'])throw new DomainException('Existing records cannot be moved between clients.');
    if($old && $old['kind']!==$kind)throw new DomainException('Record type cannot change.');
    if($old && (int)($input['version']??0)!==(int)$old['version'])throw new DomainException('This record changed. Reopen it before saving.');
    $data=[]; foreach(ins_fields($kind) as $key=>$type) {
        $value=trim((string)($input[$key]??'')); if(strlen($value)>15000)throw new DomainException('Field is too long.');
        if($type==='date')$value=ins_date($value);
        if($type==='money')$value=ins_money($value===''?0:$value);
        if(str_starts_with($type,'select:')&&!in_array($value,explode(',',substr($type,7)),true))throw new DomainException('Invalid '.$key.'.');
        if($type==='frequency'&&!in_array($value,['once','weekly','monthly','quarterly','half-yearly','yearly'],true))throw new DomainException('Invalid frequency.');
        if($type==='category'&&!in_array($value,$a['settings']['categories'],true))throw new DomainException('Select an enabled category.');
        if($type==='email'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL))throw new DomainException('Invalid email.');
        if($type==='url'&&$value!==''&&(!filter_var($value,FILTER_VALIDATE_URL)||!preg_match('~^https?://~',$value)))throw new DomainException('Use an HTTP(S) source URL.');
        $data[$key]=$value;
    }
    if($data['name']==='')throw new DomainException('Name is required.');
    foreach(['expiry','end','maturity'] as $key)if(!empty($data[$key])&&!empty($data['start'])&&$data[$key]<$data['start'])throw new DomainException('End dates cannot precede the start date.');
    if(!empty($data['reminders']))ins_reminder_days($data['reminders']);
    $client=null;
    if(!in_array($kind,['clients','plans','leads'],true)) { $client=(int)($input['client_id']??0); $c=ins_record($u,$a,$client); if($c['kind']!=='clients'||$c['archived'])throw new DomainException('Choose an active client.'); }
    if($kind==='leads'&&!empty($input['client_id'])){$c=ins_record($u,$a,(int)$input['client_id']);if($c['kind']!=='clients')throw new DomainException('Invalid client.');$client=$c['id'];}
    if($u['role']==='staff' && $kind==='leads' && !$client)throw new DomainException('Staff leads must be attached to an assigned client.');
    $assigned=$u['role']==='staff'?(int)$u['id']:(int)($input['assigned_id']??$u['id']);
    if(!ins_query('SELECT id FROM users WHERE id=? AND agency_id=? AND active=1',[$assigned,$a['id']])->fetch())throw new DomainException('Choose a staff member from this agency.');
    if($kind==='clients')foreach(ins_query("SELECT id,data FROM records WHERE agency_id=? AND kind='clients' AND id<>?",[$a['id'],$id])->fetchAll() as $existing) { $d=json_decode($existing['data'],true); if(($data['email']!==''&&strtolower($data['email'])===strtolower($d['email']??''))||($data['phone']!==''&&preg_replace('/\D/','',$data['phone'])===preg_replace('/\D/','',$d['phone']??'')))throw new DomainException('A client with this email or phone exists. Review the existing record.'); }
    $now=gmdate('c');
    if($old) { ins_query('INSERT INTO record_revisions(agency_id,record_id,version,data,actor_id,created_at) VALUES(?,?,?,?,?,?)',[$a['id'],$id,$old['version'],ins_json($old['data']),$u['id'],$now]);ins_query('UPDATE records SET data=?,client_id=?,assigned_id=?,version=version+1,updated_at=? WHERE agency_id=? AND id=?',[ins_json($data),$client,$assigned,$now,$a['id'],$id]); ins_audit((int)$a['id'],(int)$u['id'],'edit',$kind.':'.$id); }
    else { ins_query('INSERT INTO records(agency_id,kind,client_id,assigned_id,data,created_at,updated_at) VALUES(?,?,?,?,?,?,?)',[$a['id'],$kind,$client,$assigned,ins_json($data),$now,$now]); $id=(int)ins_db()->lastInsertId(); ins_audit((int)$a['id'],(int)$u['id'],'create',$kind.':'.$id); }
    ins_sync_events($a,$id,$data,$kind,$client,$old['data']??null);
    return $id;
}
function ins_reminder_days(string $s): array { $out=[];foreach(explode(',',$s) as $v) { $v=trim($v); if(!preg_match('/^-?\d+$/',$v)||abs((int)$v)>365)throw new DomainException('Reminder offsets must be whole days between -365 and 365.');$out[]=(int)$v; } return array_values(array_unique($out)); }
function ins_sync_events(array $a,int $id,array $d,string $kind,?int $client,?array $old=null): void {
    if(!$client)return;
    $spec=match($kind){'policies'=>['renewal'=>['expiry',0,'once'],'premium'=>['next_due',$d['premium'],'frequency'],'maturity'=>['maturity',0,'once']],'investments'=>['contribution'=>['next_due',$d['amount'],'frequency'],'maturity'=>['maturity',0,'once']],'leads','claims'=>['followup'=>['next_due',0,'once']],default=>[]};
    foreach($spec as $type=>[$key,$amount,$freqKey]) {
        $due=$d[$key]??''; $freq=$freqKey==='frequency'?($d['frequency']??'once'):'once';
        $enabled=!in_array($d['status']??'active',['cancelled','paused','completed','closed'],true)&&!in_array($d['stage']??'',['lost','converted'],true);
        if($old && $due===($old[$key]??'') && $amount===($kind==='policies'&&$type==='premium'?($old['premium']??0):($kind==='investments'&&$type==='contribution'?($old['amount']??0):0)) && $freq===($freqKey==='frequency'?($old['frequency']??'once'):'once') && ($d['status']??'')===($old['status']??'') && ($d['stage']??'')===($old['stage']??''))continue;
        if(ins_query("SELECT id FROM events WHERE agency_id=? AND record_id=? AND type=? AND status='open' AND received>0",[$a['id'],$id,$type])->fetch())throw new DomainException('This schedule has a partially paid event. Complete that event before changing its date, amount or recurrence.');
        ins_query("UPDATE events SET status='cancelled',revision=revision+1 WHERE agency_id=? AND record_id=? AND type=? AND status='open'",[$a['id'],$id,$type]);
        if(!$enabled||!$due)continue;
        $existing=ins_query('SELECT * FROM events WHERE record_id=? AND type=? AND due=?',[$id,$type,$due])->fetch();
        if($existing) { if($existing['status']==='paid'||$existing['status']==='renewed')continue; if($amount<(int)$existing['received'])throw new DomainException('Amount cannot be lower than recorded payments.'); ins_query("UPDATE events SET amount=?,status='open',anchor=?,occurrence=0,frequency=?,revision=revision+1 WHERE id=?",[$amount,$due,$freq,$existing['id']]); }
        else ins_query('INSERT INTO events(agency_id,record_id,client_id,type,due,amount,anchor,frequency) VALUES(?,?,?,?,?,?,?,?)',[$a['id'],$id,$client,$type,$due,$amount,$due,$freq]);
    }
}
function ins_event(array $u,array $a,int $id,string $action='view'): array {
    ins_allow($u,$a,'dues',$action); $e=ins_query('SELECT * FROM events WHERE agency_id=? AND id=?',[$a['id'],$id])->fetch();if(!$e)throw new DomainException('Event not found.');$r=ins_record($u,$a,(int)$e['record_id']);if($r['archived'])throw new DomainException('Record is archived.'); return $e;
}
function ins_pay(array $u,array $a,int $id,int $amount,string $date,string $reference): void {
    $e=ins_event($u,$a,$id,'edit'); if(!in_array($e['type'],['premium','contribution'],true)||$e['status']!=='open'||$amount<=0||$amount>(int)$e['amount']-(int)$e['received'])throw new DomainException('Enter a payment within the outstanding balance.');
    ins_date($date);if(!$date)throw new DomainException('Payment date required.');
    ins_query('INSERT INTO payments(agency_id,event_id,amount,paid_at,reference,actor_id) VALUES(?,?,?,?,?,?)',[$a['id'],$id,$amount,$date,substr($reference,0,200),$u['id']]);
    $paid=$amount+(int)$e['received'];$done=$paid===(int)$e['amount'];
    ins_query('UPDATE events SET received=?,status=?,revision=revision+1 WHERE id=?',[$paid,$done?'paid':'open',$id]);
    if($done && $e['frequency']!=='once') {
        $r=ins_record($u,$a,(int)$e['record_id']);$occ=(int)$e['occurrence']+1;$next=ins_next($e['anchor'],$e['frequency'],$occ);$d=$r['data'];
        if(empty($d['end'])||$next<=$d['end']) {ins_query('INSERT OR IGNORE INTO events(agency_id,record_id,client_id,type,due,amount,anchor,occurrence,frequency) VALUES(?,?,?,?,?,?,?,?,?)',[$a['id'],$r['id'],$e['client_id'],$e['type'],$next,$e['amount'],$e['anchor'],$occ,$e['frequency']]);}
        $d['next_due']=(string)(ins_query("SELECT min(due) FROM events WHERE record_id=? AND type=? AND status='open'",[$r['id'],$e['type']])->fetchColumn()?:'');
        ins_query('UPDATE records SET data=?,version=version+1,updated_at=? WHERE id=? AND agency_id=?',[ins_json($d),gmdate('c'),$r['id'],$a['id']]);
    }
    ins_audit((int)$a['id'],(int)$u['id'],'payment manually recorded','event:'.$id);
}
function ins_job(?DateTimeImmutable $now=null): int {
    $now??=new DateTimeImmutable('now',new DateTimeZone('UTC'));$count=0;
    foreach(ins_query('SELECT * FROM agencies WHERE active=1')->fetchAll() as $row) {
        $a=ins_agency((int)$row['id']); if(!in_array('dues',$a['settings']['modules'],true))continue;
        $local=$now->setTimezone(new DateTimeZone($a['settings']['timezone']));if($local->format('H:i')<$a['settings']['delivery_time'])continue;
        ins_materialize($a,$local->format('Y-m-d'));
        foreach(ins_query("SELECT e.*,r.data,r.kind FROM events e JOIN records r ON r.id=e.record_id WHERE e.agency_id=? AND e.status='open' AND r.archived=0",[$a['id']])->fetchAll() as $e) {
            if(!in_array($e['kind'],$a['settings']['modules'],true))continue;
            $d=json_decode($e['data'],true);$days=(int)(new DateTimeImmutable($local->format('Y-m-d')))->diff(new DateTimeImmutable($e['due']))->format('%r%a');
            $offsets=!empty($d['reminders'])?ins_reminder_days($d['reminders']):$a['settings']['reminders'];
            $eligible=array_values(array_filter($offsets,fn($v)=>$days<=$v));if(!$eligible)continue;$milestone=min($eligible);
            $s=ins_query("INSERT OR IGNORE INTO notifications(agency_id,event_id,revision,milestone,channel,state,created_at) VALUES(?,?,?,?, 'in-app','available',?)",[$a['id'],$e['id'],$e['revision'],$milestone,$now->format('c')]);$count+=$s->rowCount();
        }
    }return $count;
}
function ins_materialize(array $a,string $today): void {
    // Create every elapsed instalment plus one upcoming event, even if earlier ones remain unpaid.
    $rows=ins_query("SELECT e.*,r.data,r.kind FROM events e JOIN records r ON r.id=e.record_id WHERE e.agency_id=? AND e.status IN ('open','paid') AND e.frequency<>'once' AND r.archived=0 AND e.id=(SELECT x.id FROM events x WHERE x.record_id=e.record_id AND x.type=e.type AND x.status IN ('open','paid') ORDER BY x.due DESC LIMIT 1)",[$a['id']])->fetchAll();
    foreach($rows as $e){$d=json_decode($e['data'],true);if(!in_array($e['kind'],$a['settings']['modules'],true)||in_array($d['status']??'',['paused','completed','closed','cancelled'],true))continue;
        $due=$e['due'];$occ=(int)$e['occurrence'];$steps=0;
        while($due<=$today&&$steps++<1200){$occ++;$due=ins_next($e['anchor'],$e['frequency'],$occ);if(!empty($d['end'])&&$due>$d['end'])break;ins_query('INSERT OR IGNORE INTO events(agency_id,record_id,client_id,type,due,amount,anchor,occurrence,frequency) VALUES(?,?,?,?,?,?,?,?,?)',[$a['id'],$e['record_id'],$e['client_id'],$e['type'],$due,$e['amount'],$e['anchor'],$occ,$e['frequency']]);}
    }
}
function ins_projection(string $type,float $amount,float $rate,int $months,float $step=0,float $initial=0): array {
    if(!in_array($type,['sip','step-up','lump-sum','goal','fd','rd','retirement'],true)||$amount<0||$initial<0||$rate<0||$rate>100||$months<1||$months>1200||$step<0||$step>100)throw new DomainException('Check projection inputs (1–1200 months; 0–100% rates).');
    $r=$rate/1200;$value=in_array($type,['lump-sum','fd'],true)?$amount:$initial;$invested=$value;
    for($m=0;$m<$months;$m++){ $value*=1+$r;if(!in_array($type,['lump-sum','fd','goal'],true)){$c=$amount*pow(1+$step/100,floor($m/12));$value+=$c;$invested+=$c;} }
    if($type==='goal'){ $factor=$r==0?$months:(pow(1+$r,$months)-1)/$r;$needed=max(0,($amount-$value)/$factor);return ['result'=>round($needed,2),'invested'=>round($needed*$months+$initial,2),'label'=>'Required monthly contribution']; }
    if(!is_finite($value))throw new DomainException('Projection is outside supported range.');return ['result'=>round($value,2),'invested'=>round($invested,2),'label'=>'Projected value'];
}
