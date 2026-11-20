<?php
declare(strict_types=1);
namespace App\Support;
final class CustomerException extends \RuntimeException
{
    public function __construct(public array $errors,public int $status=422,public array $duplicates=[]) { parent::__construct('Customer request rejected.'); }
}
