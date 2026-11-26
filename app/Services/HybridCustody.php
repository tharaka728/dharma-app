<?php
declare(strict_types=1);
namespace App\Services;

/** Public physical-workflow facade. Financial return acceptance remains a separate action. */
final class HybridCustody
{
    public function __construct(private HybridReturns $returns){}
    public function receive(array $actor,array $input):array{return $this->returns->receiveCustody($actor,$input);}
    public function supplement(array $actor,array $input):array{return $this->returns->supplementCustody($actor,$input);}
    public function manage(array $actor,array $input):array{return $this->returns->manageCustody($actor,$input);}
    public function get(array $actor,string $invoice,string $id):array{return $this->returns->custodyDetails($actor,$invoice,$id);}
}
