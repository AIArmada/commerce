<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Events\Services\RegistrationService;
use AIArmada\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class FinalizeOccurredEventOrdersAction
{
    public function __construct(
        private readonly RegistrationService $registrations,
        private readonly SyncEventOrderCompletionAction $syncEventOrderCompletion,
    ) {}

    /**
     * @return array{orders_reviewed:int, registrations_marked_no_show:int, orders_completed:int}
     */
    public function handle(?CarbonImmutable $asOf = null): array
    {
        $resolvedAt = $asOf ?? CarbonImmutable::now('UTC');

        return $this->finalizeAcrossOwners($resolvedAt);
    }

    /**
     * @return array{orders_reviewed:int, registrations_marked_no_show:int, orders_completed:int}
     */
    private function finalizeAcrossOwners(CarbonImmutable $asOf): array
    {
        $orderIds = $this->endedOrderIds($asOf);
        $registrationsMarkedNoShow = 0;

        $confirmedRegistrations = Registration::query()
            ->withoutOwnerScope()
            ->with([
                'occurrence' => fn ($query) => $query->withoutOwnerScope(),
            ])
            ->whereNotNull('order_id')
            ->where('status', RegistrationStatus::Confirmed)
            ->whereHas('occurrence', function ($query) use ($asOf): void {
                /** @var Builder<Occurrence> $query */
                $this->applyEndedOccurrenceConstraint($query->withoutOwnerScope(), $asOf);
            })
            ->get();

        foreach ($confirmedRegistrations as $registration) {
            $this->withRecordOwnerContext($registration, function () use ($registration): void {
                $this->registrations->markNoShow($registration, [
                    'source' => 'scheduled_finalizer',
                ]);
            });

            $registrationsMarkedNoShow++;
        }

        $ordersCompleted = 0;

        if ($orderIds->isNotEmpty()) {
            $orders = Order::query()
                ->withoutOwnerScope()
                ->whereIn('id', $orderIds->all())
                ->get();

            foreach ($orders as $order) {
                if ($this->syncEventOrderCompletion->handle($order, ['source' => 'scheduled_finalizer'], $asOf)) {
                    $ordersCompleted++;
                }
            }
        }

        return [
            'orders_reviewed' => $orderIds->count(),
            'registrations_marked_no_show' => $registrationsMarkedNoShow,
            'orders_completed' => $ordersCompleted,
        ];
    }

    /**
     * @return Collection<int, non-empty-string>
     */
    private function endedOrderIds(CarbonImmutable $asOf): Collection
    {
        return Registration::query()
            ->withoutOwnerScope()
            ->whereNotNull('order_id')
            ->whereHas('occurrence', function ($query) use ($asOf): void {
                /** @var Builder<Occurrence> $query */
                $this->applyEndedOccurrenceConstraint($query->withoutOwnerScope(), $asOf);
            })
            ->pluck('order_id')
            ->filter(fn (mixed $orderId): bool => is_string($orderId) && $orderId !== '')
            ->unique()
            ->values();
    }

    /**
     * @param  Builder<Occurrence>  $query
     * @return Builder<Occurrence>
     */
    private function applyEndedOccurrenceConstraint(Builder $query, CarbonImmutable $asOf): Builder
    {
        return $query->where(function (Builder $endedQuery) use ($asOf): void {
            $endedQuery
                ->where(function (Builder $byEndTime) use ($asOf): void {
                    $byEndTime
                        ->whereNotNull('ends_at')
                        ->where('ends_at', '<=', $asOf);
                })
                ->orWhere(function (Builder $byCheckInClose) use ($asOf): void {
                    $byCheckInClose
                        ->whereNull('ends_at')
                        ->whereNotNull('check_in_closes_at')
                        ->where('check_in_closes_at', '<=', $asOf);
                });
        });
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
