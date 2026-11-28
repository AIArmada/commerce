<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\EntryPoints\Jobs\DispatchCommunicationDeliveriesJob;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Events\CommunicationExpired;
use AIArmada\Communications\Features\Aggregate\Api\ScheduledCommunicationLifecycle;
use AIArmada\Communications\Features\Delivery\Api\ScheduledDeliveryLifecycle;
use AIArmada\Communications\Models\Communication;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;

final readonly class ManageScheduledCommunications implements ScheduledCommunicationLifecycle
{
    public function __construct(private ScheduledDeliveryLifecycle $deliveries) {}

    public function countDue(): int
    {
        return $this->dueQuery(CarbonImmutable::now())->count();
    }

    public function dueIds(int $batchSize): iterable
    {
        foreach ($this->dueQuery(CarbonImmutable::now())->select('id')->lazyById(max(1, $batchSize)) as $communication) {
            yield $communication->id;
        }
    }

    public function dispatch(string $id): bool
    {
        return (new Communication)->getConnection()->transaction(function () use ($id): bool {
            $now = CarbonImmutable::now();
            $communication = $this->dueQuery($now)->whereKey($id)->lockForUpdate()->first();

            if ($communication === null) {
                return false;
            }

            if (Communication::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Communication::class, $id);
            }

            $communication->status = CommunicationStatus::Queued;
            $communication->queued_at = $now;
            $communication->save();
            $this->deliveries->queueDue($id, $now);

            DispatchCommunicationDeliveriesJob::dispatch(
                communicationId: $id,
                ownerType: $communication->owner_type,
                ownerId: $communication->owner_id,
                ownerIsGlobal: $communication->owner_type === null && $communication->owner_id === null,
            )->afterCommit();

            return true;
        });
    }

    public function countExpired(): int
    {
        return $this->expiredQuery()->count();
    }

    public function expiredIds(): iterable
    {
        foreach ($this->expiredQuery()->select('id')->lazyById(100) as $communication) {
            yield $communication->id;
        }
    }

    public function expire(string $id): bool
    {
        return (new Communication)->getConnection()->transaction(function () use ($id): bool {
            $communication = $this->expiredQuery()->whereKey($id)->lockForUpdate()->first();

            if ($communication === null) {
                return false;
            }

            if (Communication::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Communication::class, $id);
            }

            $communication->status = CommunicationStatus::Expired;
            $communication->save();

            $deliveryOwner = $communication->owner_type !== null || $communication->owner_id !== null
                ? OwnerContext::fromTypeAndId($communication->owner_type, $communication->owner_id)
                : null;
            $connection = (new Communication)->getConnection();

            $connection->afterCommit(function () use ($id, $deliveryOwner): void {
                if (Communication::ownerScopeConfig()->enabled) {
                    OwnerContext::withOwner($deliveryOwner, fn () => Event::dispatch(new CommunicationExpired(communicationId: $id)));

                    return;
                }

                Event::dispatch(new CommunicationExpired(communicationId: $id));
            });

            $this->deliveries->expire($id);

            return true;
        });
    }

    /** @return Builder<Communication> */
    private function dueQuery(CarbonImmutable $now): Builder
    {
        return Communication::query()
            ->where('status', CommunicationStatus::Scheduled->value)
            ->where('scheduled_at', '<=', $now)
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            });
    }

    /** @return Builder<Communication> */
    private function expiredQuery(): Builder
    {
        return Communication::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', CarbonImmutable::now())
            ->whereNotIn('status', [
                CommunicationStatus::Completed->value,
                CommunicationStatus::Failed->value,
                CommunicationStatus::Cancelled->value,
                CommunicationStatus::Expired->value,
            ]);
    }
}
