<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

interface RegistrationServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data): string;

    public function approve(string $registrationId, mixed $actor = null): void;

    public function cancel(string $registrationId, ?string $reason = null, mixed $actor = null): void;

    public function reject(string $registrationId, string $reason, mixed $actor = null): void;

    public function waitlist(string $registrationId): void;

    public function complete(string $registrationId): void;

    public function refund(string $registrationId, ?string $reason = null): void;

    public function markRefundPending(string $registrationId, ?string $reason = null): void;

    public function restoreFromRefundPending(string $registrationId, ?string $reason = null): void;

    /**
     * @param  array<string, mixed>  $orderItemData
     */
    public function createFromOrderItem(array $orderItemData): void;
}
