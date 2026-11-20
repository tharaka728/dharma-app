<?php
declare(strict_types=1);
namespace App\Support;
final class ProductException extends \RuntimeException
{
    public function __construct(public array $errors, public int $status = 422) { parent::__construct('Product request rejected.'); }
}
