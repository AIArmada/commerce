<?php

declare(strict_types=1);

namespace AIArmada\Communications\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Communications\Enums\CommunicationStatus;
use AIArmada\Communications\Events\CommunicationCompleted;
use AIArmada\Communications\Events\CommunicationFailed;
use AIArmada\Communications\Models\Communication;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

final class RecalculateCommunicationStatusAction
{
    public function handle(string $communicationId): Communication
    {
        $communication = Communication::query()->findOrFail($communicationId);

        if (Communication::ownerScopeConfig()->enabled) {
            OwnerWriteGuard::findOrFailForOwner(Communication::class, $communicationId);
        }

        $statuses = $communication->deliveries()
            ->pluck('status')
            ->map(fn (mixed $status): string => $status instanceof BackedEnum ? $status->value : (string) $status);

        if ($statuses->isEmpty()) {
            $communication->status = CommunicationStatus::Draft;
            $communication->save();

            return $communication;
        }

        $terminalStatuses = ['delivered', 'read', 'clicked', 'replied', 'bounced', 'complained', 'failed', 'cancelled', 'expired', 'suppressed'];
        $successStatuses = ['delivered', 'opened', 'read', 'clicked', 'replied'];

        $allTerminal = $statuses->every(fn (string $status): bool => in_array($status, $terminalStatuses, true));
        $anySuccess = $statuses->contains(fn (string $status): bool => in_array($status, $successStatuses, true));
        $anyFailed = $statuses->contains(fn (string $status): bool => $status === 'failed');
        $anyCancelled = $statuses->contains(fn (string $status): bool => $status === 'cancelled');

        $communication->status = match (true) {
            $allTerminal && ! $anyFailed && ! $anyCancelled => CommunicationStatus::Completed,
            $allTerminal && $anyFailed && ! $anySuccess => CommunicationStatus::Failed,
            $allTerminal && $anyFailed => CommunicationStatus::PartiallyCompleted,
            $allTerminal && $anyCancelled && ! $anySuccess && ! $anyFailed => CommunicationStatus::Cancelled,
            default => CommunicationStatus::Processing,
        };

        if ($communication->status === CommunicationStatus::Completed && $communication->completed_at === null) {
            $communication->completed_at = CarbonImmutable::now();
        } elseif ($communication->status === CommunicationStatus::Failed && $communication->failed_at === null) {
            $communication->failed_at = CarbonImmutable::now();
        } elseif ($communication->status === CommunicationStatus::Cancelled && $communication->cancelled_at === null) {
            $communication->cancelled_at = CarbonImmutable::now();
        }

        $communication->save();

        if ($communication->status === CommunicationStatus::Completed) {
            Event::dispatch(new CommunicationCompleted(
                communicationId: $communication->id,
            ));
        } elseif ($communication->status === CommunicationStatus::Failed) {
            Event::dispatch(new CommunicationFailed(
                communicationId: $communication->id,
                failureMessage: 'All deliveries failed',
            ));
        }

        return $communication;
    }
}
