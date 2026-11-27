<?php
declare(strict_types=1);
namespace App\Services;

/** Server authority port. Never implement from browser-supplied financial data. */
interface HybridInvoiceContext
{
    public function transaction(callable $work, bool $readOnly): array;
    public function database(): string;
    public function schemaReady(): bool;
    public function inventoryGate(): array;
    public function controls(): ?array;
    public function actor(array $identity): array;
    public function source(string $source): void;
    public function customer(string $id): ?array;
    public function promotionGate(): void;
    public function product(string $id): ?array;
    public function quote(array $actor, string $id, string $quantity, \DateTimeImmutable $instant): array;
    public function balance(array $settings, string $source, string $product): array;
    public function outstanding(array $actor, string $customer): string;
}
