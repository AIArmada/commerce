<?php

declare(strict_types=1);

namespace AIArmada\Docs\Contracts;

interface DocumentPaymentRecorder
{
    /** @param  array<string, mixed>  $paymentData */
    public function record(string $docId, array $paymentData): string;
}
