<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Data;

use DateTimeImmutable;

final readonly class InvoiceRenderData
{
    /** @param list<array{name: string, description: string, quantity: int, unit_price_minor: int}> $items */
    public function __construct(
        public string $id,
        public ?string $number,
        public ?DateTimeImmutable $date,
        public ?DateTimeImmutable $dueDate,
        public int $totalMinor,
        public string $currency,
        public ?string $customerName,
        public ?string $customerEmail,
        public ?string $customerPhone,
        public ?string $status,
        public array $items,
    ) {}
}
