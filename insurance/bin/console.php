<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/src/auth.php';
try {
    $command=$argv[1]??'help';
    if($command==='init') {
        if(ins_query("SELECT id FROM users WHERE role='platform'")->fetch())throw new DomainException('Platform admin already exists. Use reset instead.');
        $password=getenv('INSURANCE_ADMIN_PASSWORD')?:'';$email=getenv('INSURANCE_ADMIN_EMAIL')?:'';
        ins_create_user(null,$email,'Techdecodes administrator',$password,'platform');echo "Platform administrator created. No sample data was inserted.\n";
    } elseif($command==='reminders') { $n=ins_transaction(fn()=>ins_job());echo "$n in-app notifications created. External channels not connected.\n"; }
    elseif($command==='reset') {
        $email=$argv[2]??'';$u=ins_query('SELECT id FROM users WHERE email=? AND active=1',[strtolower($email)])->fetch();if(!$u)throw new DomainException('Account not found.');$token=bin2hex(random_bytes(32));ins_query('INSERT INTO resets(user_id,hash,expires) VALUES(?,?,?)',[$u['id'],hash('sha256',$token),time()+1800]);echo "One-use reset token (expires in 30 minutes). Share securely with the account owner:\n$token\n";
    } else echo "Commands: init | reminders | reset email@example.com\nSet INSURANCE_DATA_DIR to a private directory outside the web root. Init also needs INSURANCE_ADMIN_EMAIL and INSURANCE_ADMIN_PASSWORD.\n";
}catch(Throwable $e){fwrite(STDERR,$e instanceof DomainException?$e->getMessage()."\n":"Operation failed; check storage permissions and PHP SQLite extension.\n");exit(1);}
