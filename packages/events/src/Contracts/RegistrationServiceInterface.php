<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface RegistrationServiceInterface
{
    /**
     * @param  array<string, mixed>  $participant
     * @param  array<string, mixed>  $links
     */
    public function createForOccurrence(Occurrence $occurrence, array $participant, array $links = []): Registration;

    /**
     * @param  array<string, mixed>  $participant
     * @param  array<string, mixed>  $links
     */
    public function recordWalkInForOccurrence(Occurrence $occurrence, array $participant = [], array $links = []): Registration;

    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @return Collection<int, Registration>
     */
    public function createBatchForOrderItem(
        Occurrence $occurrence,
        OrderItem $orderItem,
        array $participants,
        ?Customer $purchaser = null,
    ): Collection;

    /**
     * @param  array<string, mixed>  $context
     */
    public function checkIn(Registration $registration, array $context = []): Registration;

    public function cancel(Registration $registration, ?string $reason = null): Registration;

    /**
     * @param  array<string, mixed>  $context
     */
    public function approve(Registration $registration, ?Model $actor = null, array $context = []): Registration;

    /**
     * @param  array<string, mixed>  $context
     */
    public function reject(Registration $registration, ?Model $actor = null, ?string $reason = null, array $context = []): Registration;

    /**
     * @param  array<string, mixed>  $context
     */
    public function refund(Registration $registration, ?string $reason = null, array $context = []): Registration;

    /**
     * @param  array<string, mixed>  $context
     */
    public function markNoShow(Registration $registration, array $context = []): Registration;
}
