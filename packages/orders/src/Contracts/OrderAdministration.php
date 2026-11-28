<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

interface OrderAdministration
{
    public function createDraft(?string $notes = null, ?string $internalNotes = null): string;

    /** @param array<string, mixed> $data */
    public function updateNotes(string $orderId, array $data): void;

    /** @param array<string, mixed> $itemData */
    public function addItem(string $orderId, array $itemData): string;

    /** @param array<string, mixed> $addressData */
    public function addAddress(string $orderId, array $addressData, string $type): void;

    public function recalculateTotals(string $orderId): void;

    /** @param list<string> $orderIds */
    public function deleteDrafts(array $orderIds): void;
}
