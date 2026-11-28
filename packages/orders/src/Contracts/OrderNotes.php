<?php

declare(strict_types=1);

namespace AIArmada\Orders\Contracts;

interface OrderNotes
{
    public function add(string $orderId, string $content, string $visibility, ?string $actorId = null): string;

    public function edit(string $orderId, string $noteId, string $content, string $visibility): void;

    /** @param list<string> $noteIds */
    public function remove(string $orderId, array $noteIds): void;
}
