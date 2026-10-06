<?php
function ins_upload(array $u,array $a,array $file,int $client,string $label): void {
    ins_allow($u,$a,'documents','edit');$c=ins_record($u,$a,$client);if($c['kind']!=='clients'||$c['archived'])throw new DomainException('Choose an active client.');
    if(($file['error']??1)!==UPLOAD_ERR_OK||($file['size']??0)>5*1024*1024||!is_uploaded_file($file['tmp_name']))throw new DomainException('Upload a PDF, JPEG or PNG up to 5 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);if(!in_array($mime,['application/pdf','image/jpeg','image/png'],true))throw new DomainException('Only PDF, JPEG and PNG are supported.');
    $dir=(getenv('INSURANCE_DATA_DIR')?:dirname(__DIR__).'/var').'/documents';if(!is_dir($dir))mkdir($dir,0700,true);$key=bin2hex(random_bytes(24));if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$key))throw new RuntimeException('Upload failed.');chmod($dir.'/'.$key,0600);
    $data=['name'=>trim($label)?:'Document','filename'=>basename($file['name']),'mime'=>$mime,'key'=>$key,'bytes'=>$file['size']];
    ins_query('INSERT INTO records(agency_id,kind,client_id,assigned_id,data,created_at,updated_at) VALUES(?,?,?,?,?,?,?)',[$a['id'],'documents',$client,$c['assigned_id'],ins_json($data),gmdate('c'),gmdate('c')]);ins_audit((int)$a['id'],(int)$u['id'],'document uploaded','client:'.$client);
}
function ins_download(array $u,array $a,int $id): never {
    $r=ins_record($u,$a,$id);if($r['kind']!=='documents'||$r['archived'])throw new DomainException('Document unavailable.');$d=$r['data'];
    if(!preg_match('/^[a-f0-9]{48}$/',$d['key']))throw new DomainException('Invalid document.');$path=(getenv('INSURANCE_DATA_DIR')?:dirname(__DIR__).'/var').'/documents/'.$d['key'];if(!is_file($path))throw new DomainException('Document missing.');
    ins_audit((int)$a['id'],(int)$u['id'],'document downloaded','document:'.$id);header('Content-Type: '.$d['mime']);header('Content-Disposition: attachment; filename="'.preg_replace('/[^a-zA-Z0-9._-]/','_',$d['filename']).'"');header('Content-Length: '.filesize($path));readfile($path);exit;
}
