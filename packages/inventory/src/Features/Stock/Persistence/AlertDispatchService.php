<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Stock\Persistence;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Enums\AlertStatus;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Support\InventoryOwnerScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class AlertDispatchService
{
    /**
     * @var array<string, class-string>
     */
    private array $notificationClasses = [];

    /**
     * @var array<string, object>
     */
    private array $notifiables = [];

    /**
     * Register a notification class for a specific alert status.
     *
     * @param  class-string  $notificationClass
     */
    public function registerNotification(AlertStatus $status, string $notificationClass): self
    {
        $this->notificationClasses[$status->value] = $notificationClass;

        return $this;
    }

    /**
     * Register notifiables (users/channels) for alerts.
     */
    public function registerNotifiable(string $key, object $notifiable): self
    {
        $this->notifiables[$key] = $notifiable;

        return $this;
    }

    /**
     * Dispatch alert for an inventory level.
     */
    public function dispatchAlert(InventoryLevel $level, AlertStatus $status): void
    {
        if (! config('inventory.events.low_inventory', true)) {
            return;
        }

        $notificationClass = $this->notificationClasses[$status->value] ?? null;

        if ($notificationClass === null || empty($this->notifiables)) {
            return;
        }

        $notification = new $notificationClass($level, $status);

        foreach ($this->notifiables as $notifiable) {
            Notification::send($notifiable, $notification);
        }
    }

    /**
     * Dispatch alerts for multiple levels.
     *
     * @param  iterable<InventoryLevel>  $levels
     */
    public function dispatchBulkAlerts(iterable $levels): int
    {
        $count = 0;

        foreach ($levels as $level) {
            $status = $level->alert_status !== null
                ? AlertStatus::from($level->alert_status)
                : AlertStatus::None;

            if ($status !== AlertStatus::None) {
                $this->dispatchAlert($level, $status);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get summary of alerts by status.
     *
     * @return array<string, int>
     */
    public function getAlertSummary(): array
    {
        $summary = [];
        $statuses = array_map(static fn (AlertStatus $status): string => $status->value, AlertStatus::cases());

        $counts = InventoryOwnerScope::applyToLocationQuery(InventoryLevel::query())
            ->whereIn('alert_status', $statuses)
            ->selectRaw('alert_status, COUNT(*) as aggregate')
            ->groupBy('alert_status')
            ->pluck('aggregate', 'alert_status');

        foreach ($statuses as $status) {
            $count = (int) ($counts[$status] ?? 0);

            if ($count > 0) {
                $summary[$status] = $count;
            }
        }

        return $summary;
    }

    /**
     * Get all critical alerts.
     *
     * @return Collection<int, InventoryLevel>
     */
    public function getCriticalAlerts(): Collection
    {
        $query = InventoryOwnerScope::applyToLocationQuery(
            InventoryLevel::query()
                ->whereIn('alert_status', array_map(
                    fn (AlertStatus $s): string => $s->value,
                    AlertStatus::criticalStatuses()
                ))
                ->with(['location', 'inventoryable'])
                ->orderByDesc('last_alert_at')
        );

        return $query->get();
    }

    /**
     * Acknowledge an alert (mark as reviewed without clearing).
     */
    public function acknowledgeAlert(InventoryLevel $level, ?string $note = null): void
    {
        DB::transaction(function () use ($level, $note): void {
            $record = $this->lockOwnedLevel($level);
            $metadata = $record->metadata ?? [];
            $metadata['last_acknowledged_at'] = CarbonImmutable::now()->toIso8601String();
            $metadata['acknowledged_note'] = $note;
            $record->update(['metadata' => $metadata]);
            $level->refresh();
        });
    }

    /**
     * Clear all alerts for a level.
     */
    public function clearAlert(InventoryLevel $level): void
    {
        DB::transaction(function () use ($level): void {
            $this->lockOwnedLevel($level)->update([
                'alert_status' => AlertStatus::None->value,
                'last_alert_at' => null,
            ]);
            $level->refresh();
        });
    }

    private function lockOwnedLevel(InventoryLevel $level): InventoryLevel
    {
        $query = InventoryLevel::query();

        if (InventoryOwnerScope::isEnabled()) {
            $owner = InventoryOwnerScope::resolveOwner();
            OwnerContext::assertResolvedOrExplicitGlobal($owner);
            $query->forOwner($owner, false);
        }

        return $query->whereKey($level->getKey())->lockForUpdate()->firstOrFail();
    }
}
