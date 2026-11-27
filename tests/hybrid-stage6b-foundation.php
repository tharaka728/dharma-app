<?php
declare(strict_types=1);
require __DIR__.'/hybrid-isolation.php';require __DIR__.'/hybrid-stage6b-installer.php';
$m=HybridIsolationHarness::manifest($argv[1]??'');$admin=HybridIsolationHarness::connection($m,'admin');
Stage6bFreshInstaller::bindScope($m);
$name='dharma_stage6b_foundation_'.bin2hex(random_bytes(8)).'_test';$admin->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$admin->exec('USE `'.$name.'`');Stage6bFreshInstaller::run($admin);
$user='fdn_'.bin2hex(random_bytes(5));$password=bin2hex(random_bytes(24));$account=$admin->quote($user)."@'127.0.0.1'";$admin->exec('CREATE USER '.$account.' IDENTIFIED BY '.$admin->quote($password));$admin->exec('GRANT ALL PRIVILEGES ON `'.$name.'`.* TO '.$account);
$p=HybridIsolationHarness::pdo($m,$user,$password,false);$p->exec('USE `'.$name.'`');$grants=$p->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);if(count($grants)!==2)throw new RuntimeException('Unexpected foundation grants.');foreach($grants as$g)if(!str_starts_with($g,'GRANT USAGE ON *.* TO ')&&!str_starts_with($g,'GRANT ALL PRIVILEGES ON `'.$name.'`.* TO '))throw new RuntimeException('Foundation grants exceeded schema.');
$env=getenv();foreach(['DB_HOST'=>'127.0.0.1','DB_PORT'=>(string)$m['port'],'DB_NAME'=>$name,'DB_USER'=>$user,'DB_PASSWORD'=>$password]as$k=>$v)$env[$k]=$v;
$pipes=[];$process=proc_open([PHP_BINARY,__DIR__.'/database.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,APP_ROOT,$env,['bypass_shell'=>true,'create_new_console'=>false]);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);echo$out;if($exit!==0)throw new RuntimeException($error?:'Foundation database suite failed.');file_put_contents($m['directory'].'/stage6b-foundation.log',$out);
