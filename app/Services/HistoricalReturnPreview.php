<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
/** Explicit, server-side read-only preview scoped to one authenticated representative. */
final class HistoricalReturnPreview
{
    public static function search(PDO $pdo,array $actor,array $configuration,array $filter):array
    {
        if(!self::allowed($pdo,$actor,$configuration))throw new \App\Support\InventoryException(['access'=>'Preview is unavailable.'],403);
        $year=$filter['year']??null;$term=$filter['q']??'';
        if(!is_string($year)||!in_array($year,['2024','2025'],true)||!is_string($term)||!mb_check_encoding($term,'UTF-8')||mb_strlen($term,'UTF-8')>100)throw new \App\Support\InventoryException(['filters'=>'Choose a valid year and product search.']);
        $term=trim($term);if($term==='')return [];
        $like='%'.strtr($term,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
        $sql="SELECT p.id,p.sku,p.name,p.name_si,r.unit_price FROM products p LEFT JOIN historical_return_price_revisions r ON r.id=(SELECT r2.id FROM historical_return_price_revisions r2 WHERE r2.product_id=p.id AND r2.price_year=? ORDER BY r2.revision DESC LIMIT 1) AND r.status='APPROVED' WHERE p.is_active=1 AND (p.sku LIKE ? ESCAPE '!' OR p.name LIKE ? ESCAPE '!' OR p.name_si LIKE ? ESCAPE '!') ORDER BY p.name,p.id LIMIT 20";
        $q=$pdo->prepare($sql);$q->execute([$year,$like,$like,$like]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$row)$row['id']=(string)$row['id'];unset($row);return $rows;
    }
    public static function allowed(PDO $pdo,array $actor,array $configuration):bool
    {
        $preview=$configuration['preview']??[];
        if(($preview['enabled']??false)!==true || !is_string($preview['database']??null) ||
           !is_string($preview['representative_id']??null) ||
           !preg_match('/^[1-9][0-9]{0,18}$/D',$preview['representative_id']) ||
           (string)($actor['id']??'')!==$preview['representative_id'])return false;
        if($pdo->query('SELECT DATABASE()')->fetchColumn()!==$preview['database'] || (int)$pdo->query('SELECT @@port')->fetchColumn()!==3309)return false;
        $q=$pdo->prepare("SELECT COUNT(*) FROM users WHERE id=? AND role='SALES_REP' AND is_active=1");$q->execute([$preview['representative_id']]);
        if((int)$q->fetchColumn()!==1)return false;
        $q=$pdo->prepare("SELECT COUNT(*) FROM schema_migrations WHERE version IN (?,?,?,?) AND status='applied'");
        $q->execute(['020_historical_return_prices.sql','021_historical_customer_returns.sql','022_historical_return_identity.sql','023_historical_return_intents.sql']);
        return (int)$q->fetchColumn()===4;
    }
}
