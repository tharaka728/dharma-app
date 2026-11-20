<?php
declare(strict_types=1);
// Local CLI only. Source read locks wait for earlier writers and prevent new writes
// until the established dump, isolated restore, and full comparison have completed.
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);$gate=$root.'/storage/historical-backup-maintenance.lock';
if(is_file($gate))throw new RuntimeException('Maintenance gate already exists; do not replace it.');
$f=@fopen($gate,'x');if(!$f)throw new RuntimeException('Cannot create maintenance gate.');
fwrite($f,'Historical return backup verification, UTC '.gmdate('c').PHP_EOL);fclose($f);
$released=false;
$release=static function()use($gate,&$released){if(!$released){$released=true;if(is_file($gate)&&!unlink($gate))fwrite(STDERR,"Maintenance gate could not be removed; manual intervention required.\n");}};
register_shutdown_function($release);
try{
 $ini=parse_ini_file('C:/mariadb-clean/private/admin.ini',true,INI_SCANNER_RAW);$auth=$ini['client']??$ini;
 $db=new PDO('mysql:host=127.0.0.1;port=3309;dbname=dharma_sales_phase1;charset=utf8mb4',$auth['user'],$auth['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);unset($ini,$auth);
 if((int)$db->query('SELECT @@port')->fetchColumn()!==3309||$db->query('SELECT DATABASE()')->fetchColumn()!=='dharma_sales_phase1')throw new RuntimeException('Unexpected database target');
 $tables=$db->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA='dharma_sales_phase1' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);
 if(!$tables)throw new RuntimeException('No source tables.');
 $locks=[];foreach($tables as $table){if($table['ENGINE']!=='InnoDB'||!preg_match('/^[a-zA-Z0-9_]+$/D',$table['TABLE_NAME']))throw new RuntimeException('Unexpected engine or table name.');$locks[]='`'.$table['TABLE_NAME'].'` READ';}
 $db->exec('SET SESSION lock_wait_timeout=30');
 $db->exec('LOCK TABLES '.implode(',',$locks));
 echo 'Application gate active; source table read locks acquired for '.count($tables)." InnoDB tables.\n";
 $command=[PHP_BINARY,$root.'/bin/phase12-backup-restore.php'];$pipes=[];
 $process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,$root,null,['bypass_shell'=>true,'create_no_window'=>true]);
 if(!is_resource($process))throw new RuntimeException('Could not start established verifier.');
 fclose($pipes[0]);$output=stream_get_contents($pipes[1]);fclose($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[2]);$code=proc_close($process);
 $db->exec('UNLOCK TABLES');
 if($code!==0)throw new RuntimeException('Established verification failed: '.trim($error));
 echo $output;
}finally{$release();}
