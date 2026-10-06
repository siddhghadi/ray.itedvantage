<?php
function ins_import(array $u,array $a,string $action): array|null {
    ins_allow($u,$a,'clients','edit');
    if($action==='import_commit') {
        $preview=$_SESSION['ins_import']??null;if(!$preview||$preview['agency']!==$a['id']||$preview['user']!==$u['id']||$preview['expires']<time())throw new DomainException('Preview expired. Upload again.');
        $count=0;foreach($preview['rows'] as $row)if($row['valid']){ins_save($u,$a,'clients',$row['data']);$count++;}unset($_SESSION['ins_import']);$_SESSION['ins_notice']=$count.' clients imported.';return null;
    }
    $f=$_FILES['csv']??[];if(($f['error']??1)!==0||($f['size']??0)>2*1024*1024||!is_uploaded_file($f['tmp_name']))throw new DomainException('Upload a CSV up to 2 MB. Excel: save as CSV first.');
    $fp=fopen($f['tmp_name'],'r');$header=fgetcsv($fp);if(!$header)throw new DomainException('CSV is empty.');$header=array_map(fn($s)=>strtolower(trim(ltrim($s,"\xEF\xBB\xBF"))),$header);
    $map=[];foreach(['name','email','phone','city','notes'] as $key){$column=strtolower(trim((string)($_POST['map_'.$key]??$key)));$map[$key]=array_search($column,$header,true);}if($map['name']===false)throw new DomainException('Mapped name column was not found.');
    $known=ins_records($u,$a,'clients');$emails=[];$phones=[];foreach($known as $c){if($c['data']['email'])$emails[strtolower($c['data']['email'])]=true;if($c['data']['phone'])$phones[preg_replace('/\D/','',$c['data']['phone'])]=true;}
    $rows=[];while(($row=fgetcsv($fp))!==false){if(count($rows)>=500)throw new DomainException('Use batches of at most 500 clients.');$d=['consent'=>'none','assigned_id'=>$u['id']];foreach($map as $key=>$col)$d[$key]=$col===false?'':trim((string)($row[$col]??''));$email=strtolower($d['email']);$phone=preg_replace('/\D/','',$d['phone']);
        $issue=$d['name']===''?'Missing name':($email&&!filter_var($email,FILTER_VALIDATE_EMAIL)?'Invalid email':(($email&&isset($emails[$email]))||($phone&&isset($phones[$phone]))?'Duplicate candidate — skipped':'Ready'));
        if($issue==='Ready'){if($email)$emails[$email]=true;if($phone)$phones[$phone]=true;}$rows[]=['data'=>$d,'valid'=>$issue==='Ready','issue'=>$issue];}
    fclose($fp);$_SESSION['ins_import']=['agency'=>$a['id'],'user'=>$u['id'],'expires'=>time()+900,'rows'=>$rows];return $rows;
}
