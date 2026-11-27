<?php
declare(strict_types=1);
require_once __DIR__.'/hybrid-fixtures.php';
function stage5Line(int $number,string $paid,string $free='0',string $condition='SALEABLE'):array
{$l=['line_number'=>(string)$number,'paid_quantity'=>$paid,'free_quantity'=>$free,'condition_reason'=>'Synthetic inspected condition','holding_reference'=>'Synthetic controlled bin'];foreach(['paid'=>$paid,'free'=>$free]as$key=>$value){$l[$key.'_conditions']=array_fill_keys(['SALEABLE','QUARANTINE','UNSALEABLE'],'0');$l[$key.'_conditions'][$condition]=$value;}return$l;}
function stage5Request(string $invoice,array $lines):array{return ['policy_version'=>'HYBRID_RETURN_V1','request_id'=>hybridUuid(),'invoice_id'=>$invoice,'reason'=>'Synthetic Stage 5 return','operation'=>'RETURN','lines'=>$lines];}
function stage5Controls(PDO $pdo,string $actor,int $enabled):void
{$pdo->beginTransaction();try{$before=$pdo->query('SELECT * FROM hybrid_operating_state WHERE id=1 FOR UPDATE')->fetch();$pdo->exec('UPDATE hybrid_operating_state SET enabled='.$enabled.',sale_posting='.$enabled.',return_posting='.$enabled.',custody='.$enabled.',version=version+1 WHERE id=1');$after=$pdo->query('SELECT * FROM hybrid_operating_state WHERE id=1')->fetch();$pdo->prepare('INSERT INTO hybrid_control_events(actor_user_id,previous_version,new_version,reason,previous_json,new_json) VALUES (?,?,?,?,?,?)')->execute([$actor,$before['version'],$after['version'],'Synthetic Stage 5 fixture controls only',json_encode($before,JSON_THROW_ON_ERROR),json_encode($after,JSON_THROW_ON_ERROR)]);$pdo->commit();}catch(Throwable$e){$pdo->rollBack();throw$e;}}
function stage5Prepare(PDO $pdo,array $m):array
{
 $f=hybridFixtures($pdo,$m);$rep=['id'=>$f['users']['rep1']];$admin=['id'=>$f['users']['admin']];$svc=hybridTestService($pdo,$m,$f);$cfg=hybridTestConfig($m,$f);$cfg['return_posting']=$cfg['custody']=true;$returns=new App\Services\HybridReturns($pdo,$cfg,$m,false);stage5Controls($pdo,$admin['id'],1);
 try{
  $pdo->prepare('UPDATE products SET name_si=? WHERE id=?')->execute(['ග්ලූකෝස්',$f['products']['glucose']]);$pdo->prepare('UPDATE products SET name_si=? WHERE id=?')->execute(['සුවඳ කුඩු',$f['products']['suwada']]);
  $in=hybridInput($f);$in['hybrid']['lines'][]=['product_id'=>$f['products']['suwada'],'paid_quantity'=>'18','product_discount'=>['choice'=>'35']];$in['hybrid']['lines'][]=['product_id'=>$f['products']['replacement'],'paid_quantity'=>'24','product_discount'=>['choice'=>'0'],'replace_free_entitlement'=>true];$in['hybrid']['replacement_discount']=['choice'=>'25'];
  $in['hybrid']['lines'][]=['product_id'=>$f['products']['ordinary'],'paid_quantity'=>'3','product_discount'=>['choice'=>'30']];$in['hybrid']['lines'][]=['product_id'=>$f['products']['partial'],'paid_quantity'=>'5','product_discount'=>['choice'=>'other','percent'=>'12.50']];$in['review_hash']=$svc->review($rep,$in)['review_hash'];$issued=$svc->issue($rep,$in);$id=$issued['invoice_id'];
  $pending=stage5Request($id,[stage5Line(1,'3','0','QUARANTINE')]);$custody=$returns->receiveCustody($rep,$pending)['custody_id'];
  $returns->supplementCustody($rep,['policy_version'=>'HYBRID_RETURN_V1','request_id'=>hybridUuid(),'invoice_id'=>$id,'custody_id'=>$custody,'custody_version'=>'1','reason'=>'Synthetic recovered free goods','lines'=>[stage5Line(1,'0','1','UNSALEABLE')]]);
  $request=stage5Request($id,[]);$request['custody_id']=$custody;$request['custody_version']='2';$request['review_hash']=$returns->preview($rep,$request)['review_hash'];$returned=$returns->post($rep,$request);
  $returns->manageCustody($admin,['policy_version'=>'HYBRID_RETURN_V1','request_id'=>hybridUuid(),'invoice_id'=>$id,'custody_id'=>$custody,'custody_version'=>'3','reason'=>'Synthetic manager release after inspection','action'=>'RELEASE','line_number'=>'1','unit_kind'=>'paid','from'=>'QUARANTINE','quantity'=>'1']);
  $void=stage5Request($id,[stage5Line(1,'5'),stage5Line(2,'18','1'),stage5Line(3,'24'),stage5Line(4,'3'),stage5Line(5,'5')]);$void['operation']='VOID';$void['review_hash']=$returns->preview($admin,$void)['review_hash'];$voided=$returns->post($admin,$void);
  // Metadata for future artifacts only; never relabel an existing stored invoice.
  return ['invoice_discount_eligibility'=>'UNPROMOTED_LINES_V1','fixtures'=>$f,'invoice'=>$id,'return'=>$returned['return_id'],'void'=>$voided['return_id'],'rep'=>$rep,'admin'=>$admin];
 }finally{stage5Controls($pdo,$admin['id'],0);}
}
