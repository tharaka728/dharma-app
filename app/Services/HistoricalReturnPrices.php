<?php
declare(strict_types=1);
namespace App\Services;

use PDO;
use App\Support\InventoryException;
use App\Support\ProductException;

/** Append-only price maintenance. Posting must lock the same gate and re-read revisions. */
final class HistoricalReturnPrices
{
    public function __construct(private PDO $pdo) {}

    /** Read-only approved prices, without product costs or administrative controls. */
    public function approved(array $actor,array $filter): array
    {
        (new Invoices($this->pdo))->actor($actor);
        $year=$filter['year']??null;$search=$filter['q']??'';
        if(!is_string($year)||!preg_match('/^(2024|2025)$/D',$year)||!is_string($search)||mb_strlen($search)>100)
            throw new InventoryException(['filters'=>'Choose a valid year and search term.']);
        $product=$filter['product_id']??null;if($product!==null)Representatives::id($product,'product_id');
        $q=$this->pdo->prepare("SELECT p.id,p.sku,p.name,p.name_si,r.id price_revision_id,r.price_year,r.unit_price FROM products p JOIN historical_return_price_revisions r ON r.id=(SELECT r2.id FROM historical_return_price_revisions r2 WHERE r2.product_id=p.id AND r2.price_year=? ORDER BY r2.revision DESC LIMIT 1) WHERE r.status='APPROVED' AND (p.sku LIKE ? OR p.name LIKE ? OR p.name_si LIKE ?) AND (? IS NULL OR p.id=?) ORDER BY p.name,p.id LIMIT 100");
        $term='%'.trim($search).'%';$q->execute([$year,$term,$term,$term,$product,$product]);return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listing(array $actor,array $filter): array
    {
        (new Invoices($this->pdo))->actor($actor,true);
        $year=$filter['year']??'2024';$search=$filter['q']??'';
        if(!is_string($year)||!preg_match('/^(2024|2025)$/D',$year)||!is_string($search)||mb_strlen($search)>100)
            throw new InventoryException(['filters'=>'Choose a valid year and search term.']);
        $q=$this->pdo->prepare("SELECT p.id,p.sku,p.name,p.name_si,r.id price_id,r.revision,r.unit_price,r.status,r.created_at_utc,u.name updated_by FROM products p LEFT JOIN historical_return_price_revisions r ON r.id=(SELECT r2.id FROM historical_return_price_revisions r2 WHERE r2.product_id=p.id AND r2.price_year=? ORDER BY r2.revision DESC LIMIT 1) LEFT JOIN users u ON u.id=r.actor_user_id WHERE p.sku LIKE ? OR p.name LIKE ? OR p.name_si LIKE ? ORDER BY p.name,p.id LIMIT 500");
        $term='%'.trim($search).'%';$q->execute([$year,$term,$term,$term]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function history(array $actor,string $product,string $year): array
    {
        (new Invoices($this->pdo))->actor($actor,true);Representatives::id($product,'product_id');
        if(!preg_match('/^(2024|2025)$/D',$year))throw new InventoryException(['year'=>'Choose 2024 or 2025.']);
        $q=$this->pdo->prepare('SELECT r.*,u.name updated_by FROM historical_return_price_revisions r JOIN users u ON u.id=r.actor_user_id WHERE r.product_id=? AND r.price_year=? ORDER BY r.revision DESC');$q->execute([$product,$year]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(array $actor,array $input): array
    {
        $product=Representatives::id($input['product_id']??null,'product_id');
        $year=$input['price_year']??null;
        if(!is_string($year)||!preg_match('/^(2024|2025)$/D',$year))
            throw new InventoryException(['price_year'=>'Choose 2024 or 2025.']);
        try{$price=Products::money($input['unit_price']??null);}catch(ProductException $e){throw new InventoryException(['unit_price'=>'Enter a nonnegative LKR price with at most two decimal places.']);}
        $reason=Inventory::text($input['reason']??null,'reason',500,true);
        $status=$input['status']??'APPROVED';
        if(!in_array($status,['APPROVED','DISABLED'],true))throw new InventoryException(['status'=>'Choose a valid price status.']);
        $own=!$this->pdo->inTransaction();
        if($own)$this->pdo->beginTransaction();else $this->pdo->exec('SAVEPOINT historical_price');
        try{
            if(!$this->pdo->query('SELECT id FROM inventory_settings WHERE id=1 FOR UPDATE')->fetchColumn())throw new InventoryException(['stock'=>'Inventory settings are unavailable.'],409);
            (new Invoices($this->pdo))->actor($actor,true);
            $q=$this->pdo->prepare('SELECT id FROM products WHERE id=? FOR UPDATE');$q->execute([$product]);
            if(!$q->fetch())throw new InventoryException(['product_id'=>'Choose an existing product.']);
            $q=$this->pdo->prepare('SELECT * FROM historical_return_price_revisions WHERE product_id=? AND price_year=? ORDER BY revision DESC LIMIT 1 FOR UPDATE');$q->execute([$product,$year]);$old=$q->fetch(PDO::FETCH_ASSOC);
            if(($input['expected_revision']??'')!==($old?(string)$old['revision']:''))throw new InventoryException(['version'=>'Historical price changed. Reload before saving.'],409);
            if($old&&$old['status']==='APPROVED'&&($input['confirm_change']??null)!=='1')throw new InventoryException(['confirm_change'=>'Confirm the change to the approved price.']);
            $revision=$old?(int)$old['revision']+1:1;
            $q=$this->pdo->prepare('INSERT INTO historical_return_price_revisions(product_id,price_year,revision,unit_price,status,reason,actor_user_id) VALUES (?,?,?,?,?,?,?)');
            $q->execute([$product,$year,$revision,$price,$status,$reason,$actor['id']]);
            $id=(string)$this->pdo->lastInsertId();
            $q=$this->pdo->prepare('INSERT INTO audit_log(actor_user_id,action,entity_type,entity_key,reason,before_json,after_json) VALUES (?,?,?,?,?,?,?)');
            $q->execute([$actor['id'],'HISTORICAL_RETURN_PRICE','historical_return_price_revisions',$id,$reason,$old?json_encode($old,JSON_THROW_ON_ERROR):null,json_encode(['product_id'=>$product,'price_year'=>$year,'revision'=>$revision,'unit_price'=>$price,'status'=>$status],JSON_THROW_ON_ERROR)]);
            if($own)$this->pdo->commit();else $this->pdo->exec('RELEASE SAVEPOINT historical_price');
            return ['id'=>$id,'revision'=>(string)$revision];
        }catch(\Throwable $e){if($own&&$this->pdo->inTransaction())$this->pdo->rollBack();elseif(!$own&&$this->pdo->inTransaction())$this->pdo->exec('ROLLBACK TO SAVEPOINT historical_price');throw $e;}
    }
}
