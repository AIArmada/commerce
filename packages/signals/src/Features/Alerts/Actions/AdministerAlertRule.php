<?php

declare(strict_types=1);

namespace AIArmada\Signals\Features\Alerts\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Signals\Features\Alerts\Api\AlertRuleAdministration;
use AIArmada\Signals\Features\Alerts\Models\SignalAlertRule;
use Illuminate\Support\Arr;

final readonly class AdministerAlertRule implements AlertRuleAdministration
{
    public function create(array $data): string
    {
        return (new SignalAlertRule)->getConnection()->transaction(function () use ($data): string {
            $record = new SignalAlertRule($this->attributes($data));

            if (SignalAlertRule::ownerScopeConfig()->enabled) {
                $owner = OwnerContext::resolveConfigured(SignalAlertRule::ownerScopeConfig());
                OwnerContext::assertResolvedOrExplicitGlobal($owner, 'Signals administration requires an owner context.');

                if ($owner !== null) {
                    $record->owner()->associate($owner);
                }
            }

            $record->save();

            return (string) $record->getKey();
        }, attempts: 3);
    }

    public function update(string $id, array $data): void
    {
        (new SignalAlertRule)->getConnection()->transaction(function () use ($id, $data): void {
            $this->lockRecord($id)->update($this->attributes($data));
        }, attempts: 3);
    }

    public function delete(string $id): bool
    {
        return (new SignalAlertRule)->getConnection()->transaction(
            fn (): bool => $this->lockRecord($id)->delete() === true,
            attempts: 3,
        );
    }

    private function lockRecord(string $id): SignalAlertRule
    {
        $query = SignalAlertRule::query();

        if (SignalAlertRule::ownerScopeConfig()->enabled) {
            $owner = OwnerContext::resolveConfigured(SignalAlertRule::ownerScopeConfig());
            OwnerContext::assertResolvedOrExplicitGlobal($owner, 'Signals administration requires an owner context.');

            $query = $owner === null
                ? $query->globalOnly()
                : $query->forOwner($owner, includeGlobal: false);
        }

        return $query->lockForUpdate()->findOrFail($id);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return Arr::only($data, [
            'tracked_property_id', 'name', 'slug', 'description',
            'metric_key', 'operator', 'threshold', 'event_filters',
            'channels', 'destination_keys', 'inline_destinations', 'timeframe_minutes',
            'cooldown_minutes', 'severity', 'priority', 'is_active',
        ]);
    }
}
