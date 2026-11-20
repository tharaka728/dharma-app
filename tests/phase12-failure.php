<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';if(PHP_SAPI!=='cli')exit(1);
putenv('DB_HOST=127.0.0.1');putenv('DB_PORT=1');putenv('DB_NAME=dharma_sales_phase9_test');putenv('DB_USER=unavailable_test');putenv('DB_PASSWORD=not-a-real-password');
ob_start();(new App\Controllers\HealthController())->show(true);$body=ob_get_clean();
if(http_response_code()!==503||str_contains($body,'not-a-real-password')||str_contains($body,'PDO')||json_decode($body,true)['status']!=='setup_required')throw new RuntimeException('Unsafe database-failure handling');
echo "Database-unavailable health returns sanitized HTTP 503 without credentials or SQL details.\n";
