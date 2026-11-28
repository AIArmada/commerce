<?php

declare(strict_types=1);

namespace AIArmada\Communications\Features\Aggregate\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Contracts\CommunicationStatusRecalculator;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Events\CommunicationCompleted;
use AIArmada\Communications\Events\CommunicationFailed;
use AIArmada\Communications\Models\Communication;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

final readonly class RecalculateCommunicationStatusAction implements CommunicationStatusRecalculator
{
    public function handle(string $communicationId): void
    {
        $model = new Communication;

        $model->getConnection()->transaction(function () use ($communicationId, $model): void {
            $communication = $model->newQuery()->whereKey($communicationId)->lockForUpdate()->firstOrFail();

            if (Communication::ownerScopeConfig()->enabled) {
                OwnerWriteGuard::findOrFailForOwner(Communication::class, $communicationId);
            }

            if ($communication->cancelled_at !== null || in_array($communication->status, [CommunicationStatus::Cancelled, CommunicationStatus::Expired], true)) {
                return;
            }

            $statuses = $communication->deliveries()
                ->select('status')
                ->distinct()
                ->pluck('status')
                ->map(fn (mixed $status): string => $status instanceof BackedEnum ? $status->value : (string) $status)
                ->all();

            if ($statuses === []) {
                return;
            }

            $success = ['sent', 'received', 'delivered', 'opened', 'read', 'clicked', 'replied'];
            $failure = ['bounced', 'complained', 'failed', 'cancelled', 'expired', 'suppressed', 'unsubscribed'];
            $allSettled = array_diff($statuses, $success, $failure) === [];
            $anySuccess = array_intersect($statuses, $success) !== [];
            $anyFailure = array_intersect($statuses, $failure) !== [];

            $status = match (true) {
                ! $allSettled => CommunicationStatus::Processing,
                $statuses === ['cancelled'] => CommunicationStatus::Cancelled,
                $statuses === ['expired'] => CommunicationStatus::Expired,
                $anySuccess && $anyFailure => CommunicationStatus::PartiallyCompleted,
                $anySuccess => CommunicationStatus::Completed,
                default => CommunicationStatus::Failed,
            };

            $changed = $communication->status !== $status;
            $communication->status = $status;

            if ($status === CommunicationStatus::Completed) {
                $communication->completed_at ??= CarbonImmutable::now();
            } elseif ($status === CommunicationStatus::Failed) {
                $communication->failed_at ??= CarbonImmutable::now();
            } elseif ($status === CommunicationStatus::Cancelled) {
                $communication->cancelled_at ??= CarbonImmutable::now();
            }

            if ($communication->isDirty()) {
                $communication->save();
            }

            if ($changed && $status === CommunicationStatus::Completed) {
                $model->getConnection()->afterCommit(fn () => Event::dispatch(new CommunicationCompleted(communicationId: $communication->id)));
            } elseif ($changed && $status === CommunicationStatus::Failed) {
                $model->getConnection()->afterCommit(fn () => Event::dispatch(new CommunicationFailed(
                    communicationId: $communication->id,
                    failureMessage: 'All deliveries failed',
                )));
            }
        });
    }
}
