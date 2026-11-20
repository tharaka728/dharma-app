<?php
declare(strict_types=1);
namespace App\Support;
final class BusinessCode
{
    public static function format(string $prefix,string $number):string
    {
        if(!in_array($prefix,['P','C','S','A','M','R'],true)||!preg_match('/^[1-9][0-9]{0,17}$/D',$number))throw new \InvalidArgumentException('Invalid business code sequence.');
        return $prefix.str_pad($number,3,'0',STR_PAD_LEFT);
    }
    /** Allocate inside the caller's transaction; never change internal IDs. */
    public static function next(\PDO $pdo,string $prefix,string $key):string
    {
        if(!$pdo->inTransaction()||!in_array($prefix,['P','C','S','A','M','R'],true)||!preg_match('/^[a-zA-Z0-9:_-]{1,100}$/D',$key))throw new \LogicException('Transactional business-code reservation required.');
        $q=$pdo->prepare('SELECT next_number FROM business_code_counters WHERE prefix=? FOR UPDATE');$q->execute([$prefix]);$number=$q->fetchColumn();
        if($number===false)throw new \RuntimeException('Business-code migration required.');
        $old=$pdo->prepare('SELECT code FROM business_code_registry WHERE prefix=? AND request_key=?');$old->execute([$prefix,$key]);$code=$old->fetchColumn();if($code!==false)return $code;
        for($attempt=0;$attempt<1000;$attempt++){
            $code=self::format($prefix,(string)$number);
            $pdo->prepare('UPDATE business_code_counters SET next_number=next_number+1 WHERE prefix=?')->execute([$prefix]);
            $reserved=false;
            if($prefix==='P'){$check=$pdo->prepare('SELECT 1 FROM product_sku_history WHERE sku=? UNION ALL SELECT 1 FROM products WHERE sku=?');$check->execute([$code,$code]);$reserved=(bool)$check->fetchColumn();}
            if($prefix==='C'){$check=$pdo->prepare('SELECT 1 FROM customer_code_history WHERE customer_code=? UNION ALL SELECT 1 FROM customers WHERE customer_code=?');$check->execute([$code,$code]);$reserved=(bool)$check->fetchColumn();}
            if(!$reserved){$pdo->prepare('INSERT INTO business_code_registry(prefix,request_key,sequence_number,code) VALUES (?,?,?,?)')->execute([$prefix,$key,$number,$code]);return $code;}
            $number=(string)((int)$number+1);
        }
        throw new \RuntimeException('Too many reserved codes. Review numbering state.');
    }
    public static function lookup(\PDO $pdo,string $prefix,string $key):?string
    {
        $q=$pdo->prepare('SELECT code FROM business_code_registry WHERE prefix=? AND request_key=?');$q->execute([$prefix,$key]);return $q->fetchColumn()?:null;
    }
    /** Form token stays unchanged on validation/retry; existing UNIQUE/history
     * constraints prevent the same submission creating a second master record. */
    public static function generate(string $prefix,mixed $token=null):string
    {
        if($token===null||$token==='')$token=\App\Services\Inventory::uuid();
        if(!is_string($token)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$token))throw new \InvalidArgumentException('Refresh the create form and try again.');
        return $prefix.'-'.strtoupper(str_replace('-','',$token));
    }
}
