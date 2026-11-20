<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\Reports;use App\Support\{Http,InventoryException};
final class ReportController
{
    public function __construct(private Reports $reports){}
    public static function route(string $path):?array
    {
        if(!preg_match('~^/(admin|rep)/reports(?:/([a-z]+))?(?:/(csv))?$~D',$path,$m))return null;
        $type=$m[2]??'overview';if($type!=='towns'&&!isset(Reports::TYPES[$type]))return null;
        return ['role'=>$m[1]==='admin'?'ADMIN':'SALES_REP','type'=>$type,'csv'=>isset($m[3]),'methods'=>['GET']];
    }
    public function handle(array $route,array $user):void
    {
        header('Cache-Control: private, no-store, max-age=0');header('Pragma: no-cache');header('Referrer-Policy: no-referrer');
        try{
            if($route['type']==='towns'){Http::json($this->reports->towns($user,$_GET));return;}
            $report=$this->reports->report($user,$route['type'],$_GET,$route['csv']);
            // Presentation only: preserve raw report values and immutable invoice snapshots.
            $productColumn=match($route['type']){'inventory','movements'=>'name','products'=>'product_name_snapshot','returns'=>'product_name',default=>null};
            if($productColumn!==null){
                foreach($report['rows'] as &$row){$row[$productColumn]=\App\Support\I18n::product($row);unset($row['name_si'],$row['product_name_si']);}unset($row);
                $report['columns']=array_values(array_filter($report['columns'],static fn($column)=>!in_array($column,['name_si','product_name_si'],true)));
            }
            if($route['csv']){
                header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="Dharma-'.$route['type'].'-'.$report['filters']['from'].'-'.$report['filters']['to'].'.csv"');
                $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,array_map(static fn($label)=>Reports::csvCell(\App\Support\I18n::text((string)$label)),$report['columns']));foreach($report['rows'] as $row)fputcsv($out,array_map([Reports::class,'csvCell'],array_values($row)));fclose($out);return;
            }
            $admin=$route['role']==='ADMIN';$base=$admin?'/admin/reports':'/rep/reports';$options=$this->reports->options($user,$report['filters']);$reportTypes=$this->reports->availableTypes();require APP_ROOT.'/app/Views/reports.php';
        }catch(InventoryException $e){Http::json(['errors'=>$e->errors],$e->status);}
    }
}
