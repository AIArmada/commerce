<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

interface OrderAdministration
{
    public function createDraft(?string $notes = null, ?string $internalNotes = null): string;

    /** @param array<string, mixed> $data */
    public function updateNotes(string $orderId, array $data): void;

    /** @param list<string> $orderIds */
    public function deleteDrafts(array $orderIds): void;
}
