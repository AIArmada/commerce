<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Events\RegistrationCheckedIn;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Orders\Contracts\OrderServiceInterface;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Processing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

final class SyncEventOrderCompletionAction
{
    public function __construct(
        private readonly OrderServiceInterface $orders,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(Order $order, array $metadata = [], ?CarbonImmutable $asOf = null): bool
    {
        $resolvedAt = $asOf ?? CarbonImmutable::now('UTC');

        return $this->withRecordOwnerContext($order, fn (): bool => $this->syncWithinOwnerContext($order, $metadata, $resolvedAt));
    }

    public function handleCheckedInRegistration(RegistrationCheckedIn $event): void
    {
        $resolvedAt = CarbonImmutable::now('UTC');

        $registration = Registration::query()
            ->withoutOwnerScope()
            ->with([
                'occurrence' => fn ($query) => $query->withoutOwnerScope(),
            ])
            ->find($event->registration->getKey());

        if (! $registration instanceof Registration) {
            return;
        }

        $this->withRecordOwnerContext($registration, function () use ($registration, $resolvedAt): void {
            $orderId = is_string($registration->order_id) ? $registration->order_id : null;

            if ($orderId === null || $orderId === '') {
                return;
            }

            if (! $this->occurrenceHasEnded($registration->occurrence, $resolvedAt)) {
                return;
            }

            $order = Order::query()->find($orderId);

            if (! $order instanceof Order) {
                return;
            }

            $this->syncWithinOwnerContext($order, [
                'source' => 'registration_checked_in',
                'registration_id' => $registration->id,
            ], $resolvedAt);
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function syncWithinOwnerContext(Order $order, array $metadata, CarbonImmutable $asOf): bool
    {
        $freshOrder = Order::query()->find($order->getKey());

        if (! $freshOrder instanceof Order || ! $freshOrder->status instanceof Processing) {
            return false;
        }

        $registrations = Registration::query()
            ->with('occurrence')
            ->where('order_id', $freshOrder->getKey())
            ->get();

        if ($registrations->isEmpty()) {
            return false;
        }

        if (! $registrations->every(fn (Registration $registration): bool => $registration->status->isTerminal())) {
            return false;
        }

        if (! $registrations->every(fn (Registration $registration): bool => $this->occurrenceHasEnded($registration->occurrence, $asOf))) {
            return false;
        }

        $this->orders->complete($freshOrder, array_merge([
            'source' => 'event_fulfillment',
            'completed_at' => $asOf->toIso8601String(),
            'completion_reason' => 'event_fulfilled',
        ], $metadata));

        return true;
    }

    private function occurrenceHasEnded(?Occurrence $occurrence, CarbonImmutable $asOf): bool
    {
        if (! $occurrence instanceof Occurrence) {
            return false;
        }

        if ($occurrence->ends_at !== null) {
            return $occurrence->ends_at->lte($asOf);
        }

        if ($occurrence->check_in_closes_at !== null) {
            return $occurrence->check_in_closes_at->lte($asOf);
        }

        return false;
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function withRecordOwnerContext(Model $record, callable $callback): mixed
    {
        $owner = OwnerContext::fromTypeAndId(
            is_string($record->getAttribute('owner_type')) ? $record->getAttribute('owner_type') : null,
            is_scalar($record->getAttribute('owner_id')) ? (string) $record->getAttribute('owner_id') : null,
        );

        return OwnerContext::withOwner($owner, $callback);
    }
}
