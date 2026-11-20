<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\Services\ProductReturnCalculation as ReturnCalculation;

$cases=[
    ['4000.00','4300.00','4000.00','0.00','300.00'],
    ['6000.00','4500.00','4500.00','1500.00','0.00'],
    ['4000.00','4000.00','4000.00','0.00','0.00'],
    ['10.00','0.00','0.00','10.00','0.00'],
];
foreach($cases as [$value,$invoice,$applied,$forfeited,$remaining]){
    $actual=ReturnCalculation::settlement($value,$invoice);
    if($actual['applied_value']!==$applied||$actual['forfeited_value']!==$forfeited||$actual['remaining_payable']!==$remaining)
        throw new RuntimeException('Settlement calculation failed.');
}
if(ReturnCalculation::historicalLine('123.45','3')!=='370.35')throw new RuntimeException('Historical line calculation failed.');
try{ReturnCalculation::historicalLine('9999999999999.99','2');throw new RuntimeException('Overflow accepted.');}
catch(\App\Support\InventoryException $e){}
echo "6 return calculation checks passed.\n";
