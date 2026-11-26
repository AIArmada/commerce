<?php

declare(strict_types=1);

namespace AIArmada\Signals\Features\Ingestion\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Signals\Features\Ingestion\Api\InteractionRuleAdministration;
use AIArmada\Signals\Features\Ingestion\Models\SignalInteractionRule;
use Illuminate\Support\Arr;

final readonly class AdministerInteractionRule implements InteractionRuleAdministration
{
    public function create(array $data): string
    {
        return (new SignalInteractionRule)->getConnection()->transaction(function () use ($data): string {
            $record = new SignalInteractionRule($this->attributes($data));

            if (SignalInteractionRule::ownerScopeConfig()->enabled) {
                $owner = OwnerContext::resolveConfigured(SignalInteractionRule::ownerScopeConfig());
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
        (new SignalInteractionRule)->getConnection()->transaction(function () use ($id, $data): void {
            $this->lockRecord($id)->update($this->attributes($data));
        }, attempts: 3);
    }

    public function delete(string $id): bool
    {
        return (new SignalInteractionRule)->getConnection()->transaction(
            fn (): bool => $this->lockRecord($id)->delete() === true,
            attempts: 3,
        );
    }

    private function lockRecord(string $id): SignalInteractionRule
    {
        $query = SignalInteractionRule::query();

        if (SignalInteractionRule::ownerScopeConfig()->enabled) {
            $owner = OwnerContext::resolveConfigured(SignalInteractionRule::ownerScopeConfig());
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
            'trigger_type', 'event_name', 'event_category', 'selector',
            'page_pattern', 'settings', 'sort_order', 'is_active',
        ]);
    }
}
