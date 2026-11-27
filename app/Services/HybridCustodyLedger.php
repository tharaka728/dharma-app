<?php
declare(strict_types=1);
namespace App\Services;

/** Physical custody ledger, never a substitute for financial return position history. */
final class HybridCustodyLedger
{
    public static function rebuild(string $id,array $events):array
    {
        $p=['custody_id'=>$id,'version'=>0,'status'=>'PENDING','accepted_return_id'=>null,'lines'=>[]];
        foreach($events as $event){
            if((int)$event['version']!==$p['version']+1)throw new \LogicException('Custody event revision mismatch.');
            $d=$event['data'];$type=$event['type'];
            if(in_array($type,['RECEIVED','SUPPLEMENT'],true)){
                if($p['status']==='ACCEPTED'||($type==='RECEIVED'&&$p['version']!==0))throw new \LogicException('Custody receipt already accepted or initialized.');
                foreach(HybridReturnRequest::lines($d['lines'])as$line){
                    $key=$line['line_number'];if(!isset($p['lines'][$key]))$p['lines'][$key]=['received'=>['paid'=>array_fill_keys(HybridReturnRequest::CONDITIONS,0),'free'=>array_fill_keys(HybridReturnRequest::CONDITIONS,0)],'held'=>['paid'=>array_fill_keys(HybridReturnRequest::CONDITIONS,0),'free'=>array_fill_keys(HybridReturnRequest::CONDITIONS,0)],'released'=>['paid'=>0,'free'=>0],'disposed'=>['paid'=>0,'free'=>0]];
                    foreach(['paid','free']as$kind)foreach(HybridReturnRequest::CONDITIONS as$condition){
                        $q=Inventory::quantity($line[$kind.'_conditions'][$condition],true);
                        foreach(['received','held']as$bucket){if($q>PHP_INT_MAX-$p['lines'][$key][$bucket][$kind][$condition])throw new \LogicException('Custody quantity overflow.');$p['lines'][$key][$bucket][$kind][$condition]+=$q;}
                    }
                }
            }elseif($type==='ACCEPTED'){
                if($p['status']!=='PENDING')throw new \LogicException('Only pending custody may become a financially accepted return.');
                $p['status']='ACCEPTED';$p['accepted_return_id']=$d['return_id'];
                foreach($p['lines']as&$line)foreach(['paid','free']as$kind){$line['released'][$kind]+=$line['held'][$kind]['SALEABLE'];$line['held'][$kind]['SALEABLE']=0;}unset($line);
            }elseif($type==='REVIEW'){
                if($p['status']==='REJECTED')$p['status']='PENDING';
            }elseif($type==='REJECT'){
                if($p['status']==='ACCEPTED')throw new \LogicException('An accepted financial return cannot be rejected through custody.');$p['status']='REJECTED';
            }elseif(in_array($type,['RECLASSIFY','RELEASE','DISPOSE'],true)){
                if($p['status']!=='ACCEPTED')throw new \LogicException('Pending/rejected custody cannot enter available stock or final disposition.');
                $key=$d['line_number'];$kind=$d['unit_kind'];$from=$d['from'];$to=$d['to'];$q=Inventory::quantity($d['quantity']);
                if(!isset($p['lines'][$key])||!in_array($kind,['paid','free'],true)||!in_array($from,['QUARANTINE','UNSALEABLE'],true)||$q>$p['lines'][$key]['held'][$kind][$from])throw new \LogicException('Custody movement exceeds held physical units.');
                $p['lines'][$key]['held'][$kind][$from]-=$q;
                if($type==='RECLASSIFY'){
                    if(!in_array($to,['QUARANTINE','UNSALEABLE'],true)||$from===$to)throw new \LogicException('Invalid non-saleable reclassification.');$p['lines'][$key]['held'][$kind][$to]+=$q;
                }elseif($type==='RELEASE'){
                    if($from!=='QUARANTINE'||$to!=='SALEABLE')throw new \LogicException('Release requires verified quarantine goods.');$p['lines'][$key]['released'][$kind]+=$q;
                }else{if($to!=='DISPOSED')throw new \LogicException('Invalid disposal destination.');$p['lines'][$key]['disposed'][$kind]+=$q;}
            }else throw new \LogicException('Unsupported custody event.');
            $p['version']++;
        }
        foreach($p['lines']as$line)foreach(['paid','free']as$kind){
            if(array_sum($line['received'][$kind])!==array_sum($line['held'][$kind])+$line['released'][$kind]+$line['disposed'][$kind])throw new \LogicException('Physical custody conservation failed.');
        }
        ksort($p['lines'],SORT_NUMERIC);return $p;
    }
    public static function receiptLines(array $projection):array
    {
        $out=[];foreach($projection['lines']as$number=>$line){
            $r=['line_number'=>(string)$number,'paid_quantity'=>(string)array_sum($line['received']['paid']),'free_quantity'=>(string)array_sum($line['received']['free']),
                'condition_reason'=>'Recorded custody intake evidence','holding_reference'=>'Custody '.$projection['custody_id']];
            foreach(['paid','free']as$kind)$r[$kind.'_conditions']=array_map('strval',$line['received'][$kind]);$out[]=$r;
        }return HybridReturnRequest::lines($out);
    }
}
