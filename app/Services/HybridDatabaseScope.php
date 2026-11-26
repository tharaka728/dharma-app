<?php
declare(strict_types=1);
namespace App\Services;
use PDO;
use App\Support\InventoryException;

/** Stage 2C physical-server proof. Name checks alone are insufficient. CLI only. */
final class HybridDatabaseScope
{
    public static function verify(PDO $pdo, ?array $scope): void
    {
        $fail=static function (): void { throw new InventoryException(['hybrid'=>'A physically verified disposable Stage 2C database is required.'],409); };
        if (PHP_SAPI!=='cli' || !$scope || !preg_match('/^dharma_sales_hybrid_[a-f0-9]{32}_test$/D',$scope['database']??'')
            || !is_int($scope['port']??null) || in_array($scope['port'],[3306,3309],true)) { $fail(); }
        $root=realpath(dirname(__DIR__,2).'/storage'); $expected=realpath($scope['datadir']??'');
        $norm=static fn($p)=>strtolower(rtrim(str_replace('\\','/',(string)$p),'/'));
        if (!$root || !$expected || !preg_match('~^'.preg_quote($norm($root),'~').'/stage2c-[a-f0-9]{32}/data$~D',$norm($expected))) { $fail(); }
        $row=$pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['db']!==$scope['database'] || (int)$row['port']!==$scope['port'] || $norm($row['datadir'])!==$norm($expected)) { $fail(); }
        $identity=$pdo->query('SELECT run_id FROM hybrid_test_identity')->fetchColumn();
        if ($identity!==($scope['run_id']??null)) { $fail(); }
        $grants=$pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($grants as $grant) {
            if (strpos($grant,'GRANT USAGE ON *.* TO ')===0) { continue; }
            if (strpos($grant,'GRANT SELECT, INSERT, UPDATE, DELETE ON `'.$scope['database'].'`.* TO ')!==0) { $fail(); }
        }
        if (count($grants)!==2 || $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES)) { $fail(); }
    }
}
