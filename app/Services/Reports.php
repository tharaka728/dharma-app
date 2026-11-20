<?php
declare(strict_types=1);
namespace App\Services;
use PDO;use App\Support\InventoryException;
/** Read-only reports over the existing authoritative ledgers. No posting methods. */
final class Reports
{
    public const TYPES=['overview'=>'Overview','sales'=>'Sales / trends','products'=>'Product sales','customers'=>'Customer purchases','locations'=>'District / town sales','representatives'=>'Representative performance','collections'=>'Collections','outstanding'=>'Outstanding / overdue','refunds'=>'Refund liabilities','returns'=>'Historical returns','inventory'=>'Current stock','movements'=>'Stock movement history','offline'=>'Offline submissions','reconciliation'=>'Reconciliation'];
    public function __construct(private PDO $pdo){}
    public function availableTypes():array
    {
        $types=self::TYPES;if($this->returnValue('i')==='0.00')unset($types['returns']);return $types;
    }
    private function rows(string $sql,array $params=[]):array{$q=$this->pdo->prepare($sql);$q->execute($params);return $q->fetchAll();}
    private function one(string $sql,array $params=[]):array{return $this->rows($sql,$params)[0]??[];}
    private function returnValue(string $alias):string
    {
        $row=$this->one('SELECT status FROM schema_migrations WHERE version=?',['021_historical_customer_returns.sql']);
        return ($row['status']??null)==='applied'?'COALESCE((SELECT hr.applied_value FROM historical_customer_returns hr WHERE hr.invoice_id='.$alias.'.id),0.00)':'0.00';
    }
    public function actor(array $actor):array{return (new Invoices($this->pdo))->actor($actor);}
    public static function day(?\DateTimeImmutable $now=null):string{return ($now??new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('Asia/Colombo'))->format('Y-m-d');}
    private function id(mixed $value,string $field):string
    {if(!is_string($value)||($value!==''&&!preg_match('/^[1-9][0-9]{0,17}$/D',$value)))throw new InventoryException([$field=>'Choose a valid listed value.']);return $value;}
    public function filters(array $actor,array $input):array
    {
        $a=$this->actor($actor);$f=[];
        foreach(['rep_id','customer_id','product_id','district_id','town_id','category_id'] as $key)$f[$key]=$this->id($input[$key]??'',$key);
        if($a['role']==='SALES_REP'){
            if($f['rep_id']!==''&&$f['rep_id']!==(string)$a['id'])throw new InventoryException(['rep_id'=>'Only your own reports are available.'],403);
            $f['rep_id']=(string)$a['id'];
        }
        foreach(['rep_id'=>['users'," AND role='SALES_REP'"],'customer_id'=>['customers',''],'product_id'=>['products',''],'district_id'=>['location_districts',''],'town_id'=>['location_towns',''],'category_id'=>['categories','']] as $key=>[$table,$extra]){
            if($f[$key]!==''&&!$this->one('SELECT id FROM '.$table.' WHERE id=?'.$extra,[$f[$key]]))throw new InventoryException([$key=>'Choose an existing listed value.']);
        }
        if($a['role']==='SALES_REP'&&$f['customer_id']!==''&&!$this->one('SELECT id FROM customers WHERE id=? AND assigned_rep_id=?',[$f['customer_id'],$a['id']]))throw new InventoryException(['customer_id'=>'Customer not accessible.'],403);
        if($f['town_id']!==''&&$f['district_id']!==''&&!$this->one('SELECT id FROM location_towns WHERE id=? AND district_id=?',[$f['town_id'],$f['district_id']]))throw new InventoryException(['town_id'=>'Choose a town belonging to the selected district.']);
        foreach(['payment_status'=>['','PAID','PARTIAL','UNPAID'],'group'=>['day','week','month'],'period'=>['today','yesterday','week','month','custom']] as $key=>$allowed){$f[$key]=$input[$key]??($key==='group'?'day':($key==='period'?'month':''));if(!is_string($f[$key])||!in_array($f[$key],$allowed,true))throw new InventoryException([$key=>'Choose a valid report option.']);}
        $f['collection_group']=$input['collection_group']??'entries';if(!is_string($f['collection_group'])||!in_array($f['collection_group'],['entries','day','customer','collector'],true))throw new InventoryException(['collection_group'=>'Choose a valid collections grouping.']);
        $f['q']=$input['q']??'';if(!is_string($f['q'])||!mb_check_encoding($f['q'],'UTF-8')||mb_strlen($f['q'],'UTF-8')>100)throw new InventoryException(['q'=>'Search must be at most 100 characters.']);$f['q']=trim($f['q']);
        $page=$input['page']??'1';if(!is_string($page)||!preg_match('/^[1-9][0-9]{0,5}$/D',$page))throw new InventoryException(['page'=>'Invalid page.']);$f['page']=(int)$page;
        $range=self::range($f['period'],$input['from']??'',$input['to']??'');return $f+$range;
    }
    public static function range(string $period,mixed $from='',mixed $to='',?\DateTimeImmutable $now=null):array
    {
        $today=new \DateTimeImmutable(self::day($now),new \DateTimeZone('Asia/Colombo'));
        if($period==='custom'){
            foreach(['from'=>$from,'to'=>$to] as $key=>$value){if(!is_string($value)||!preg_match('/^(20[0-9]{2}|19[0-9]{2})-[0-9]{2}-[0-9]{2}$/D',$value)||($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value))===false||$d->format('Y-m-d')!==$value)throw new InventoryException([$key=>'Enter a valid reporting date (1900–2099).']);}
            if($from>$to)throw new InventoryException(['from'=>'From date must not follow To date.']);
        }else{
            $to=$today->format('Y-m-d');
            if($period==='today')$from=$to;
            elseif($period==='yesterday')$from=$to=$today->modify('-1 day')->format('Y-m-d');
            elseif($period==='week'){$monday=$today->modify('-'.((int)$today->format('N')-1).' days');$from=$monday->format('Y-m-d');$to=$monday->modify('+6 days')->format('Y-m-d');}
            elseif($period==='month'){$from=$today->format('Y-m-01');$to=$today->format('Y-m-t');}
            else throw new InventoryException(['period'=>'Invalid reporting period.']);
        }
        return ['from'=>$from,'to'=>$to];
    }
    private function context(array $a,array $f):array
    {
        $tz=new \DateTimeZone('Asia/Colombo');$utc=new \DateTimeZone('UTC');
        $params=[$f['from'],$f['to'],self::day(),(new \DateTimeImmutable($f['from'],$tz))->setTimezone($utc)->format('Y-m-d H:i:s'),(new \DateTimeImmutable($f['to'],$tz))->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s')];
        $where=["i.status='FINALIZED'"];
        if($a['role']==='SALES_REP'){$where[]='i.rep_id=? AND c.assigned_rep_id=?';array_push($params,$a['id'],$a['id']);}
        foreach(['rep_id'=>'i.rep_id','customer_id'=>'i.customer_id','district_id'=>'c.district_id','town_id'=>'c.town_id'] as $key=>$column)if($f[$key]!==''){$where[]=$column.'=?';$params[]=$f[$key];}
        $line=[];if($f['product_id']!==''){$line[]='li.product_id=?';$params[]=$f['product_id'];}if($f['category_id']!==''){$line[]='lp.category_id=?';$params[]=$f['category_id'];}
        if($line)$where[]='EXISTS(SELECT 1 FROM invoice_items li JOIN products lp ON lp.id=li.product_id WHERE li.invoice_id=i.id AND '.implode(' AND ',$line).')';
        if($f['q']!==''){$like='%'.strtr($f['q'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where[]="(i.invoice_number LIKE ? ESCAPE '!' OR c.shop_name LIKE ? ESCAPE '!' OR EXISTS(SELECT 1 FROM invoice_items sq WHERE sq.invoice_id=i.id AND (sq.sku_snapshot LIKE ? ESCAPE '!' OR sq.product_name_snapshot LIKE ? ESCAPE '!' OR EXISTS(SELECT 1 FROM invoice_product_language_snapshots sn WHERE sn.invoice_id=sq.invoice_id AND sn.product_id=sq.product_id AND sn.name_si LIKE ? ESCAPE '!'))))";array_push($params,$like,$like,$like,$like,$like);}
        $return=$this->returnValue('i');
        $cte="WITH clock AS (SELECT CAST(? AS DATE) first_day,CAST(? AS DATE) last_day,CAST(? AS DATE) today,CAST(? AS DATETIME) utc_start,CAST(? AS DATETIME) utc_end),
        raw_invoices AS (SELECT i.id,i.invoice_number,i.rep_id,i.customer_id,i.business_date,i.subtotal,i.discount_total,i.grand_total,i.payment_type,i.rep_name_snapshot,
        JSON_UNQUOTE(JSON_EXTRACT(i.customer_snapshot,'$.shop_name')) customer_name,c.customer_code,c.district_id,c.town_id,c.credit_limit_enabled,c.credit_limit,c.allow_credit_sales,d.due_date,
        COALESCE(pa.paid,0.00) paid_amount,IF(v.invoice_id IS NULL,'ISSUED','VOIDED') validity,
        $return return_applied,IF(COALESCE(pa.paid,0)+$return>=i.grand_total,'PAID',IF(COALESCE(pa.paid,0)+$return>0,'PARTIAL','UNPAID')) payment_status,
        IF(v.invoice_id IS NULL,i.grand_total-COALESCE(pa.paid,0)-$return,0.00) outstanding,COALESCE(v.refund_due,0.00) refund_due,v.created_at_utc voided_at_utc,
        COALESCE(iq.paid_units,0) paid_units,COALESCE(iq.free_units,0) free_units
        FROM invoices i JOIN online_invoice_details d ON d.invoice_id=i.id JOIN customers c ON c.id=i.customer_id
        LEFT JOIN (SELECT x.invoice_id,SUM(x.amount) paid FROM payment_allocations x JOIN payments p ON p.id=x.payment_id WHERE p.status='POSTED' GROUP BY x.invoice_id) pa ON pa.invoice_id=i.id
        LEFT JOIN (SELECT invoice_id,SUM(quantity) paid_units,SUM(free_quantity) free_units FROM invoice_items GROUP BY invoice_id) iq ON iq.invoice_id=i.id
        LEFT JOIN invoice_voids v ON v.invoice_id=i.id WHERE ".implode(' AND ',$where)."),
        a AS (SELECT * FROM raw_invoices";
        if($f['payment_status']!==''){$cte.=' WHERE payment_status=?';$params[]=$f['payment_status'];}
        $cte.="), period_invoices AS (SELECT a.* FROM a CROSS JOIN clock WHERE business_date BETWEEN first_day AND last_day),
        collected AS (SELECT x.payment_id,x.invoice_id,x.amount,p.method,p.accepted_at_utc,p.received_by_user_id,u.name collector,a.customer_id,a.customer_name,a.rep_id,a.rep_name_snapshot,a.invoice_number,a.validity,a.refund_due
        FROM payment_allocations x JOIN payments p ON p.id=x.payment_id JOIN a ON a.id=x.invoice_id JOIN users u ON u.id=p.received_by_user_id CROSS JOIN clock WHERE p.status='POSTED' AND p.accepted_at_utc>=utc_start AND p.accepted_at_utc<utc_end) ";
        return [$cte,$params];
    }
    private function stockQuery(array $a,array $f):array
    {
        $sql="SELECT 'Warehouse' location,NULL rep_id,p.id product_id,p.sku,p.name,p.name_si,p.selling_price,COALESCE(b.quantity,0) quantity FROM products p LEFT JOIN product_stock_balances b ON b.product_id=p.id
        UNION ALL SELECT u.name location,b.rep_id,p.id product_id,p.sku,p.name,p.name_si,p.selling_price,b.quantity FROM representative_stock_balances b JOIN users u ON u.id=b.rep_id JOIN products p ON p.id=b.product_id";
        $where=['1=1'];$params=[];
        if($f['rep_id']!==''){$where[]='s.rep_id=?';$params[]=$f['rep_id'];}
        if($f['product_id']!==''){$where[]='s.product_id=?';$params[]=$f['product_id'];}
        if($f['category_id']!==''){$where[]='EXISTS(SELECT 1 FROM products p WHERE p.id=s.product_id AND p.category_id=?)';$params[]=$f['category_id'];}
        if($f['q']!==''){$where[]="(s.name LIKE ? ESCAPE '!' OR s.name_si LIKE ? ESCAPE '!' OR s.sku LIKE ? ESCAPE '!')";$like='%'.strtr($f['q'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%';array_push($params,$like,$like,$like);}
        return ['SELECT s.location,s.rep_id,s.sku,s.name,s.name_si,s.selling_price,s.quantity,CAST(s.quantity AS DECIMAL(30,0))*s.selling_price estimated_selling_value FROM ('.$sql.') s WHERE '.implode(' AND ',$where),$params];
    }
    private function offlineQuery(array $a,array $f):array
    {
        $where=['o.received_at_utc>=? AND o.received_at_utc<?'];$tz=new \DateTimeZone('Asia/Colombo');$utc=new \DateTimeZone('UTC');$params=[(new \DateTimeImmutable($f['from'],$tz))->setTimezone($utc)->format('Y-m-d H:i:s'),(new \DateTimeImmutable($f['to'],$tz))->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s')];
        if($f['rep_id']!==''){$where[]='o.user_id=?';$params[]=$f['rep_id'];}
        if($f['customer_id']!==''){$where[]="JSON_UNQUOTE(JSON_EXTRACT(p.body_json,'$.customer_id'))=?";$params[]=$f['customer_id'];}
        if($f['product_id']!==''){$where[]="JSON_SEARCH(p.body_json,'one',?,NULL,'$.items[*].product_id') IS NOT NULL";$params[]=$f['product_id'];}
        if($f['category_id']!==''){$where[]="EXISTS(SELECT 1 FROM products pr WHERE pr.category_id=? AND JSON_SEARCH(p.body_json,'one',CAST(pr.id AS CHAR),NULL,'$.items[*].product_id') IS NOT NULL)";$params[]=$f['category_id'];}
        foreach(['district_id'=>'district_id','town_id'=>'town_id'] as $key=>$column)if($f[$key]!==''){$where[]="EXISTS(SELECT 1 FROM customers c WHERE CAST(c.id AS CHAR)=JSON_UNQUOTE(JSON_EXTRACT(p.body_json,'$.customer_id')) AND c.$column=?)";$params[]=$f[$key];}
        if($f['q']!==''){$where[]="(o.id LIKE ? ESCAPE '!' OR u.name LIKE ? ESCAPE '!')";$like='%'.strtr($f['q'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%';array_push($params,$like,$like);}
        $sql="SELECT o.id operation_id,u.name representative,o.result_status,o.last_error_code,CONVERT_TZ(o.received_at_utc,'+00:00','+05:30') received_colombo,JSON_UNQUOTE(JSON_EXTRACT(p.body_json,'$.amount_received')) provisional_cash_lkr FROM sync_operations o JOIN offline_sale_payloads p ON p.operation_id=o.id JOIN users u ON u.id=o.user_id WHERE ".implode(' AND ',$where);
        return [$sql,$params];
    }
    public function report(array $actor,string $type,array $input,bool $csv=false):array
    {
        $a=$this->actor($actor);if(!isset(self::TYPES[$type]))throw new InventoryException(['report'=>'Unknown report.'],404);
        if($type==='reconciliation'&&$a['role']!=='ADMIN')throw new InventoryException(['access'=>'Company reconciliation requires admin access.'],403);
        $f=$this->filters($a,$input);$own=!$this->pdo->inTransaction();
        if($own){$this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$this->pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');}
        try{$result=$this->build($a,$type,$f,$csv);if($own)$this->pdo->commit();return $result;}catch(\Throwable $e){if($own&&$this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    private function build(array $a,string $type,array $f,bool $csv):array
    {
        [$cte,$params]=$this->context($a,$f);$cards=[];$notes=["Period activity uses Colombo invoice business dates; collections use actual payment posting dates. Weeks run Monday–Sunday.","Current receivables, refund liabilities and stock ignore period dates; they are current snapshots. Current customer locations and current assignment define location/access filters.","Actual refunds: Unavailable — refund posting not implemented. Device-only unsynchronized drafts are unavailable to the server. CSV is limited to 10,000 rows; narrow the filters if needed."];
        if($this->returnValue('i')!=='0.00')$notes[]='Operational net sales after returns equal valid new-invoice merchandise net sales minus applied noncash return settlements. Forfeited value is not deducted. This management measure is not statutory or tax revenue. Voided invoices and their settlements are excluded from active sales; settlement reversals are reported separately by void posting date.';
        if($f['product_id']!==''||$f['category_id']!=='')$notes[]='Whole-invoice totals for invoices containing the selected product/category. Product-sales rows include only matching lines; collections and debt are not allocated to individual products.';
        $period="SELECT * FROM period_invoices";$order='';
        if($type==='overview'){
            $sum=$this->one($cte."SELECT COALESCE(SUM(IF(validity='ISSUED',subtotal,0)),0.00) gross,COALESCE(SUM(IF(validity='ISSUED',grand_total,0)),0.00) net,COALESCE(SUM(IF(validity='ISSUED' AND payment_type='CREDIT',grand_total,0)),0.00) credit,SUM(validity='VOIDED') void_count FROM period_invoices",$params);
            $balance=$this->one($cte."SELECT COALESCE(SUM(outstanding),0.00) outstanding,COALESCE(SUM(IF(due_date<clock.today,outstanding,0)),0.00) overdue,COALESCE(SUM(refund_due),0.00) refund_due FROM a CROSS JOIN clock",$params);
            $cash=$this->one($cte."SELECT COALESCE(SUM(amount),0.00) cash,COALESCE(SUM(IF(method='CASH',amount,0)),0.00) cash_only FROM collected",$params);
            $cards=['Period gross issued sales · LKR'=>$sum['gross'],'Period net sales · LKR'=>$sum['net'],'Period posted collections · LKR'=>$cash['cash'],'Period credit sales · LKR'=>$sum['credit'],'Current outstanding · LKR'=>$balance['outstanding'],'Current overdue · LKR'=>$balance['overdue'],'Current Refund Due · LKR'=>$balance['refund_due'],'Period voided invoice count'=>(string)($sum['void_count']??0)];
            if($this->returnValue('i')!=='0.00'){
                $returns=$this->one($cte."SELECT COALESCE(SUM(IF(a.validity='ISSUED',hr.approved_value,0)),0.00) approved,COALESCE(SUM(IF(a.validity='ISSUED',hr.applied_value,0)),0.00) applied,COALESCE(SUM(IF(a.validity='ISSUED',hr.forfeited_value,0)),0.00) forfeited,COALESCE(SUM(IF(a.validity='ISSUED',a.grand_total-COALESCE(hr.applied_value,0),0)),0.00) operational_net FROM period_invoices a LEFT JOIN historical_customer_returns hr ON hr.invoice_id=a.id",$params);
                $reversed=$this->one($cte."SELECT COALESCE(SUM(hr.applied_value),0.00) amount FROM a JOIN historical_customer_returns hr ON hr.invoice_id=a.id CROSS JOIN clock WHERE a.validity='VOIDED' AND a.voided_at_utc>=clock.utc_start AND a.voided_at_utc<clock.utc_end",$params);
                $cards['Valid invoice net merchandise sales · LKR']=$sum['net'];$cards['Approved historical return value · LKR']=$returns['approved'];$cards['Applied noncash settlements · LKR']=$returns['applied'];$cards['Forfeited return value · LKR']=$returns['forfeited'];$cards['Operational net sales after returns · LKR']=$returns['operational_net'];$cards['Settlement reversals by void date · LKR']=$reversed['amount'];
            }
            $cards['Period cash-method collections · LKR']=$cash['cash_only'];
            [$stock,$sp]=$this->stockQuery($a,$f);$quantities=$this->one('SELECT COALESCE(SUM(IF(rep_id IS NULL,quantity,0)),0) warehouse,COALESCE(SUM(IF(rep_id IS NOT NULL,quantity,0)),0) reps FROM ('.$stock.') z',$sp);
            if($a['role']==='ADMIN'&&$f['rep_id']==='')$cards['Current warehouse units']=(string)$quantities['warehouse'];$cards['Current representative units']=(string)$quantities['reps'];
            [$offline,$op]=$this->offlineQuery($a,$f);$pending=$this->one("SELECT COUNT(*) count,COALESCE(SUM(last_error_code IS NOT NULL),0) conflicts FROM ($offline) z WHERE result_status='RECEIVED'",$op);$cards['Period unresolved server submissions']=(string)$pending['count'];$cards['Period conflicted submissions (subset)']=(string)$pending['conflicts'];
            if($csv){
                $asOf=(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s P');$rows=[];
                foreach($cards+['Actual refunds'=>'Unavailable — refund posting not implemented.'] as $metric=>$value)$rows[]=['metric'=>$metric,'value'=>$value,'basis'=>str_starts_with($metric,'Current')?'CURRENT SNAPSHOT':'SELECTED PERIOD / SEE METRIC','period_from'=>$f['from'],'period_to'=>$f['to'],'snapshot_as_of_colombo'=>$asOf];
                return ['filters'=>$f,'as_of'=>$asOf,'notes'=>$notes,'cards'=>$cards,'rows'=>$rows,'columns'=>array_keys($rows[0]),'total'=>count($rows),'page'=>1,'pages'=>1];
            }
        }
        if(in_array($type,['overview','sales'],true)){
            $bucket=['day'=>'business_date','week'=>'DATE_SUB(business_date,INTERVAL WEEKDAY(business_date) DAY)','month'=>"DATE_FORMAT(business_date,'%Y-%m-01')"][$f['group']];
            $sql=$cte."SELECT $bucket period_start,COUNT(*) finalized_count,COALESCE(SUM(validity='VOIDED'),0) void_count,COALESCE(SUM(IF(validity='ISSUED',subtotal,0)),0.00) gross_lkr,COALESCE(SUM(IF(validity='ISSUED',discount_total,0)),0.00) discounts_lkr,COALESCE(SUM(IF(validity='ISSUED',grand_total,0)),0.00) net_lkr,COALESCE(SUM(IF(validity='ISSUED',return_applied,0)),0.00) applied_returns_lkr,COALESCE(SUM(IF(validity='ISSUED',grand_total-return_applied,0)),0.00) operational_net_after_returns_lkr,COALESCE(SUM(IF(validity='ISSUED' AND payment_type='CASH',grand_total,0)),0.00) cash_sales_lkr,COALESCE(SUM(IF(validity='ISSUED' AND payment_type='CREDIT',grand_total,0)),0.00) credit_sales_lkr,COALESCE(SUM(IF(validity='ISSUED',paid_units,0)),0) paid_units,COALESCE(SUM(IF(validity='ISSUED',free_units,0)),0) free_units FROM period_invoices GROUP BY $bucket";$order='period_start DESC';
        }elseif($type==='products'){
            $where=["a.validity='ISSUED'"];if($f['product_id']!==''){$where[]='li.product_id=?';$params[]=$f['product_id'];}if($f['category_id']!==''){$where[]='p.category_id=?';$params[]=$f['category_id'];}
            $sql=$cte.'SELECT li.product_id,li.sku_snapshot,li.product_name_snapshot,ls.name_si,SUM(li.quantity) paid_units,SUM(li.free_quantity) free_units,SUM(li.gross_amount) gross_lkr,SUM(li.discount_amount) discounts_lkr,SUM(li.line_total) net_lkr FROM period_invoices a JOIN invoice_items li ON li.invoice_id=a.id JOIN products p ON p.id=li.product_id LEFT JOIN invoice_product_language_snapshots ls ON ls.invoice_id=li.invoice_id AND ls.product_id=li.product_id WHERE '.implode(' AND ',$where).' GROUP BY li.product_id,li.sku_snapshot,li.product_name_snapshot,ls.name_si';$order='net_lkr DESC,product_id';
        }elseif($type==='customers'){
            $current=$a['role']==='ADMIN'?'current_outstanding_lkr':'current_own_outstanding_lkr';$overdue=$a['role']==='ADMIN'?'current_overdue_lkr':'current_own_overdue_lkr';
            $within="a.validity='ISSUED' AND a.business_date BETWEEN clock.first_day AND clock.last_day";
            $sql=$cte."SELECT a.customer_id,c.customer_code,c.shop_name,SUM(IF($within,a.grand_total,0.00)) period_net_lkr,MAX(IF($within,a.business_date,NULL)) period_last_purchase,MAX(IF(a.validity='ISSUED',a.business_date,NULL)) lifetime_last_purchase,SUM(a.outstanding) $current,SUM(IF(a.due_date<clock.today,a.outstanding,0.00)) $overdue";
            if($a['role']!=='ADMIN')$sql=str_replace(",MAX(IF(a.validity='ISSUED',a.business_date,NULL)) lifetime_last_purchase",'',$sql);
            $extra='';$group='a.customer_id,c.customer_code,c.shop_name';
            if($a['role']==='ADMIN'){
                $sql.=',IF(c.credit_limit_enabled,c.credit_limit,NULL) configured_credit_limit_lkr,IF(c.credit_limit_enabled,COALESCE(all_debt.amount,0.00),NULL) company_credit_used_lkr,IF(c.credit_limit_enabled,c.credit_limit-COALESCE(all_debt.amount,0),NULL) company_credit_remaining_lkr';
                $return=$this->returnValue('i');$extra=" LEFT JOIN (SELECT i.customer_id,SUM(i.grand_total-COALESCE(paid.amount,0)-$return) amount FROM invoices i LEFT JOIN invoice_voids v ON v.invoice_id=i.id LEFT JOIN (SELECT x.invoice_id,SUM(x.amount) amount FROM payment_allocations x JOIN payments p ON p.id=x.payment_id WHERE p.status='POSTED' GROUP BY x.invoice_id) paid ON paid.invoice_id=i.id WHERE v.invoice_id IS NULL AND i.status='FINALIZED' GROUP BY i.customer_id) all_debt ON all_debt.customer_id=a.customer_id";
                $group.=',c.credit_limit_enabled,c.credit_limit,all_debt.amount';
            }
            $sql.=' FROM a JOIN customers c ON c.id=a.customer_id CROSS JOIN clock'.$extra.' GROUP BY '.$group;$order='period_net_lkr DESC,customer_id';
            $notes[]='Purchase history can be opened in Invoices using the customer filter. Company credit usage/remaining use all current customer debt, ignoring product/period filters, and are shown only to admins. Other debt columns follow the selected invoice cohort.';
        }elseif($type==='locations'){
            $sql=$cte."SELECT COALESCE(d.name,'Unmapped — review legacy address') district,COALESCE(t.name,'Unmapped — review legacy address') town,COUNT(*) invoices,SUM(a.grand_total) net_lkr,SUM(a.paid_units) paid_units,SUM(a.free_units) free_units FROM period_invoices a LEFT JOIN location_districts d ON d.id=a.district_id LEFT JOIN location_towns t ON t.id=a.town_id WHERE a.validity='ISSUED' GROUP BY a.district_id,a.town_id,d.name,t.name";$order='district,town';
        }elseif($type==='representatives'){
            $sql=$cte."SELECT r.rep_id,u.name representative,COALESCE(s.net,0.00) period_net_lkr,COALESCE(s.cash,0.00) cash_sales_lkr,COALESCE(s.credit,0.00) credit_sales_lkr,COALESCE(c.amount,0.00) period_collected_on_own_invoices_lkr,COALESCE(b.amount,0.00) current_outstanding_lkr,COALESCE(s.paid,0) paid_units,COALESCE(s.free,0) free_units
            FROM (SELECT DISTINCT rep_id FROM a) r JOIN users u ON u.id=r.rep_id
            LEFT JOIN (SELECT rep_id,SUM(grand_total) net,SUM(IF(payment_type='CASH',grand_total,0)) cash,SUM(IF(payment_type='CREDIT',grand_total,0)) credit,SUM(paid_units) paid,SUM(free_units) free FROM period_invoices WHERE validity='ISSUED' GROUP BY rep_id) s ON s.rep_id=r.rep_id
            LEFT JOIN (SELECT rep_id,SUM(amount) amount FROM collected GROUP BY rep_id) c ON c.rep_id=r.rep_id LEFT JOIN (SELECT rep_id,SUM(outstanding) amount FROM a GROUP BY rep_id) b ON b.rep_id=r.rep_id";
            $ownerWhere=$f['rep_id']!==''?' AND id=?':'';
            $sql=str_replace('(SELECT DISTINCT rep_id FROM a) r',"(SELECT id rep_id FROM users WHERE role='SALES_REP'$ownerWhere UNION SELECT DISTINCT rep_id FROM a) r",$sql);if($ownerWhere)$params[]=$f['rep_id'];
            [$stock,$stockParams]=$this->stockQuery($a,$f);[$off,$offParams]=$this->offlineQuery($a,$f);
            $sql=str_replace('COALESCE(s.free,0) free_units','COALESCE(s.free,0) free_units,COALESCE(st.quantity,0) current_assigned_units,COALESCE(os.pending,0) period_pending_submissions,COALESCE(os.conflicts,0) period_sync_conflicts',$sql);
            $sql.=" LEFT JOIN (SELECT rep_id,SUM(quantity) quantity FROM ($stock) stock_rows GROUP BY rep_id) st ON st.rep_id=r.rep_id LEFT JOIN (SELECT representative_id,SUM(result_status='RECEIVED') pending,SUM(result_status='RECEIVED' AND last_error_code IS NOT NULL) conflicts FROM (".str_replace('SELECT o.id operation_id,','SELECT o.user_id representative_id,o.id operation_id,',$off).") op_rows GROUP BY representative_id) os ON os.representative_id=r.rep_id";
            array_push($params,...$stockParams,...$offParams);$order='period_net_lkr DESC,rep_id';$notes[]='Sales-source ownership and actual collection actor are distinct: Collections lists the responsible collector. Current stock ignores customer/geography/payment filters. Central warehouse invoices retain their admin numbering owner.';
        }elseif($type==='collections'){
            $sql=$cte."SELECT payment_id,invoice_number,CONVERT_TZ(accepted_at_utc,'+00:00','+05:30') posted_colombo,customer_name,rep_name_snapshot invoice_owner,collector,method,amount applied_collection_lkr,validity,IF(validity='VOIDED','Refund liability — not earned revenue','Posted collection') cash_status FROM collected";$order='posted_colombo DESC,payment_id,invoice_number';
            if($f['collection_group']!=='entries'){
                $bucket=['day'=>"DATE(CONVERT_TZ(accepted_at_utc,'+00:00','+05:30'))",'customer'=>'customer_id,customer_name','collector'=>'received_by_user_id,collector'][$f['collection_group']];
                $labels=['day'=>"DATE(CONVERT_TZ(accepted_at_utc,'+00:00','+05:30')) collection_day",'customer'=>'customer_id,customer_name','collector'=>'received_by_user_id collector_id,collector'][$f['collection_group']];
                $sql=$cte."SELECT $labels,COUNT(DISTINCT payment_id) payments,SUM(amount) gross_posted_collections_lkr,SUM(IF(validity='VOIDED',amount,0)) collected_on_voided_invoices_lkr FROM collected GROUP BY $bucket";$order='gross_posted_collections_lkr DESC';
            }
        }elseif($type==='outstanding'){
            $sql=$cte."SELECT invoice_number,business_date,customer_name,rep_name_snapshot,payment_type,payment_status,grand_total total_lkr,paid_amount paid_lkr,outstanding outstanding_lkr,due_date,GREATEST(DATEDIFF(clock.today,due_date),0) overdue_days,IF(due_date<clock.today,outstanding,0.00) overdue_lkr FROM a CROSS JOIN clock WHERE validity='ISSUED' AND outstanding>0";$order='due_date,invoice_number';
        }elseif($type==='refunds'){
            $sql=$cte."SELECT invoice_number,customer_name,rep_name_snapshot,CONVERT_TZ(voided_at_utc,'+00:00','+05:30') voided_colombo,refund_due current_refund_liability_lkr,'Unavailable — refund posting not implemented.' actual_refunds FROM a WHERE validity='VOIDED'";$order='voided_colombo DESC,invoice_number';
        }elseif($type==='inventory'){
            [$sql,$params]=$this->stockQuery($a,$f);$order='location,sku';$notes[]='Current selling-price stock value is an estimate, not accounting cost. Server stock excludes device-local provisional reservations. Stock uses only representative/product/category/search filters; dates, customer, payment status and locations do not apply.';
        }elseif($type==='movements'){
            $where=['m.batch_id IS NULL','m.created_at_utc>=clock.utc_start AND m.created_at_utc<clock.utc_end'];$extra=[];
            foreach(['rep_id'=>'l.rep_id','product_id'=>'m.product_id','category_id'=>'p.category_id'] as $key=>$column)if($f[$key]!==''){$where[]=$column.'=?';$extra[]=$f[$key];}
            if($f['q']!==''){$where[]="(p.sku LIKE ? ESCAPE '!' OR p.name LIKE ? ESCAPE '!' OR p.name_si LIKE ? ESCAPE '!')";$like='%'.strtr($f['q'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%';array_push($extra,$like,$like,$like);}
            $sql=$cte."SELECT m.transaction_id,CONVERT_TZ(m.created_at_utc,'+00:00','+05:30') moved_colombo,l.code location,p.sku,p.name,p.name_si,t.type,CASE WHEN t.type='TRANSFER' AND l.type='REP' AND m.quantity_delta>0 THEN 'ALLOCATION' WHEN t.type='TRANSFER' AND l.type='REP' AND m.quantity_delta<0 THEN 'REP_RETURN' WHEN t.reverses_transaction_id IS NOT NULL THEN 'REVERSAL' ELSE t.type END movement_kind,m.quantity_delta,m.quantity_after,t.reason,u.name responsible_user FROM inventory_movements m JOIN stock_locations l ON l.id=m.location_id JOIN products p ON p.id=m.product_id JOIN inventory_transactions t ON t.id=m.transaction_id JOIN users u ON u.id=t.created_by_user_id CROSS JOIN clock WHERE ".implode(' AND ',$where);array_push($params,...$extra);$order='moved_colombo DESC,transaction_id,location';$notes[]='A transfer has equal/opposite location rows; never sum absolute transfer legs as new company stock. Customer/geography/payment filters do not apply to physical movements.';
        }elseif($type==='returns'){
            if($this->returnValue('i')==='0.00')throw new InventoryException(['report'=>'Historical return reporting is not yet available.'],503);
            $where=['i.business_date BETWEEN ? AND ?'];$params=[$f['from'],$f['to']];
            if($a['role']==='SALES_REP'){$where[]='i.rep_id=? AND c.assigned_rep_id=?';array_push($params,$a['id'],$a['id']);}
            foreach(['rep_id'=>'i.rep_id','customer_id'=>'hr.customer_id','product_id'=>'rl.product_id','category_id'=>'p.category_id','district_id'=>'c.district_id','town_id'=>'c.town_id'] as $key=>$column)if($f[$key]!==''){$where[]=$column.'=?';$params[]=$f[$key];}
            if($f['q']!==''){$where[]="(i.invoice_number LIKE ? ESCAPE '!' OR c.shop_name LIKE ? ESCAPE '!' OR p.sku LIKE ? ESCAPE '!' OR p.name LIKE ? ESCAPE '!' OR p.name_si LIKE ? ESCAPE '!')";$like='%'.strtr($f['q'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%';array_push($params,$like,$like,$like,$like,$like);}
            $sql="SELECT i.id return_reference,i.invoice_number linked_invoice,i.business_date return_date,c.customer_code,c.shop_name customer_name,u.name representative,p.sku,p.name product_name,p.name_si product_name_si,rl.price_year,rl.unit_price historical_unit_price_lkr,rl.quantity non_saleable_quantity,rl.line_value line_return_value_lkr,rl.disposition,hr.approved_value transaction_approved_lkr,hr.applied_value transaction_applied_lkr,hr.forfeited_value transaction_forfeited_lkr,IF(v.invoice_id IS NULL,'ISSUED','VOIDED') invoice_validity,IF(v.invoice_id IS NULL,NULL,CONVERT_TZ(v.created_at_utc,'+00:00','+05:30')) voided_colombo,COALESCE(pa.paid,0.00) transaction_cash_lkr,IF(v.invoice_id IS NULL,i.grand_total-COALESCE(pa.paid,0)-hr.applied_value,0.00) current_outstanding_lkr FROM historical_customer_returns hr JOIN invoices i ON i.id=hr.invoice_id JOIN customers c ON c.id=hr.customer_id JOIN users u ON u.id=hr.actor_user_id JOIN historical_customer_return_lines rl ON rl.invoice_id=hr.invoice_id JOIN products p ON p.id=rl.product_id LEFT JOIN invoice_voids v ON v.invoice_id=i.id LEFT JOIN (SELECT x.invoice_id,SUM(x.amount) paid FROM payment_allocations x JOIN payments p ON p.id=x.payment_id WHERE p.status='POSTED' GROUP BY x.invoice_id) pa ON pa.invoice_id=i.id WHERE ".implode(' AND ',$where);
            $order='return_date DESC,linked_invoice,sku,price_year';$notes[]='Each row is one returned product and year. Transaction totals, actual cash and outstanding are repeated for each line and must not be summed across lines. Voids retain the physical return and cancel the linked invoice settlement. Management net sales after returns equal valid merchandise net sales less applied noncash settlements, excluding forfeited amounts; this is not a statutory revenue measure.';
        }elseif($type==='offline'){
            [$sql,$params]=$this->offlineQuery($a,$f);$order='received_colombo DESC,operation_id';$notes[]='RECEIVED means pending or Needs Attention; error codes identify conflicts. ACCEPTED is finalized exactly once, REJECTED is not revenue. Cash shown is provisional submitted cash, not posted collections. Invoice payment-status filters do not apply; offline search matches operation UUID or representative name.';
        }else{
            $checks=$this->reconciliation($a);$rows=[];foreach($checks as $check=>$violations)$rows[]=['check'=>$check,'violations'=>(string)$violations,'result'=>$violations===0?'PASS':'INVESTIGATE'];return ['filters'=>$f,'as_of'=>(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s P'),'notes'=>array_merge($notes,['Company-wide integrity checks ignore all report filters.']),'cards'=>[],'rows'=>$rows,'columns'=>array_keys($rows[0]),'total'=>count($rows),'page'=>1,'pages'=>1];
        }
        $total=(int)$this->one('SELECT COUNT(*) total FROM ('.$sql.') report_rows',$params)['total'];
        if($csv&&$total>10000)throw new InventoryException(['export'=>'More than 10,000 rows. Narrow the report filters before exporting.']);
        $page=min($f['page'],max(1,intdiv($total+49,50)));$rows=$this->rows($sql.($order?' ORDER BY '.$order:'').' LIMIT '.($csv?10000:50).' OFFSET '.($csv?0:($page-1)*50),$params);
        // Preserve stable columns even for empty reports using statement metadata.
        $q=$this->pdo->prepare($sql.' LIMIT 0');$q->execute($params);$columns=[];for($i=0;$i<$q->columnCount();$i++)$columns[]=$q->getColumnMeta($i)['name'];
        $asOf=(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s P');
        if(in_array($type,['customers','representatives','outstanding','refunds','inventory'],true)){$columns[]='snapshot_as_of_colombo';foreach($rows as &$row)$row['snapshot_as_of_colombo']=$asOf;unset($row);}
        return ['filters'=>$f,'as_of'=>$asOf,'notes'=>$notes,'cards'=>$cards,'rows'=>$rows,'columns'=>$columns,'total'=>$total,'page'=>$page,'pages'=>max(1,intdiv($total+49,50))];
    }
    public function reconciliation(array $a):array
    {
        $a=$this->actor($a);if($a['role']!=='ADMIN')throw new InventoryException(['access'=>'Admin only.'],403);
        $return=$this->returnValue('i');$queries=[
            'Invoice discount snapshots / allocations'=>"SELECT d.invoice_id FROM invoice_discount_snapshots d JOIN invoices i ON i.id=d.invoice_id LEFT JOIN (SELECT invoice_id,SUM(manual_discount_amount) allocated FROM invoice_items GROUP BY invoice_id) l ON l.invoice_id=i.id WHERE d.discount_amount<>i.manual_discount_total OR d.discount_amount<>COALESCE(l.allocated,0) OR d.discountable_subtotal<>i.subtotal-i.automatic_discount_total OR d.discount_amount<>ROUND(d.discountable_subtotal*d.percent_rate/100,2)",
            'Finalized invoice reporting metadata'=>"SELECT i.id FROM invoices i LEFT JOIN online_invoice_details d ON d.invoice_id=i.id WHERE d.invoice_id IS NULL",
            'Invoice snapshots / totals'=>"SELECT i.id FROM invoices i JOIN online_invoice_details d ON d.invoice_id=i.id LEFT JOIN (SELECT invoice_id,SUM(gross_amount) gross,SUM(discount_amount) discount,SUM(line_total) net FROM invoice_items GROUP BY invoice_id) l ON l.invoice_id=i.id WHERE l.gross IS NULL OR i.subtotal<>l.gross OR i.discount_total<>l.discount OR i.grand_total<>l.net",
            'Posted payment / allocation totals'=>"SELECT p.id FROM payments p LEFT JOIN (SELECT payment_id,SUM(amount) allocated FROM payment_allocations GROUP BY payment_id) x ON x.payment_id=p.id WHERE p.status='POSTED' AND (x.allocated IS NULL OR p.amount<>x.allocated)",
            'Receipts / change / posted payments'=>"SELECT r.request_id FROM invoice_receipts r LEFT JOIN payments p ON p.id=r.payment_id LEFT JOIN payment_allocations x ON x.payment_id=r.payment_id AND x.invoice_id=r.invoice_id WHERE r.received_amount<>r.applied_amount+r.change_amount OR (r.applied_amount>0 AND (p.id IS NULL OR p.status<>'POSTED' OR p.amount<>r.applied_amount OR x.amount IS NULL OR x.amount<>r.applied_amount))",
            'Payments do not exceed invoice totals'=>"SELECT i.id FROM invoices i JOIN payment_allocations x ON x.invoice_id=i.id JOIN payments p ON p.id=x.payment_id WHERE p.status='POSTED' GROUP BY i.id,i.grand_total HAVING SUM(x.amount)+$return>i.grand_total",
            'Void refund liabilities / cancelled debt'=>"SELECT v.invoice_id FROM invoice_voids v JOIN invoices i ON i.id=v.invoice_id LEFT JOIN (SELECT x.invoice_id,SUM(x.amount) paid FROM payment_allocations x JOIN payments p ON p.id=x.payment_id WHERE p.status='POSTED' GROUP BY x.invoice_id) p ON p.invoice_id=i.id WHERE v.refund_due<>COALESCE(p.paid,0) OR v.refund_due+v.cancelled_outstanding+$return<>i.grand_total",
            'Paid and free units match stock deductions'=>"SELECT l.id FROM invoice_items l JOIN online_invoice_details d ON d.invoice_id=l.invoice_id LEFT JOIN inventory_transactions t ON t.invoice_id=l.invoice_id AND t.type='SALE' LEFT JOIN inventory_movements m ON m.transaction_id=t.id AND m.product_id=l.product_id AND m.location_id=d.source_location_id WHERE t.id IS NULL OR m.id IS NULL OR m.quantity_delta<>-(l.quantity+l.free_quantity)",
            'Voids reverse stock exactly once'=>"SELECT l.id FROM invoice_voids v JOIN invoice_items l ON l.invoice_id=v.invoice_id JOIN online_invoice_details d ON d.invoice_id=v.invoice_id LEFT JOIN inventory_transactions t ON t.id=v.reversal_transaction_id LEFT JOIN inventory_movements m ON m.transaction_id=t.id AND m.product_id=l.product_id AND m.location_id=d.source_location_id WHERE t.reverses_transaction_id<>v.invoice_id OR m.id IS NULL OR m.quantity_delta<>l.quantity+l.free_quantity",
            'Transfers conserve stock'=>"SELECT t.id FROM inventory_transactions t LEFT JOIN inventory_movements m ON m.transaction_id=t.id WHERE t.type='TRANSFER' AND m.batch_id IS NULL GROUP BY t.id HAVING COUNT(m.id)<>2 OR COALESCE(SUM(m.quantity_delta),0)<>0",
            'Finalized offline submissions'=>"SELECT o.id FROM sync_operations o LEFT JOIN invoices i ON i.id=o.invoice_id WHERE o.result_status='ACCEPTED' AND (i.id IS NULL OR i.posting_channel<>'SYNC' OR i.rep_id<>o.user_id OR i.device_id<>o.device_id)"
        ];if($return!=='0.00')$queries['Historical return settlement / lines']="SELECT hr.invoice_id FROM historical_customer_returns hr JOIN invoices i ON i.id=hr.invoice_id LEFT JOIN (SELECT invoice_id,SUM(line_value) amount FROM historical_customer_return_lines GROUP BY invoice_id) rl ON rl.invoice_id=hr.invoice_id WHERE hr.customer_id<>i.customer_id OR hr.approved_value<>hr.applied_value+hr.forfeited_value OR hr.applied_value>i.grand_total OR rl.amount IS NULL OR rl.amount<>hr.approved_value";
        $result=[];foreach($queries as $label=>$sql)$result[$label]=(int)$this->one('SELECT COUNT(*) n FROM ('.$sql.') failures')['n'];
        $result['Warehouse movement ledger']=count((new ProductStock($this->pdo))->reconciliation($a));$result['Representative movement ledger']=count((new RepresentativeStock($this->pdo))->reconciliation($a));return $result;
    }
    public function towns(array $actor,array $input):array
    {
        $this->actor($actor);$district=$this->id($input['district_id']??'','district_id');$q=$input['q']??'';$page=$input['page']??'1';
        if(!is_string($q)||!mb_check_encoding($q,'UTF-8')||mb_strlen($q,'UTF-8')>100||!is_string($page)||!preg_match('/^[1-9][0-9]{0,5}$/D',$page))throw new InventoryException(['q'=>'Invalid town search.']);
        if($district!==''&&!$this->one('SELECT id FROM location_districts WHERE id=?',[$district]))throw new InventoryException(['district_id'=>'Unknown district.']);
        $where='1=1';$params=[];if($district!==''){$where.=' AND t.district_id=?';$params[]=$district;}
        if(trim($q)!==''){$where.=" AND t.name LIKE ? ESCAPE '!'";$params[]='%'.strtr(trim($q),['!'=>'!!','%'=>'!%','_'=>'!_']).'%';}
        $rows=$this->rows('SELECT t.id,t.name,t.district_id,d.name district_name FROM location_towns t JOIN location_districts d ON d.id=t.district_id WHERE '.$where.' ORDER BY t.name,t.id LIMIT 51 OFFSET '.(((int)$page-1)*50),$params);$more=count($rows)>50;$rows=array_slice($rows,0,50);foreach($rows as &$row){$row['id']=(string)$row['id'];$row['district_id']=(string)$row['district_id'];}
        return ['towns'=>$rows,'has_more'=>$more];
    }
    public function options(array $actor,array $f):array
    {
        $a=$this->actor($actor);$scope=$a['role']==='ADMIN'?'':' WHERE assigned_rep_id=?';
        $towns=$this->towns($actor,['district_id'=>$f['district_id']]);
        if($f['town_id']!==''&&!in_array($f['town_id'],array_map('strval',array_column($towns['towns'],'id')),true)){
            $selected=$this->one('SELECT t.id,t.district_id,t.name,d.name district_name FROM location_towns t JOIN location_districts d ON d.id=t.district_id WHERE t.id=?',[$f['town_id']]);if($selected){$selected['id']=(string)$selected['id'];$selected['district_id']=(string)$selected['district_id'];array_unshift($towns['towns'],$selected);}
        }
        return ['customers'=>$this->rows('SELECT id,customer_code,shop_name FROM customers'.$scope.' ORDER BY shop_name',$scope?[$a['id']]:[]),'products'=>$this->rows('SELECT id,sku,name,name_si FROM products ORDER BY name'),'categories'=>$this->rows('SELECT id,name FROM categories ORDER BY name'),'representatives'=>$a['role']==='ADMIN'?$this->rows("SELECT id,name FROM users WHERE role='SALES_REP' ORDER BY name"):[$a], 'locations'=>$towns+['districts'=>$this->rows('SELECT CAST(id AS CHAR) id,name FROM location_districts ORDER BY name'),'district_id'=>$f['district_id'],'town_id'=>$f['town_id']]];
    }
    public static function csvCell(mixed $value):string
    {$text=(string)($value??'');if(preg_match('/^[\x00-\x20\p{Z}\x{FEFF}]*[=+\-@]/u',$text)||preg_match('/^[\t\r\n]/',$text))$text="'".$text;return $text;}
}
