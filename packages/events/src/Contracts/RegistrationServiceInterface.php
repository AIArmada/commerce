<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Models\EventRegistration;

interface RegistrationServiceInterface
{
    public function register(array $data): EventRegistration;

    public function approve(EventRegistration $registration, mixed $actor = null): void;

    public function cancel(EventRegistration $registration, ?string $reason = null, mixed $actor = null): void;

    public function reject(EventRegistration $registration, string $reason, mixed $actor = null): void;

    public function waitlist(EventRegistration $registration): void;

    public function complete(EventRegistration $registration): void;

    public function refund(EventRegistration $registration, ?string $reason = null): void;

    public function createFromOrderItem(array $orderItemData): void;
}
