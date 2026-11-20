<?php
declare(strict_types=1);
// CLI-only, read-only source backup. No schema creation, restore or migration.
if(PHP_SAPI!=='cli')exit(1);
$optionFile='C:/mariadb-clean/private/admin.ini';
$source='dharma_sales_phase1';
$directory=dirname(__DIR__).'/storage/historical-return-backups';
try {
    $ini=parse_ini_file($optionFile,true,INI_SCANNER_RAW);
    if(!is_array($ini))throw new RuntimeException('Private database administrator option file is unavailable.');
    $auth=$ini['client']??$ini;
    $pdo=new PDO('mysql:host=127.0.0.1;port=3309;dbname='.$source.';charset=utf8mb4',$auth['user'],$auth['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    unset($auth,$ini);
    if($pdo->query('SELECT DATABASE()')->fetchColumn()!==$source||(int)$pdo->query('SELECT @@port')->fetchColumn()!==3309)throw new RuntimeException('Database target mismatch.');
    $tables=(int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type=\'BASE TABLE\'')->fetchColumn();
    if(!$tables)throw new RuntimeException('Source database has no tables.');
    if(!is_dir($directory)&&!mkdir($directory,0700,true))throw new RuntimeException('Cannot create private backup directory.');
    $backup=$directory.'/application-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql';
    $log=$backup.'.log';
    $command=['C:/xampp/mysql/bin/mysqldump.exe','--defaults-extra-file='.$optionFile,'--protocol=tcp','--host=127.0.0.1','--port=3309','--single-transaction','--routines','--triggers','--hex-blob','--default-character-set=utf8mb4','--skip-add-drop-table','--result-file='.$backup,$source];
    $pipes=[];$process=proc_open($command,[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,null,null,['bypass_shell'=>true,'create_no_window'=>true]);
    if(!is_resource($process))throw new RuntimeException('Unable to start database dump.');
    fclose($pipes[0]);if(proc_close($process)!==0)throw new RuntimeException('Database dump failed; inspect the private log.');
    $dump=file_get_contents($backup);
    if($dump===false||strlen($dump)<1000||substr_count($dump,'CREATE TABLE ')!==$tables||strpos($dump,'Dump completed on')===false)throw new RuntimeException('Backup verification failed; do not migrate.');
    $report=['database'=>$source,'host'=>'127.0.0.1','port'=>3309,'file'=>$backup,'bytes'=>filesize($backup),'tables'=>$tables,'sha256'=>hash_file('sha256',$backup),'dump_complete'=>true,'restore_tested'=>false];
    if(file_put_contents($backup.'.verification.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Cannot write backup verification record.');
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $e) {
    fwrite(STDERR,"Backup failed or database access unavailable. No migration was run.\n");
    exit(1);
}
