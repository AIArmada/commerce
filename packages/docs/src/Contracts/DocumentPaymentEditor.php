<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

interface DocumentPaymentEditor
{
    /** @param array<string, mixed> $data */
    public function edit(string $docId, string $paymentId, array $data): void;
}
