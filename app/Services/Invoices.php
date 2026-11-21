<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use App\Support\InventoryException;use App\Support\ProductException;
use App\Services\PromotionCalculator as Money;

/** Online posting reuses the foundation invoices, payments and immutable stock ledger. */
final class Invoices
{
    private ?bool $returnSchema=null;
    public function __construct(private PDO $pdo){}
    private function one(string $sql,array $values=[]):?array{$q=$this->pdo->prepare($sql);$q->execute($values);return $q->fetch()?:null;}
    private function rows(string $sql,array $values=[]):array{$q=$this->pdo->prepare($sql);$q->execute($values);return $q->fetchAll();}
    public function historicalReturnEnabled():bool
    {
        $database=$this->pdo->query('SELECT DATABASE()')->fetchColumn();
        $testing=getenv('HISTORICAL_RETURN_TEST')==='1'&&$database==='dharma_sales_phase12_restore_20261118154343';
        $config=require APP_ROOT.'/config/historical-returns.php';
        $dbConfig=require APP_ROOT.'/config/database.php';
        $operational=$database==='dharma_sales_phase1'&&($dbConfig['host']??null)==='127.0.0.1'&&(string)($dbConfig['port']??'')==='3309'&&(int)$this->pdo->query('SELECT @@port')->fetchColumn()===3309&&($config['enabled']??false)===true;
        if((!$testing&&!$operational)||!$this->returnSchema())return false;
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE version IN (?,?,?,?) AND status=\'applied\'');$q->execute(['020_historical_return_prices.sql','021_historical_customer_returns.sql','022_historical_return_identity.sql','023_historical_return_intents.sql']);return (int)$q->fetchColumn()===4;
    }
    /** Server-side financial pilot, independent of preview authorization. */
    public function historicalReturnAllowed(array $actor):bool
    {
        if(!$this->historicalReturnEnabled())return false;
        $config=require APP_ROOT.'/config/historical-returns.php';$pilot=$config['pilot']??null;
        if(!is_array($pilot)||($pilot['enabled']??false)!==true||!is_string($pilot['representative_id']??null)||!preg_match('/^[1-9][0-9]{0,18}$/D',$pilot['representative_id'])||!is_string($pilot['username']??null)||$pilot['username']!=='nissanka')return false;
        $id=$pilot['representative_id'];$username=$pilot['username'];
        // Existing isolated-test mode cannot target the operational database.
        if($this->pdo->query('SELECT DATABASE()')->fetchColumn()==='dharma_sales_phase12_restore_20261118154343'&&getenv('HISTORICAL_RETURN_TEST')==='1'){
            $testId=getenv('HISTORICAL_RETURN_TEST_REP_ID');if(!is_string($testId)||!preg_match('/^[1-9][0-9]{0,18}$/D',$testId))return false;$id=$testId;$username=null;
        }
        if((string)($actor['id']??'')!==$id)return false;
        $u=$this->one('SELECT id,username,role,is_active FROM users WHERE id=?',[$id]);
        return $u!==null&&(int)$u['is_active']===1&&$u['role']==='SALES_REP'&&($username===null||$u['username']===$username);
    }
    public function requireHistoricalReturnAccess(array $actor):void
    {
        if(!$this->historicalReturnAllowed($actor))throw new InventoryException(['return'=>'Historical return checkout is not available for this account.'],403);
    }
    public function historicalReturnPreviewAllowed(array $actor):bool
    {
        return HistoricalReturnPreview::allowed($this->pdo,$actor,require APP_ROOT.'/config/historical-returns.php');
    }
    private function returnSchema():bool
    {
        if($this->returnSchema===null){$q=$this->pdo->prepare('SELECT status FROM schema_migrations WHERE version=?');$q->execute(['021_historical_customer_returns.sql']);$this->returnSchema=$q->fetchColumn()==='applied';}return $this->returnSchema;
    }
    public function actor(array $actor,bool $admin=false):array
    {
        $u=$this->one('SELECT id,name,role,is_active FROM users WHERE id=?',[$actor['id']??'0']);
        if(!$u||!(int)$u['is_active']||!in_array($u['role'],$admin?['ADMIN']:['ADMIN','SALES_REP'],true))throw new InventoryException(['access'=>'You do not have permission for this operation.'],403);
        return $u;
    }
    private static function uuid(mixed $v):string
    {
        if(!is_string($v)||!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D',$v))throw new InventoryException(['request_id'=>'Refresh the form to obtain a valid request ID.']);return $v;
    }
    private static function amount(mixed $v):string{try{return Products::money($v);}catch(ProductException $e){throw new InventoryException(['amount'=>'Enter a nonnegative LKR decimal with at most two decimal places.']);}}
    private function tx(callable $fn):array
    {
        $own=!$this->pdo->inTransaction();
        for($try=0;;$try++){
            if($own){$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');$this->pdo->beginTransaction();}else $this->pdo->exec('SAVEPOINT invoice_command');
            try{$result=$fn();if($own)$this->pdo->commit();else $this->pdo->exec('RELEASE SAVEPOINT invoice_command');return $result;}
            catch(\Throwable $e){if($own&&$this->pdo->inTransaction())$this->pdo->rollBack();elseif(!$own&&$this->pdo->inTransaction())$this->pdo->exec('ROLLBACK TO SAVEPOINT invoice_command');$code=$e instanceof \PDOException?(int)($e->errorInfo[1]??0):0;if($own&&in_array($code,[1205,1213],true)&&$try<2)continue;if(in_array($code,[1205,1213,1062],true))throw new InventoryException(['conflict'=>'Concurrent request conflict. Retry with the same request ID.'],409);throw $e;}
        }
    }
    private function gate():array
    {
        $s=$this->one('SELECT s.*,l.type location_type,l.is_active location_active FROM inventory_settings s LEFT JOIN stock_locations l ON l.id=s.warehouse_location_id WHERE s.id=1 FOR UPDATE');
        if(!$s||$s['stock_model']!=='PRODUCT'||$s['location_type']!=='WAREHOUSE'||!(int)$s['location_active'])throw new InventoryException(['stock'=>'Active product-level inventory is required.'],409);
        return $s;
    }
    private function audit(array $actor,string $action,string $id,array $data,?string $reason=null):void
    {
        $entity=$action==='CUSTOMER_BILLING_SETTINGS'?'customers':'invoices';
        $this->pdo->prepare('INSERT INTO audit_log(actor_user_id,action,entity_type,entity_key,reason,after_json) VALUES (?,?,?,?,?,?)')->execute([$actor['id'],$action,$entity,$id,$reason,json_encode($data,JSON_THROW_ON_ERROR)]);
    }
    public function customerSettings(array $actor,array $input):array
    {
        $this->actor($actor,true);$id=Representatives::id($input['customer_id']??null,'customer_id');$rep=$input['assigned_rep_id']??'';$credit=$input['allow_credit_sales']??'0';
        if(!is_string($rep)||!in_array($credit,['0','1'],true))throw new InventoryException(['settings'=>'Choose valid customer billing settings.']);
        if($rep!=='')$rep=Representatives::id($rep,'assigned_rep_id');
        return $this->tx(function()use($actor,$input,$id,$rep,$credit){$this->gate();$this->actor($actor,true);
            if($rep!==''){$r=$this->one("SELECT id FROM users WHERE id=? AND role='SALES_REP' AND is_active=1 FOR UPDATE",[$rep]);if(!$r)throw new InventoryException(['assigned_rep_id'=>'Choose an active representative.']);}
            $c=$this->one('SELECT version,assigned_rep_id,allow_credit_sales FROM customers WHERE id=? FOR UPDATE',[$id]);if(!$c)throw new InventoryException(['customer'=>'Customer not found.'],404);
            if(($input['version']??null)!==(string)$c['version'])throw new InventoryException(['version'=>'Customer changed. Reload before saving.'],409);
            $this->pdo->prepare('UPDATE customers SET assigned_rep_id=?,allow_credit_sales=?,version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE id=?')->execute([$rep===''?null:$rep,$credit,$id]);
            $this->audit($actor,'CUSTOMER_BILLING_SETTINGS',$id,['before'=>$c,'assigned_rep_id'=>$rep?:null,'allow_credit_sales'=>$credit]);return ['customer_id'=>$id];
        });
    }
    public function setPrefix(array $actor,array $input):array
    {
        $this->actor($actor,true);$prefix=$input['business_prefix']??null;if(!is_string($prefix)||!preg_match('/^[A-Z][A-Z0-9-]{0,11}$/D',$prefix))throw new InventoryException(['business_prefix'=>'Use 1–12 uppercase letters, digits or hyphens, starting with a letter.']);
        return $this->tx(function()use($actor,$input,$prefix){$this->gate();$this->actor($actor,true);$s=$this->one('SELECT * FROM invoice_settings WHERE id=1 FOR UPDATE');if(($input['version']??null)!==(string)$s['version'])throw new InventoryException(['version'=>'Number settings changed. Reload.'],409);$this->pdo->prepare('UPDATE invoice_settings SET business_prefix=?,version=version+1 WHERE id=1')->execute([$prefix]);$this->audit($actor,'INVOICE_PREFIX_CHANGED','settings',['before'=>$s,'business_prefix'=>$prefix]);return ['business_prefix'=>$prefix];});
    }
    public function options(array $actor,?string $source=null,bool $includeInactive=false,?array $selectedProducts=null):array
    {
        $a=$this->actor($actor);$own=$a['role']==='SALES_REP';$params=[];$scope='';if($own){$scope=' AND assigned_rep_id=?';$params[]=$a['id'];}
        $customers=$this->rows('SELECT id,customer_code,shop_name,address,city_town,district,credit_terms_days,allow_credit_sales,credit_limit_enabled,credit_limit FROM customers WHERE '.($includeInactive?'1=1':'is_active=1').$scope.' ORDER BY shop_name,id',$params);
        $reps=$own?[]:$this->rows("SELECT id,name FROM users WHERE role='SALES_REP'".($includeInactive?'':' AND is_active=1').' ORDER BY name,id');
        $rep=$own?(string)$a['id']:($source??'');$productSql='SELECT p.id,p.sku,p.name,p.name_si,p.selling_price,COALESCE(c.name,\'\') category FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.is_active=1';
        $ids=$selectedProducts===null?null:array_values(array_filter(array_slice($selectedProducts,0,100),static fn($id)=>is_string($id)&&preg_match('/^[1-9][0-9]{0,18}$/D',$id)));
        $products=$ids===[]?[]:$this->rows($productSql.($ids===null?'':' AND p.id IN ('.implode(',',array_fill(0,count($ids),'?')).')').' ORDER BY p.name,p.id',$ids??[]);
        $balances=[];if($rep==='warehouse'){$balances=$this->rows('SELECT product_id,quantity FROM product_stock_balances');}elseif($rep!==''&&preg_match('/^[1-9][0-9]{0,18}$/D',$rep)){$balances=$this->rows('SELECT product_id,quantity FROM representative_stock_balances WHERE rep_id=?',[$rep]);}
        $map=array_column($balances,'quantity','product_id');foreach($products as &$p){$p['available_quantity']=(string)($map[$p['id']]??'0');$p['id']=(string)$p['id'];}unset($p);
        foreach($customers as &$c)$c['id']=(string)$c['id'];unset($c);foreach($reps as &$r)$r['id']=(string)$r['id'];unset($r);
        return ['customers'=>$customers,'representatives'=>$reps,'products'=>$products,'settings'=>$own?null:$this->one('SELECT * FROM invoice_settings WHERE id=1')];
    }
    /** Bounded, role-scoped catalogue for the POS; never includes cost prices. */
    public function catalogue(array $actor,array $filter):array
    {
        $a=$this->actor($actor);$source=$a['role']==='SALES_REP'?(string)$a['id']:($filter['stock_source']??'');
        if(!is_string($source))throw new InventoryException(['stock_source'=>'Choose a valid stock source.']);
        $params=[];$join='';$quantity='0';
        if($source==='warehouse'){$join=' LEFT JOIN product_stock_balances b ON b.product_id=p.id';$quantity='COALESCE(b.quantity,0)';}
        elseif($source!==''){$rep=Representatives::id($source,'stock_source');if(!$this->one("SELECT id FROM users WHERE id=? AND role='SALES_REP' AND is_active=1",[$rep]))throw new InventoryException(['stock_source'=>'Choose an active representative.']);$join=' LEFT JOIN representative_stock_balances b ON b.product_id=p.id AND b.rep_id=?';$params[]=$rep;$quantity='COALESCE(b.quantity,0)';}
        $q=$filter['q']??'';$category=$filter['category']??'';$page=$filter['page']??'1';
        if(!is_string($q)||mb_strlen($q)>100||!is_string($category)||mb_strlen($category)>100||!is_string($page)||!preg_match('/^[1-9][0-9]{0,5}$/D',$page))throw new InventoryException(['catalogue'=>'Invalid catalogue filters.']);
        $where=' WHERE p.is_active=1';if(trim($q)!==''){$where.=' AND (p.name LIKE ? OR p.sku LIKE ? OR p.name_si LIKE ?)';$params[]='%'.trim($q).'%';$params[]='%'.trim($q).'%';$params[]='%'.trim($q).'%';}if($category!==''){$where.=' AND c.name=?';$params[]=$category;}
        $offset=((int)$page-1)*12;
        $rows=$this->rows('SELECT p.id,p.sku,p.name,p.name_si,p.selling_price,COALESCE(c.name,\'\') category,'.$quantity.' available_quantity FROM products p LEFT JOIN categories c ON c.id=p.category_id'.$join.$where.' ORDER BY p.name,p.id LIMIT 13 OFFSET '.$offset,$params);
        $more=count($rows)>12;$rows=array_slice($rows,0,12);foreach($rows as &$row){$row['id']=(string)$row['id'];$row['available_quantity']=(string)$row['available_quantity'];if(ProductImages::exists($row['id']))$row['image_url']=\App\Controllers\AuthController::url('/product-image/'.$row['id']);}unset($row);
        return ['products'=>$rows,'categories'=>array_column($this->rows('SELECT DISTINCT c.name FROM categories c JOIN products p ON p.category_id=c.id WHERE p.is_active=1 ORDER BY c.name'),'name'),'page'=>(int)$page,'has_more'=>$more];
    }
    public function customers(array $actor):array{return $this->options($actor)['customers'];}
    public function historicalReturnsForCustomer(array $actor,string $customer):array
    {
        $a=$this->actor($actor);Representatives::id($customer,'customer_id');
        $c=$this->one('SELECT assigned_rep_id FROM customers WHERE id=?',[$customer]);
        if(!$c||($a['role']==='SALES_REP'&&(string)$c['assigned_rep_id']!==(string)$a['id']))throw new InventoryException(['customer'=>'Customer is not available to you.'],403);
        if(!$this->returnSchema())return [];
        $scope=$a['role']==='SALES_REP'?' AND i.rep_id=?':'';$params=[$customer];if($scope)$params[]=$a['id'];
        return $this->rows("SELECT i.id invoice_id,i.invoice_number,i.business_date,hr.approved_value,hr.applied_value,hr.forfeited_value,u.name representative,rl.price_year,rl.unit_price,rl.quantity,rl.line_value,rl.disposition,p.sku,p.name,p.name_si,IF(v.invoice_id IS NULL,'ISSUED','VOIDED') validity FROM historical_customer_returns hr JOIN invoices i ON i.id=hr.invoice_id JOIN users u ON u.id=hr.actor_user_id JOIN historical_customer_return_lines rl ON rl.invoice_id=hr.invoice_id JOIN products p ON p.id=rl.product_id LEFT JOIN invoice_voids v ON v.invoice_id=i.id WHERE hr.customer_id=?".$scope.' ORDER BY i.business_date DESC,i.invoice_number DESC,rl.line_number LIMIT 100',$params);
    }
    private function normalize(array $actor,array $input):array
    {
        foreach(['benefit_mode','benefit_policy','promotion_discount_percent'] as $simulationField)if(array_key_exists($simulationField,$input))throw new InventoryException(['benefit'=>'Benefit simulation is not available for checkout.'],409);
        $a=$this->actor($actor);$customer=Representatives::id($input['customer_id']??null,'customer_id');$source=$input['stock_source']??'';
        if($a['role']==='SALES_REP'){$expected=(string)$a['id'];if($source!==''&&$source!==$expected)throw new InventoryException(['stock_source'=>'Representatives may sell only their own stock.'],403);$source=$expected;}
        elseif($source!=='warehouse')$source=Representatives::id($source,'stock_source');
        $type=$input['payment_type']??null;if(!in_array($type,['CASH','CREDIT'],true))throw new InventoryException(['payment_type'=>'Choose cash or credit.']);
        $received=self::amount($input['amount_received']??'0.00');$items=$input['items']??null;if(!is_array($items)||count($items)<1||count($items)>100)throw new InventoryException(['items'=>'Add 1–100 different products.']);$lines=[];
        foreach($items as $item){if($item instanceof \stdClass)$item=(array)$item;if(!is_array($item))throw new InventoryException(['items'=>'Invalid product line.']);$p=Representatives::id($item['product_id']??null,'product_id');$qty=(string)Inventory::quantity($item['quantity']??null);if(isset($lines[$p]))throw new InventoryException(['items'=>'Each product may appear only once.']);$lines[$p]=['product_id'=>$p,'quantity'=>$qty];}
        $oil=MixedOil::rewards($input['oil_rewards']??[]);
        $lineOrder=array_map('strval',array_keys($lines));uksort($lines,static fn($a,$b)=>Money::compare((string)$a,(string)$b));
        $normalized=['customer_id'=>$customer,'stock_source'=>$source,'payment_type'=>$type,'amount_received'=>$received,'items'=>array_values($lines)];
        if(array_key_exists('historical_return',$input))$normalized['historical_return']=HistoricalCustomerReturns::normalize($input['historical_return']);
        if($oil)$normalized['oil_rewards']=$oil;
        if(array_key_exists('invoice_discount_percent',$input)||array_key_exists('invoice_discount_choice',$input)||array_key_exists('discount_policy',$input)){
            $choice=$input['invoice_discount_choice']??'custom';if(!is_string($choice)||!in_array($choice,['0','25','30','35','custom'],true))throw new InventoryException(['invoice_discount_percent'=>'Choose a listed invoice discount.']);
            if(($input['discount_policy']??null)!==InvoiceDiscount::POLICY)throw new InventoryException(['discount_policy'=>'Unsupported invoice discount policy. Refresh the application.'],409);
            $normalized['invoice_discount_percent']=InvoiceDiscount::rate($choice==='custom'?($input['invoice_discount_percent']??null):$choice);$normalized['discount_policy']=InvoiceDiscount::POLICY;$normalized['line_order']=$lineOrder;
        }
        return $normalized;
    }
    private function customer(array $a,string $id,bool $active=true):array
    {
        $c=$this->one('SELECT * FROM customers WHERE id=? FOR UPDATE',[$id]);if(!$c||($a['role']==='SALES_REP'&&(string)$c['assigned_rep_id']!==(string)$a['id']))throw new InventoryException(['customer'=>'Customer is not available to you.'],403);
        if($active&&!(int)$c['is_active'])throw new InventoryException(['customer'=>'Choose an active customer.']);return $c;
    }
    public function outstanding(array $actor,string $customer):string
    {
        $a=$this->actor($actor);$c=$this->one('SELECT assigned_rep_id FROM customers WHERE id=?',[$customer]);if(!$c||($a['role']==='SALES_REP'&&(string)$c['assigned_rep_id']!==(string)$a['id']))throw new InventoryException(['customer'=>'Customer is not available to you.'],403);
        $settlement=$this->returnSchema()?'COALESCE((SELECT hr.applied_value FROM historical_customer_returns hr WHERE hr.invoice_id=i.id),0)':'0';
        $row=$this->one("SELECT COALESCE(SUM(i.grand_total-COALESCE(p.paid,0)-$settlement),0.00) amount FROM invoices i LEFT JOIN invoice_voids v ON v.invoice_id=i.id LEFT JOIN (SELECT a.invoice_id,SUM(a.amount) paid FROM payment_allocations a JOIN payments p ON p.id=a.payment_id WHERE p.status='POSTED' GROUP BY a.invoice_id) p ON p.invoice_id=i.id WHERE i.customer_id=? AND v.invoice_id IS NULL",[$customer]);return (string)$row['amount'];
    }
    private function balance(array $s,string $source,string $product,bool $reversing=false):array
    {
        if($source==='warehouse'){$loc=(string)$s['warehouse_location_id'];$b=$this->one('SELECT quantity FROM product_stock_balances WHERE product_id=? FOR UPDATE',[$product]);}
        else{$l=$this->one("SELECT id,is_active FROM stock_locations WHERE rep_id=? AND type='REP' FOR UPDATE",[$source]);if(!$l||(!$reversing&&!(int)$l['is_active']))throw new InventoryException(['stock'=>'Representative stock location is unavailable.'],409);$loc=(string)$l['id'];$b=$this->one('SELECT quantity FROM representative_stock_balances WHERE rep_id=? AND product_id=? FOR UPDATE',[$source,$product]);}
        if($reversing&&!$b)throw new InventoryException(['reconciliation'=>'Original stock balance is missing. Reversal blocked.'],409);
        $quantity=(string)($b['quantity']??'0');$ledger=$this->one('SELECT COALESCE(SUM(quantity_delta),0) quantity FROM inventory_movements WHERE location_id=? AND product_id=? AND batch_id IS NULL FOR UPDATE',[$loc,$product]);if((string)$ledger['quantity']!==$quantity)throw new InventoryException(['reconciliation'=>'Stock does not reconcile. Posting is blocked.'],409);return [$loc,$quantity];
    }
    private function reviewLocked(array $actor,array $n):array
    {
        $s=$this->gate();$a=$this->actor($actor);$source=$n['stock_source'];
        if($a['role']==='SALES_REP'&&$source!==(string)$a['id'])throw new InventoryException(['access'=>'Only your own stock may be invoiced.'],403);
        if($source!=='warehouse'){
            $r=$this->one("SELECT id,name FROM users WHERE id=? AND role='SALES_REP' AND is_active=1 FOR UPDATE",[$source]);if(!$r)throw new InventoryException(['stock_source'=>'Choose an active representative.']);
            $this->pdo->prepare('INSERT INTO rep_operating_state(rep_id) VALUES (?) ON DUPLICATE KEY UPDATE rep_id=rep_id')->execute([$source]);$this->one('SELECT rep_id FROM rep_operating_state WHERE rep_id=? FOR UPDATE',[$source]);
        }
        $c=$this->customer($a,$n['customer_id']);$this->one('SELECT id FROM promotion_write_gate WHERE id=1 FOR UPDATE');
        if($this->one('SELECT batch_id FROM inventory_balances WHERE quantity<>0 LIMIT 1 FOR UPDATE')||$this->one('SELECT id FROM inventory_movements WHERE batch_id IS NOT NULL LIMIT 1 FOR UPDATE'))throw new InventoryException(['legacy'=>'Legacy batch stock requires a reviewed reconciliation.'],409);
        $lines=[];$gross='0.00';$discount='0.00';$total='0.00';$loc=null;$instant=new \DateTimeImmutable('now',new \DateTimeZone('UTC'));
        // Promotion gate is held. Discover group members before locking every product in ID order.
        $promotionService=new Promotions($this->pdo);$rules=[];$ids=array_column($n['items'],'product_id');
        foreach($n['items'] as $item){$q=$promotionService->quote($a,$item['product_id'],$item['quantity'],$instant);$rule=$q['rule_snapshot'];if(isset($rule['mixed_product_ids'])){$rules[$rule['revision_id']]=$rule;$ids=array_merge($ids,$rule['mixed_product_ids']);}}
        $ids=array_values(array_unique($ids));usort($ids,fn($a,$b)=>Money::compare($a,$b));$products=[];
        foreach($ids as $id){$p=$this->one('SELECT id,sku,name,name_si,selling_price,version,is_active FROM products WHERE id=? FOR UPDATE',[$id]);if(!$p||!(int)$p['is_active'])throw new InventoryException(['product'=>'Choose active products only.']);$products[$id]=$p;}
        foreach($n['items'] as $item){$p=$products[$item['product_id']];$line=$promotionService->quote($a,$item['product_id'],$item['quantity'],$instant);$line['product_name']=$p['name'];$line['name_si']=$p['name_si']??null;$line['sku']=$p['sku'];$lines[]=$line;}
        foreach($ids as $id){[$loc,$qty]=$this->balance($s,$source,$id);$products[$id]['available_quantity']=$qty;}
        $selected=$n['oil_rewards']??[];$recognized=[];$offers=[];
        foreach($rules as $rule){$offers[]=MixedOil::plan($rule,$lines,$products,$selected);foreach($rule['mixed_product_ids'] as $id)$recognized[$id]=$rule;}
        foreach($selected as $id=>$qty){$id=(string)$id;if(!isset($recognized[$id]))throw new InventoryException(['oil_rewards'=>'Selected free oil is not eligible for a qualifying paid oil group.'],409);$rule=$recognized[$id];$found=false;
            foreach($lines as &$line)if($line['product_id']===$id){if($line['rule_snapshot']['revision_id']!==$rule['revision_id'])throw new InventoryException(['oil_rewards'=>'Automatic offers cannot stack.'],409);$line['free_quantity']=$qty;if((int)$qty>PHP_INT_MAX-(int)$line['paid_quantity'])throw new InventoryException(['quantity'=>'Paid plus free quantity exceeds supported range.']);$line['required_stock']=(string)((int)$line['paid_quantity']+(int)$qty);$found=true;break;}unset($line);
            if(!$found){$p=$products[$id];$q=$promotionService->quote($a,$id,'1',$instant);if(($q['rule_snapshot']['revision_id']??null)!==$rule['revision_id'])throw new InventoryException(['oil_rewards'=>'Conflicting oil offer.'],409);$q['paid_quantity']='0';$q['free_quantity']=$qty;$q['required_stock']=$qty;foreach(['gross_amount','discount_amount','payable_amount'] as $k)$q[$k]='0.00';$q['product_name']=$p['name'];$q['sku']=$p['sku'];$lines[]=$q;}
        }
        if(count($lines)>100)throw new InventoryException(['items'=>'Paid and reward products together may not exceed 100.']);
        foreach($lines as &$line){$qty=$products[$line['product_id']]['available_quantity'];if(Money::compare($line['required_stock'],$qty)>0)throw new InventoryException(['stock'=>'Insufficient stock for '.$line['sku'].', including free promotional units.'],409);$line['available_quantity']=$qty;$gross=Money::sumMoney($gross,$line['gross_amount']);$discount=Money::sumMoney($discount,$line['discount_amount']);$total=Money::sumMoney($total,$line['payable_amount']);}unset($line);
        $discountSpec=null;if(isset($n['discount_policy'])){
            $order=array_flip($n['line_order']);usort($lines,static fn($a,$b)=>($order[$a['product_id']]??PHP_INT_MAX)<=>($order[$b['product_id']]??PHP_INT_MAX)?:Money::compare($a['product_id'],$b['product_id']));
            $discountSpec=InvoiceDiscount::apply($lines,$n['invoice_discount_percent']);$lines=$discountSpec['lines'];$total=Money::subtractMoney($total,$discountSpec['invoice_discount_amount']);$automatic=$discount;$discount=Money::sumMoney($discount,$discountSpec['invoice_discount_amount']);
        }
        $returnValue=null;$settlement=null;$due=$total;
        if(isset($n['historical_return'])){
            $intent=$this->one('SELECT actor_user_id,customer_id,(expires_at_utc<=UTC_TIMESTAMP(6)) expired FROM historical_return_intents WHERE id=? FOR UPDATE',[$n['historical_return']['return_intent_id']]);
            if(!$intent||(string)$intent['actor_user_id']!==(string)$a['id']||(string)$intent['customer_id']!==$n['customer_id'])throw new InventoryException(['return'=>'Return intent is unavailable for this customer.'],403);
            if((int)$intent['expired'])throw new InventoryException(['return'=>'This return intent expired. Start a new return.'],409);
            if($this->one('SELECT invoice_id FROM historical_return_identities WHERE return_intent_id=?',[$n['historical_return']['return_intent_id']]))throw new InventoryException(['return'=>'This return is already finalized. Recover its linked invoice.'],409);
            $returnValue=(new HistoricalCustomerReturns($this->pdo))->value($n['historical_return']);$settlement=ProductReturnCalculation::settlement($returnValue['approved_value'],$total);$due=$settlement['remaining_payable'];
        }
        $applied=Money::compareMoney($n['amount_received'],$due)>0?$due:$n['amount_received'];$change=Money::subtractMoney($n['amount_received'],$applied);$outstanding=Money::subtractMoney($due,$applied);
        $existing=$this->outstanding($a,$n['customer_id']);
        if($n['payment_type']==='CREDIT'){
            if(!(int)$c['allow_credit_sales'])throw new InventoryException(['credit'=>'Credit sales are not enabled for this customer.']);
            if((int)$c['credit_limit_enabled']){
                // Aggregate receivables can exceed one invoice's DECIMAL(15,2) range.
                // Compare debt first, then remaining headroom, without overflowing a capped sum.
                $exceeded=Money::compare(str_replace('.','',$existing),str_replace('.','',$c['credit_limit']))>0;
                if(!$exceeded)$exceeded=Money::compareMoney($outstanding,Money::subtractMoney($c['credit_limit'],$existing))>0;
                if($exceeded)throw new InventoryException(['credit'=>'This invoice would exceed the customer credit limit.'],409);
            }
        }
        $date=Inventory::today($instant);$terms=$n['payment_type']==='CREDIT'?(int)$c['credit_terms_days']:0;$due=(new \DateTimeImmutable($date,new \DateTimeZone('Asia/Colombo')))->modify('+'.$terms.' days')->format('Y-m-d');
        $snapshot=array_intersect_key($c,array_flip(['customer_code','shop_name','contact_person','phone','address','city_town','district']));
        $review=['currency'=>'LKR','customer_id'=>$n['customer_id'],'customer'=>$snapshot,'customer_version'=>(string)$c['version'],'stock_source'=>$source,'source_location_id'=>$loc,'rep_name'=>$source==='warehouse'?mb_substr('Central warehouse — '.$a['name'],0,150,'UTF-8'):$r['name'],'number_owner'=>$source==='warehouse'?(string)$a['id']:$source,'business_date'=>$date,'due_date'=>$due,'credit_terms_days'=>$terms,'credit_limit_snapshot'=>(int)$c['credit_limit_enabled']?$c['credit_limit']:null,'payment_type'=>$n['payment_type'],'lines'=>$lines,'subtotal'=>$gross,'discount_total'=>$discount,'grand_total'=>$total,'amount_received'=>$n['amount_received'],'applied_amount'=>$applied,'change_amount'=>$change,'outstanding'=>$outstanding,'existing_customer_outstanding'=>$existing];
        if($returnValue!==null){$review['historical_return']=$returnValue;$review['return_settlement']=$settlement;}
        if($discountSpec!==null){$review=array_merge($review,array_diff_key($discountSpec,['lines'=>true]));$review['automatic_discount_total']=$automatic;}
        if($offers)$review['oil_offers']=$offers;
        // Available stock is advisory, not part of the financial confirmation fingerprint.
        $fingerprint=$review;unset($fingerprint['oil_offers']);foreach($fingerprint['lines'] as &$line)unset($line['available_quantity'],$line['name_si']);unset($line);
        $review['review_hash']=hash('sha256',json_encode($fingerprint,JSON_THROW_ON_ERROR));return $review;
    }
    public function review(array $actor,array $input):array
    {
        if(array_key_exists('historical_return',$input))$this->requireHistoricalReturnAccess($actor);
        $n=$this->normalize($actor,$input);
        // Review may create no permanent operating state or business rows.
        $own=!$this->pdo->inTransaction();if($own){$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');$this->pdo->beginTransaction();}else $this->pdo->exec('SAVEPOINT invoice_review');
        try{return $this->reviewLocked($actor,$n);}finally{if($own)$this->pdo->rollBack();else{$this->pdo->exec('ROLLBACK TO SAVEPOINT invoice_review');$this->pdo->exec('RELEASE SAVEPOINT invoice_review');}}
    }
    private function streams(array $actor,string $source,string $invoice):void
    {
        if($source!=='warehouse')$this->pdo->prepare('UPDATE rep_operating_state SET inventory_revision=inventory_revision+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE rep_id=?')->execute([$source]);
        foreach($source==='warehouse'?['ADMIN']:['ADMIN','REP:'.$source] as $scope){$this->pdo->prepare('INSERT INTO sync_stream_state(scope_key,last_change_id) VALUES (?,0) ON DUPLICATE KEY UPDATE scope_key=scope_key')->execute([$scope]);$this->one('SELECT last_change_id FROM sync_stream_state WHERE scope_key=? FOR UPDATE',[$scope]);$this->pdo->prepare('UPDATE sync_stream_state SET last_change_id=last_change_id+1 WHERE scope_key=?')->execute([$scope]);$this->pdo->prepare("INSERT INTO change_log(scope_key,change_id,entity_type,entity_key,entity_version,action,changed_at_utc) SELECT scope_key,last_change_id,'invoice',?,last_change_id,'UPSERT',UTC_TIMESTAMP(6) FROM sync_stream_state WHERE scope_key=?")->execute([$invoice,$scope]);}
    }
    private function updateBalance(string $source,string $product,int $quantity):void
    {
        if($source==='warehouse')$this->pdo->prepare('UPDATE product_stock_balances SET quantity=?,version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE product_id=?')->execute([$quantity,$product]);
        else $this->pdo->prepare('UPDATE representative_stock_balances SET quantity=?,version=version+1,updated_at_utc=UTC_TIMESTAMP(6) WHERE rep_id=? AND product_id=?')->execute([$quantity,$source,$product]);
    }
    private function receipt(array $a,string $invoice,string $customer,string $request,string $hash,string $received,string $due):array
    {
        $applied=Money::compareMoney($received,$due)>0?$due:$received;$change=Money::subtractMoney($received,$applied);$payment=null;
        if($applied!=='0.00'){$payment=$request;$this->pdo->prepare("INSERT INTO payments(id,customer_id,received_by_user_id,method,amount,received_at_client_utc,accepted_at_utc,status) VALUES (?,?,?,'CASH',?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),'POSTED')")->execute([$payment,$customer,$a['id'],$applied]);$this->pdo->prepare('INSERT INTO payment_allocations(payment_id,invoice_id,customer_id,amount) VALUES (?,?,?,?)')->execute([$payment,$invoice,$customer,$applied]);}
        $this->pdo->prepare('INSERT INTO invoice_receipts(request_id,invoice_id,actor_user_id,payload_hash,received_amount,applied_amount,change_amount,payment_id) VALUES (?,?,?,?,?,?,?,?)')->execute([$request,$invoice,$a['id'],$hash,$received,$applied,$change,$payment]);return ['applied_amount'=>$applied,'change_amount'=>$change];
    }
    public function issue(array $actor,array $input,?array $syncContext=null):array
    {
        if($syncContext!==null&&array_key_exists('historical_return',$input))throw new InventoryException(['return'=>'Historical returns require an online transaction.'],409);
        if(array_key_exists('historical_return',$input))$this->requireHistoricalReturnAccess($actor);
        $n=$this->normalize($actor,$input);$id=self::uuid($input['request_id']??null);$review=$input['review_hash']??null;if(!is_string($review)||!preg_match('/^[0-9a-f]{64}$/D',$review))throw new InventoryException(['review'=>'Review the invoice before issuing.']);$hash=hash('sha256',json_encode([$n,$review],JSON_THROW_ON_ERROR));$returnHash=hash('sha256',json_encode($n,JSON_THROW_ON_ERROR));
        return $this->tx(function()use($actor,$n,$id,$review,$hash,$returnHash,$syncContext){$this->gate();$a=$this->actor($actor);if(isset($n['historical_return']))$this->requireHistoricalReturnAccess($a);
            if($syncContext===null&&$this->one('SELECT id FROM sync_operations WHERE id=? FOR UPDATE',[$id]))throw new InventoryException(['request_id'=>'This identity belongs to the offline synchronization workflow. Use its queue or administrator review.'],409);
            $old=$this->one('SELECT i.client_payload_hash,i.posting_channel,i.device_id,i.policy_grant_id,d.issued_by_user_id FROM invoices i JOIN online_invoice_details d ON d.invoice_id=i.id WHERE i.id=? FOR UPDATE',[$id]);
            if($old){if($syncContext!==null&&($old['posting_channel']!=='SYNC'||$old['device_id']!==$syncContext['device_id']||$old['policy_grant_id']!==$syncContext['grant_id']))throw new InventoryException(['request_id'=>'Identity already belongs to another issued invoice. Administrator review is required.'],409);if($old['client_payload_hash']!==$hash||(string)$old['issued_by_user_id']!==(string)$a['id'])throw new InventoryException(['request_id'=>'Request ID already used with different details.'],409);$this->get($a,$id);return ['invoice_id'=>$id,'replayed'=>true];}
            if(isset($n['historical_return'])){
                $previous=$this->one('SELECT invoice_id,actor_user_id,customer_id,payload_hash FROM historical_return_identities WHERE return_intent_id=? FOR UPDATE',[$n['historical_return']['return_intent_id']]);
                if($previous){if((string)$previous['actor_user_id']!==(string)$a['id']||(string)$previous['customer_id']!==$n['customer_id']||!hash_equals($previous['payload_hash'],$returnHash))throw new InventoryException(['return'=>'This return identity has already been used with different details.'],409);$existing=$this->get($a,$previous['invoice_id']);return ['invoice_id'=>$existing['id'],'invoice_number'=>$existing['invoice_number'],'replayed'=>true];}
            }
            $r=$this->reviewLocked($a,$n);foreach($r['oil_offers']??[] as $offer)if($offer['remaining']!=='0')throw new InventoryException(['oil_rewards'=>'Select all earned free oils before issuing.'],409);if(!hash_equals($r['review_hash'],$review))throw new InventoryException(['review'=>'Customer, price, promotion, date or outstanding balance changed. Review again before issuing.'],409);
            $owner=$r['number_owner'];$this->pdo->prepare('INSERT INTO invoice_sequence_counters(rep_id,next_sequence) VALUES (?,1) ON DUPLICATE KEY UPDATE rep_id=rep_id')->execute([$owner]);$counter=$this->one('SELECT next_sequence FROM invoice_sequence_counters WHERE rep_id=? FOR UPDATE',[$owner]);$seq=(string)$counter['next_sequence'];if(Money::compare($seq,(string)(PHP_INT_MAX-1))>0)throw new InventoryException(['number'=>'Invoice sequence exhausted.'],409);$settings=$this->one('SELECT business_prefix FROM invoice_settings WHERE id=1 FOR UPDATE');$number=$settings['business_prefix'].'-'.$owner.'-'.str_pad($seq,8,'0',STR_PAD_LEFT);$this->pdo->prepare('UPDATE invoice_sequence_counters SET next_sequence=next_sequence+1 WHERE rep_id=?')->execute([$owner]);
            $device=null;$grant=null;$channel='ONLINE';$created=gmdate('Y-m-d H:i:s');
            if($syncContext!==null){$device=self::uuid($syncContext['device_id']??null);$grant=self::uuid($syncContext['grant_id']??null);$channel='SYNC';$created=$syncContext['created_at_utc'];}
            $this->pdo->prepare('INSERT INTO invoices(id,invoice_number,rep_id,customer_id,sequence_number,business_date,created_at_client_utc,received_at_utc,accepted_at_utc,subtotal,automatic_discount_total,manual_discount_total,discount_total,grand_total,payment_type,customer_snapshot,rep_name_snapshot,client_payload_hash,posting_channel,device_id,policy_grant_id) VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6),?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$id,$number,$owner,$n['customer_id'],$seq,$r['business_date'],$created,$r['subtotal'],$r['automatic_discount_total']??$r['discount_total'],$r['invoice_discount_amount']??'0.00',$r['discount_total'],$r['grand_total'],$n['payment_type'],json_encode($r['customer'],JSON_THROW_ON_ERROR),$r['rep_name'],$hash,$channel,$device,$grant]);
            if(isset($r['discount_policy']))$this->pdo->prepare('INSERT INTO invoice_discount_snapshots(invoice_id,percent_rate,discountable_subtotal,discount_amount,policy_version) VALUES (?,?,?,?,?)')->execute([$id,$r['invoice_discount_percent'],$r['discountable_subtotal'],$r['invoice_discount_amount'],$r['discount_policy']]);
            $this->pdo->prepare('INSERT INTO invoice_company_snapshots(invoice_id,details) VALUES (?,?)')->execute([$id,json_encode((new CompanyLanguages($this->pdo))->snapshot(),JSON_THROW_ON_ERROR)]);
            $this->pdo->prepare('INSERT INTO invoice_language_snapshots(invoice_id,default_locale) VALUES (?,?)')->execute([$id,'si-LK']);
            $this->pdo->prepare('INSERT INTO online_invoice_details(invoice_id,source_location_id,issued_by_user_id,due_date,credit_terms_days,credit_limit_snapshot,review_hash) VALUES (?,?,?,?,?,?,?)')->execute([$id,$r['source_location_id'],$a['id'],$r['due_date'],$r['credit_terms_days'],$r['credit_limit_snapshot'],$review]);
            $this->pdo->prepare("INSERT INTO inventory_transactions(id,type,invoice_id,reason,created_by_user_id,payload_hash) VALUES (?,'SALE',?,'Invoice issued',?,?)")->execute([$id,$id,$a['id'],$hash]);
            $offlineNames=[];if($syncContext!==null){$nameGrant=$this->one('SELECT approved_snapshot FROM offline_policy_grants WHERE id=?',[$grant]);foreach(json_decode($nameGrant['approved_snapshot'],true,512,JSON_THROW_ON_ERROR)['products'] as $grantedProduct)$offlineNames[$grantedProduct['id']]=$grantedProduct['name_si']??null;}
            foreach($r['lines'] as $index=>$line){$p=$this->one('SELECT cost_price,name_si FROM products WHERE id=?',[$line['product_id']]);$this->pdo->prepare('INSERT INTO invoice_items(invoice_id,line_number,product_id,product_name_snapshot,sku_snapshot,quantity,free_quantity,unit_price,gross_amount,automatic_discount_amount,manual_discount_amount,discount_amount,line_total,price_version,promotion_version_id,cost_price_snapshot,promotion_snapshot) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$id,$index+1,$line['product_id'],$line['product_name'],$line['sku'],$line['paid_quantity'],$line['free_quantity'],$line['unit_price'],$line['gross_amount'],$line['automatic_discount_amount']??$line['discount_amount'],$line['invoice_discount_amount']??'0.00',$line['discount_amount'],$line['payable_amount'],$line['price_version'],$line['promotion_version_id'],$p['cost_price'],json_encode($line['rule_snapshot'],JSON_THROW_ON_ERROR)]);
                $this->pdo->prepare('INSERT INTO invoice_product_language_snapshots(invoice_id,product_id,name_si) VALUES (?,?,?)')->execute([$id,$line['product_id'],$syncContext===null?$p['name_si']:($offlineNames[$line['product_id']]??null)]);
                $after=(int)$line['available_quantity']-(int)$line['required_stock'];$this->updateBalance($n['stock_source'],$line['product_id'],$after);$this->pdo->prepare('INSERT INTO inventory_movements(transaction_id,line_number,location_id,product_id,quantity_delta,quantity_after) VALUES (?,?,?,?,?,?)')->execute([$id,$index+1,$r['source_location_id'],$line['product_id'],-(int)$line['required_stock'],$after]);}
            if(isset($r['historical_return'])){(new HistoricalCustomerReturns($this->pdo))->post($id,$n['customer_id'],(string)$a['id'],$r['historical_return'],$r['return_settlement']);$this->pdo->prepare('INSERT INTO historical_return_identities(return_intent_id,invoice_id,actor_user_id,customer_id,payload_hash) VALUES (?,?,?,?,?)')->execute([$n['historical_return']['return_intent_id'],$id,$a['id'],$n['customer_id'],$returnHash]);$this->audit($a,'HISTORICAL_RETURN_ISSUED',$id,$r['return_settlement']+['return_intent_id'=>$n['historical_return']['return_intent_id']],$r['historical_return']['reason']);}
            $receiptDue=$r['return_settlement']['remaining_payable']??$r['grand_total'];$this->receipt($a,$id,$n['customer_id'],$id,hash('sha256','INITIAL:'.$hash),$n['amount_received'],$receiptDue);$this->streams($a,$n['stock_source'],$id);$this->audit($a,'INVOICE_ISSUED',$id,['number'=>$number,'source'=>$n['stock_source'],'total'=>$r['grand_total'],'outstanding'=>$r['outstanding'],'invoice_discount_percent'=>$r['invoice_discount_percent']??'0.00','invoice_discount_amount'=>$r['invoice_discount_amount']??'0.00','discount_policy'=>$r['discount_policy']??'LEGACY_ZERO']);return ['invoice_id'=>$id,'invoice_number'=>$number,'replayed'=>false];
        });
    }
    private function scope(array $a,string $alias='i'):array
    {
        return $a['role']==='ADMIN'?['',[]]:[' AND '.$alias.'.rep_id=? AND EXISTS(SELECT 1 FROM customers c WHERE c.id='.$alias.'.customer_id AND c.assigned_rep_id=?)',[$a['id'],$a['id']]];
    }
    private function select():string
    {
        $return=$this->returnSchema()?'COALESCE((SELECT hr.applied_value FROM historical_customer_returns hr WHERE hr.invoice_id=i.id),0.00)':'0.00';
        return "SELECT (SELECT percent_rate FROM invoice_discount_snapshots WHERE invoice_id=i.id) invoice_discount_percent,(SELECT discount_amount FROM invoice_discount_snapshots WHERE invoice_id=i.id) invoice_discount_amount,(SELECT policy_version FROM invoice_discount_snapshots WHERE invoice_id=i.id) discount_policy,i.id,i.invoice_number,i.rep_id,i.customer_id,i.business_date,i.subtotal,i.discount_total,i.grand_total,i.payment_type,i.currency,i.customer_snapshot,i.rep_name_snapshot,d.source_location_id,d.issued_by_user_id,d.due_date,COALESCE(p.paid,0.00) paid_amount,$return return_applied,IF(v.invoice_id IS NULL,'ISSUED','VOIDED') validity,IF(COALESCE(p.paid,0)+$return>=i.grand_total,'PAID',IF(COALESCE(p.paid,0)+$return>0,'PARTIAL','UNPAID')) payment_status,IF(v.invoice_id IS NULL,i.grand_total-COALESCE(p.paid,0)-$return,0.00) outstanding,COALESCE(v.refund_due,0.00) refund_due,v.reason void_reason,v.created_at_utc voided_at_utc FROM invoices i JOIN online_invoice_details d ON d.invoice_id=i.id LEFT JOIN invoice_voids v ON v.invoice_id=i.id LEFT JOIN (SELECT a.invoice_id,SUM(a.amount) paid FROM payment_allocations a JOIN payments p ON p.id=a.payment_id WHERE p.status='POSTED' GROUP BY a.invoice_id) p ON p.invoice_id=i.id";
    }
    public function get(array $actor,string $id):array
    {
        $a=$this->actor($actor);self::uuid($id);[$scope,$params]=$this->scope($a);$row=$this->one($this->select().' WHERE i.id=?'.$scope,array_merge([$id],$params));if(!$row)throw new InventoryException(['invoice'=>'Invoice not found or not accessible.'],404);
        $company=$this->one('SELECT details FROM invoice_company_snapshots WHERE invoice_id=?',[$id]);if($company)$row['company']=json_decode($company['details'],true,32,JSON_THROW_ON_ERROR);
        $row['document_locale']=$this->one('SELECT default_locale FROM invoice_language_snapshots WHERE invoice_id=?',[$id])['default_locale']??'en';$translatedNames=$this->rows('SELECT product_id,name_si FROM invoice_product_language_snapshots WHERE invoice_id=?',[$id]);$translatedNames=array_column($translatedNames,'name_si','product_id');
        $row['customer']=json_decode($row['customer_snapshot'],true,32,JSON_THROW_ON_ERROR);unset($row['customer_snapshot']);$row['items']=$this->rows('SELECT product_id,product_name_snapshot,sku_snapshot,quantity,free_quantity,unit_price,gross_amount,automatic_discount_amount,manual_discount_amount,discount_amount,line_total,price_version,promotion_snapshot FROM invoice_items WHERE invoice_id=? ORDER BY line_number',[$id]);
        if($this->returnSchema())$row['historical_return']=$this->one('SELECT approved_value,applied_value,forfeited_value,created_at_utc FROM historical_customer_returns WHERE invoice_id=?',[$id]);
        foreach(['rep_id','customer_id','source_location_id','issued_by_user_id'] as $key)$row[$key]=(string)$row[$key];
        foreach($row['items'] as &$line){$line['name_si']=$translatedNames[$line['product_id']]??null;foreach(['product_id','quantity','free_quantity','price_version'] as $key)$line[$key]=(string)$line[$key];}unset($line);
        $row['receipts']=$this->rows('SELECT r.request_id,r.received_amount,r.applied_amount,r.change_amount,r.created_at_utc,u.name received_by FROM invoice_receipts r JOIN users u ON u.id=r.actor_user_id WHERE r.invoice_id=? ORDER BY r.created_at_utc,r.request_id',[$id]);return $row;
    }
    public function listing(array $actor,array $filters):array
    {
        $a=$this->actor($actor);[$scope,$params]=$this->scope($a);$where=' WHERE 1=1'.$scope;
        foreach(['customer_id','rep_id'] as $key)if(($filters[$key]??'')!==''){$id=Representatives::id($filters[$key],$key);if($key==='rep_id'&&$a['role']==='SALES_REP'&&$id!==(string)$a['id'])throw new InventoryException(['access'=>'Only your own sales are accessible.'],403);$where.=' AND i.'.$key.'=?';$params[]=$id;}
        if(($filters['customer_id']??'')!==''&&$a['role']==='SALES_REP'){$c=$this->one('SELECT assigned_rep_id FROM customers WHERE id=?',[$filters['customer_id']]);if(!$c||(string)$c['assigned_rep_id']!==(string)$a['id'])throw new InventoryException(['access'=>'Customer is not assigned to you.'],403);}
        $q=Inventory::text($filters['q']??'','q',100);if($q!==''){$where.=" AND i.invoice_number LIKE ? ESCAPE '!'";$params[]='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';}
        foreach(['from'=>'>=','to'=>'<='] as $key=>$op)if(($filters[$key]??'')!==''){$date=Inventory::date($filters[$key],$key,true);$where.=' AND i.business_date'.$op.'?';$params[]=$date;}
        $status=$filters['payment_status']??'';$valid=$filters['validity']??'';if(!in_array($status,['','PAID','PARTIAL','UNPAID'],true)||!in_array($valid,['','ISSUED','VOIDED'],true))throw new InventoryException(['filters'=>'Invalid invoice status.']);
        $sql='SELECT * FROM ('.$this->select().$where.') x WHERE 1=1';if($status!==''){$sql.=' AND payment_status=?';$params[]=$status;}if($valid!==''){$sql.=' AND validity=?';$params[]=$valid;}
        $page=$filters['page']??'1';if(!is_string($page)||!preg_match('/^[1-9][0-9]{0,5}$/D',$page))throw new InventoryException(['page'=>'Invalid page.']);$total=(int)$this->one('SELECT COUNT(*) n FROM ('.$sql.') filtered',$params)['n'];$pages=max(1,intdiv($total+24,25));$page=min((int)$page,$pages);$rows=$this->rows($sql.' ORDER BY business_date DESC,invoice_number DESC LIMIT 25 OFFSET '.(($page-1)*25),$params);foreach($rows as &$row){$row['customer']=json_decode($row['customer_snapshot'],true,32,JSON_THROW_ON_ERROR);unset($row['customer_snapshot']);}unset($row);return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
    public function pay(array $actor,string $id,array $input):array
    {
        $a=$this->actor($actor);self::uuid($id);$request=self::uuid($input['request_id']??null);$amount=self::amount($input['amount_received']??null);if($amount==='0.00')throw new InventoryException(['amount'=>'Enter a positive received amount.']);$hash=hash('sha256',json_encode([$id,$amount],JSON_THROW_ON_ERROR));
        return $this->tx(function()use($a,$id,$request,$amount,$hash){$this->gate();$this->actor($a);$i=$this->get($a,$id);$this->customer($a,(string)$i['customer_id'],false);$this->one('SELECT id FROM invoices WHERE id=? FOR UPDATE',[$id]);
            $old=$this->one('SELECT * FROM invoice_receipts WHERE request_id=? FOR UPDATE',[$request]);if($old){if($old['payload_hash']!==$hash||(string)$old['actor_user_id']!==(string)$a['id']||$old['invoice_id']!==$id)throw new InventoryException(['request_id'=>'Request ID already used.'],409);return ['invoice_id'=>$id,'applied_amount'=>$old['applied_amount'],'change_amount'=>$old['change_amount'],'replayed'=>true];}
            $i=$this->get($a,$id);if($i['validity']!=='ISSUED'||$i['outstanding']==='0.00')throw new InventoryException(['payment'=>'Only an issued invoice with an outstanding amount can receive payment.'],409);
            $r=$this->receipt($a,$id,(string)$i['customer_id'],$request,$hash,$amount,$i['outstanding']);$this->audit($a,'INVOICE_PAYMENT',$id,$r+['request_id'=>$request]);return $r+['invoice_id'=>$id,'replayed'=>false];
        });
    }
    public function void(array $actor,string $id,array $input):array
    {
        $this->actor($actor,true);self::uuid($id);$request=self::uuid($input['request_id']??null);$reason=Inventory::text($input['reason']??'','reason',500,true);$hash=hash('sha256',json_encode([$id,$reason],JSON_THROW_ON_ERROR));
        return $this->tx(function()use($actor,$id,$request,$reason,$hash){$s=$this->gate();$a=$this->actor($actor,true);$i=$this->get($a,$id);$location=$this->one('SELECT type,rep_id FROM stock_locations WHERE id=?',[$i['source_location_id']]);$source=$location['type']==='WAREHOUSE'?'warehouse':(string)$location['rep_id'];
            if($source!=='warehouse'){$this->one('SELECT id FROM users WHERE id=? FOR UPDATE',[$source]);$this->one('SELECT rep_id FROM rep_operating_state WHERE rep_id=? FOR UPDATE',[$source]);}
            $this->customer($a,(string)$i['customer_id'],false);$this->one('SELECT id FROM invoices WHERE id=? FOR UPDATE',[$id]);$old=$this->one('SELECT * FROM invoice_voids WHERE invoice_id=? FOR UPDATE',[$id]);if($old){if($old['request_id']!==$request||$old['payload_hash']!==$hash||(string)$old['actor_user_id']!==(string)$a['id'])throw new InventoryException(['void'=>'Invoice already voided. No second reversal is permitted.'],409);return ['invoice_id'=>$id,'refund_due'=>$old['refund_due'],'replayed'=>true];}
            $items=$i['items'];usort($items,static fn($a,$b)=>Money::compare((string)$a['product_id'],(string)$b['product_id']));foreach($items as $line)$this->one('SELECT id FROM products WHERE id=? FOR UPDATE',[$line['product_id']]);
            $reversal=$request;$this->pdo->prepare("INSERT INTO inventory_transactions(id,type,reverses_transaction_id,reason,created_by_user_id,payload_hash) VALUES (?,'ADJUSTMENT',?,?,?,?)")->execute([$reversal,$id,$reason,$a['id'],$hash]);
            foreach($items as $index=>$line){[$loc,$qty]=$this->balance($s,$source,(string)$line['product_id'],true);if($loc!==(string)$i['source_location_id'])throw new InventoryException(['source'=>'Original stock source no longer matches.'],409);$units=(int)$line['quantity']+(int)$line['free_quantity'];if($units>PHP_INT_MAX-(int)$qty)throw new InventoryException(['stock'=>'Stock reversal exceeds supported integer range.']);$after=(int)$qty+$units;$this->updateBalance($source,(string)$line['product_id'],$after);$this->pdo->prepare('INSERT INTO inventory_movements(transaction_id,line_number,location_id,product_id,quantity_delta,quantity_after) VALUES (?,?,?,?,?,?)')->execute([$reversal,$index+1,$loc,$line['product_id'],$units,$after]);}
            $i=$this->get($a,$id);$this->pdo->prepare('INSERT INTO invoice_voids(invoice_id,request_id,actor_user_id,reason,payload_hash,reversal_transaction_id,cancelled_outstanding,refund_due) VALUES (?,?,?,?,?,?,?,?)')->execute([$id,$request,$a['id'],$reason,$hash,$reversal,$i['outstanding'],$i['paid_amount']]);if(!empty($i['historical_return']))$this->audit($a,'HISTORICAL_RETURN_SETTLEMENT_VOIDED',$id,['applied_value'=>$i['historical_return']['applied_value'],'void_request_id'=>$request,'return_retained'=>true],$reason);$this->streams($a,$source,$id);$this->audit($a,'INVOICE_VOIDED',$id,['cancelled_outstanding'=>$i['outstanding'],'refund_due'=>$i['paid_amount'],'reversal_transaction_id'=>$reversal],$reason);return ['invoice_id'=>$id,'refund_due'=>$i['paid_amount'],'replayed'=>false];
        });
    }
}
