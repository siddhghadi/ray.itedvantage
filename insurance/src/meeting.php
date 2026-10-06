<?php
declare(strict_types=1);
function ins_seed_demo(int $platformId): int {
    $name='Techdecodes Insurance · Meeting Demo';
    $existing=ins_query('SELECT id FROM agencies WHERE name=? AND demo=1',[$name])->fetchColumn();
    if($existing){$id=ins_query("SELECT id FROM users WHERE agency_id=? AND role='admin'",[$existing])->fetchColumn();if($id)return (int)$id;}
    $s=ins_defaults();$s['modules']=INS_MODULES;$s['contact']='Demonstration agency · Fictional records only';
    ins_query('INSERT INTO agencies(name,demo,settings) VALUES(?,1,?)',[$name,ins_json($s)]);$aid=(int)ins_db()->lastInsertId();
    $uid=ins_create_user($aid,'meeting-'.$aid.'@example.invalid','Meeting demonstrator',bin2hex(random_bytes(32)),'admin');$u=ins_user($uid);$a=ins_context($u,$aid);
    $today=new DateTimeImmutable('today',new DateTimeZone('Asia/Kolkata'));$date=fn(string $x)=>$today->modify($x)->format('Y-m-d');
    $names=['Aarav Shah','Meera Desai','Rohan Joshi','Ananya Mehta','Vikram Nair','Priya Rao','Kunal Patel','Sneha Kulkarni','Arjun Verma','Neha Kapoor','Ishaan Jain','Kavya Iyer'];
    foreach($names as $i=>$name){$n=$i+1;$c=ins_save($u,$a,'clients',['name'=>$name.' (Demo)','email'=>'client'.$n.'@example.invalid','phone'=>'','dob'=>'1990-04-12','city'=>['Mumbai','Pune','Thane'][$i%3],'consent'=>'none','assigned_id'=>$uid,'source'=>'Meeting demo','family'=>'Demo spouse · spouse · 1992-06-10','notes'=>'Fictional example. No communication consent.']);
        $expiry=$date('+'.([7,15,30,45,60,-5][$i%6]).' days');$due=$date(($i%3===0?'-3':'+'.($i+1)).' days');
        $p=ins_save($u,$a,'policies',['name'=>['Family health cover','Term protection','Motor cover'][$i%3].' · Demo','category'=>['Health','Life / Term / LIC','Motor'][$i%3],'provider'=>'Example insurer (fictional)','product'=>'Demo product, not an offer','policy_number'=>'DEMO-POL-'.str_pad((string)$n,3,'0',STR_PAD_LEFT),'policyholder'=>$name,'coverage'=>500000+100000*$i,'premium'=>12000+1000*$i,'frequency'=>'yearly','start'=>$date('-300 days'),'expiry'=>$expiry,'next_due'=>$due,'status'=>'active','client_id'=>$c,'assigned_id'=>$uid,'notes'=>'Demonstration coverage; not an actual insurance contract.']);
        if($i<6)ins_save($u,$a,'investments',['name'=>'Monthly SIP · Demo '.$n,'category'=>'Mutual funds / SIP','provider'=>'Example AMC (fictional)','scheme'=>'Demonstration fund','amount'=>2000+$i*500,'frequency'=>'monthly','start'=>$date('-60 days'),'next_due'=>$date('+'.($i+1).' days'),'status'=>'active','client_id'=>$c,'assigned_id'=>$uid]);
        ins_save($u,$a,'leads',['name'=>'Review cover · '.$name,'email'=>'client'.$n.'@example.invalid','stage'=>['enquiry','contacted','quotation shared','follow-up'][$i%4],'next_due'=>$date($i<3?'today':'+'.$i.' days'),'next_action'=>'Discuss requirements at the scheduled review','client_id'=>$c,'assigned_id'=>$uid]);
        if($i<3){$event=ins_query("SELECT id FROM events WHERE record_id=? AND type='premium'",[$p])->fetchColumn();ins_pay($u,$a,(int)$event,($i===0?6000:12000+$i*1000)*100,$date('today'),'DEMO manual payment');}
    }
    foreach(['Essential Health','Family Health Plus','Term Protection'] as $i=>$title)ins_save($u,$a,'plans',['name'=>$title.' · Demo','category'=>$i===2?'Life / Term / LIC':'Health','provider'=>'Example insurer (fictional)','product_version'=>'DEMO-1','eligibility'=>'Demonstration only; verify official eligibility','coverage_options'=>'₹5 lakh illustration','benefits'=>'Illustrative benefits for presentation only','exclusions'=>'Not verified. Obtain the official policy wording.','conditions'=>'Waiting periods, co-pay and underwriting require insurer confirmation.','pricing'=>'Quote required','status'=>'active','assigned_id'=>$uid]);
    ins_audit($aid,$platformId,'meeting demo initialized','synthetic data');ins_job();return $uid;
}
