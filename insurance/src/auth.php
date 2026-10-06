<?php
declare(strict_types=1);
require_once __DIR__.'/domain.php';
function ins_limit(string $key,int $max=10): void {
    $key=hash('sha256',$key);$now=time();
    ins_query('INSERT INTO attempts(bucket,count,expires) VALUES(?,1,?) ON CONFLICT(bucket) DO UPDATE SET count=CASE WHEN expires<? THEN 1 ELSE count+1 END,expires=CASE WHEN expires<? THEN ? ELSE expires END',[$key,$now+900,$now,$now,$now+900]);
    if((int)ins_query('SELECT count FROM attempts WHERE bucket=?',[$key])->fetchColumn()>$max)throw new DomainException('Too many attempts. Please try again in 15 minutes.');
}
function ins_login(string $email,string $password): array {
    ins_limit('login-ip:'.($_SERVER['REMOTE_ADDR']??'local'),30);ins_limit('login:'.strtolower(trim($email)),10);
    $u=ins_query('SELECT * FROM users WHERE email=? AND active=1',[strtolower(trim($email))])->fetch();
    if(!$u||!password_verify($password,$u['password']))throw new DomainException('Email or password is incorrect.');
    if($u['agency_id'])ins_agency((int)$u['agency_id']);
    ins_audit($u['agency_id']?(int)$u['agency_id']:null,(int)$u['id'],'login','account');return $u;
}
function ins_create_user(?int $agency,string $email,string $name,string $password,string $role,array $permissions=[]): int {
    $email=strtolower(trim($email));if(!filter_var($email,FILTER_VALIDATE_EMAIL)||trim($name)===''||strlen($password)<12)throw new DomainException('Valid email, name and a password of at least 12 characters are required.');
    if(!in_array($role,['platform','admin','staff'],true)||($role!=='platform'&&!$agency))throw new DomainException('Invalid account role.');
    if(ins_query('SELECT id FROM users WHERE email=?',[$email])->fetch())throw new DomainException('This email is already registered.');
    ins_query('INSERT INTO users(agency_id,email,name,password,role,permissions) VALUES(?,?,?,?,?,?)',[$agency,$email,trim($name),password_hash($password,PASSWORD_DEFAULT),$role,ins_json($permissions)]);return (int)ins_db()->lastInsertId();
}
