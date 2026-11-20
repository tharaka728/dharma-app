<?php
declare(strict_types=1);
namespace App\Support;
use PDO;
final class Migrator
{
    public static function run(PDO $pdo): void
    {
        $server = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $maria = stripos($server, 'MariaDB') !== false;
        if (!preg_match('/^(\d+\.\d+\.\d+)/', $server, $m) || version_compare($m[1], $maria ? '10.4.0' : '8.0.16', '<')) {
            throw new \RuntimeException('Requires MariaDB >=10.4 or MySQL >=8.0.16 with enforced CHECK constraints.');
        }
        if ((int) $pdo->query("SELECT GET_LOCK('dharma_schema_migrations',10)")->fetchColumn() !== 1) {
            throw new \RuntimeException('Another migration is running.');
        }
        try {
            $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(150) PRIMARY KEY, checksum CHAR(64) NOT NULL, status VARCHAR(10) NOT NULL, applied_at_utc DATETIME(6) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $files = glob(APP_ROOT . '/database/migrations/*.sql');
            sort($files, SORT_STRING);
            foreach ($files as $file) {
                $name = basename($file);
                $hash = hash_file('sha256', $file);
                $q = $pdo->prepare('SELECT checksum,status FROM schema_migrations WHERE version=?');
                $q->execute([$name]);
                $old = $q->fetch();
                if ($old) {
                    if ($old['checksum'] !== $hash || $old['status'] !== 'applied') {
                        throw new \RuntimeException('Migration changed or partially applied: ' . $name . '. Restore a backup or manually reconcile DDL; do not rerun blindly.');
                    }
                    echo "Already applied: $name\n";
                    continue;
                }
                $pdo->prepare("INSERT INTO schema_migrations(version,checksum,status) VALUES (?,?,'applying')")->execute([$name,$hash]);
                // Files contain simple SQL statements only, no delimiters or semicolons in literals.
                foreach (explode(';', file_get_contents($file)) as $sql) {
                    if (trim($sql) !== '') { $pdo->exec($sql); }
                }
                $pdo->prepare("UPDATE schema_migrations SET status='applied',applied_at_utc=UTC_TIMESTAMP(6) WHERE version=?")->execute([$name]);
                echo "Applied: $name\n";
            }
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('dharma_schema_migrations')");
        }
    }
}
