<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventVerification;

interface EventVerificationService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function verify(mixed $target, string $verificationType, array $context = []): EventVerification;

    public function revoke(EventVerification $verification, string $reason): void;
}
