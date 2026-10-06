<?php
// Add illustrative prior periods only to the explicitly labelled synthetic demo policies.
function ins_demo_history(array $u,array $a): void {
    if(!$a['demo']||$u['role']!=='admin')return;
    ins_transaction(function()use($u,$a){
        foreach(ins_query("SELECT * FROM records WHERE agency_id=? AND kind='policies'",[$a['id']])->fetchAll() as $r){
            $d=json_decode($r['data'],true);
            if(!preg_match('/^DEMO-POL-\d{3}$/',$d['policy_number']??'')||($d['provider']??'')!=='Example insurer (fictional)'||empty($d['start']))continue;
            if(ins_query('SELECT id FROM record_revisions WHERE agency_id=? AND record_id=? AND version=-1',[$a['id'],$r['id']])->fetchColumn())continue;
            $end=(new DateTimeImmutable($d['start']))->modify('-1 day');
            for($i=1;$i<=5;$i++){
                $old=$d;$start=$end->modify('-1 year')->modify('+1 day');
                $old['start']=$start->format('Y-m-d');$old['expiry']=$end->format('Y-m-d');$old['next_due']=$old['start'];$old['premium']=(int)round($d['premium']*pow(.94,$i));$old['notes']='Synthetic historical coverage for demonstration only; not a payment or real contract.';
                ins_query('INSERT INTO record_revisions(agency_id,record_id,version,data,actor_id,created_at) VALUES(?,?,?,?,?,?)',[$a['id'],$r['id'],-$i,ins_json($old),$u['id'],$old['start'].' 09:00:00']);
                $end=$start->modify('-1 day');
            }
        }
    });
}
