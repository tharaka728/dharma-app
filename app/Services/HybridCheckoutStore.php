<?php
declare(strict_types=1);
namespace App\Services;

/**
 * Transactional writer port. Stage 2C PDO implementation is verified-disposable/CLI only.
 * All operations must share the context transaction and obey its lock order.
 * command() locks UUID globally; replayResult() reauthorizes current invoice/customer scope.
 * postOfficial() owns existing numbering, stock, payments and identity collisions, without committing.
 * persistSnapshot() inserts parents/children then seal last. complete() appends audit/result last.
 */
interface HybridCheckoutStore extends HybridInvoiceContext
{
    public function command(string $uuid): ?array;
    public function replayResult(array $actor, array $command): array;
    public function claim(array $request, string $actor, string $payloadHash): void;
    public function financialPostingHeld(): bool;
    public function postOfficial(array $request, array $prepared, array $mapping): array;
    public function persistSnapshot(string $invoice, array $snapshot): void;
    public function complete(string $uuid, array $result): void;
}
