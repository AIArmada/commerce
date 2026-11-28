<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

interface OrderItemFulfillmentMarker
{
    /** @return array{options: array<string, mixed>|null}|null */
    public function mark(string $orderItemId, string $key, string $value): ?array;

    /** @param array<string, mixed>|null $originalOptions */
    public function restore(string $orderItemId, string $key, string $value, ?array $originalOptions): void;
}
