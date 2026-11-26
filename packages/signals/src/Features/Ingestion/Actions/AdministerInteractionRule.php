<?php

declare(strict_types=1);

namespace AIArmada\Signals\Features\Ingestion\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Signals\Features\Ingestion\Api\InteractionRuleAdministration;
use AIArmada\Signals\Features\Ingestion\Models\SignalInteractionRule;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use AIArmada\Signals\Support\DuplicateKeyViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

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

    public function createScanned(string $propertyId, string $triggerType, string $eventName, ?string $eventCategory, bool $isActive, array $candidates): int
    {
        if (count($candidates) > 500 || ! in_array($triggerType, ['click', 'accordion', 'media', 'youtube'], true) || trim($eventName) === '') {
            throw new InvalidArgumentException('Invalid interaction rule scan.');
        }

        return (new SignalInteractionRule)->getConnection()->transaction(function () use ($propertyId, $triggerType, $eventName, $eventCategory, $isActive, $candidates): int {
            $query = SignalInteractionRule::query();
            $properties = TrackedProperty::query();

            if (SignalInteractionRule::ownerScopeConfig()->enabled) {
                $owner = OwnerContext::resolveConfigured(SignalInteractionRule::ownerScopeConfig());
                OwnerContext::assertResolvedOrExplicitGlobal($owner, 'Signals administration requires an owner context.');
                $query = $owner === null ? $query->globalOnly() : $query->forOwner($owner, includeGlobal: false);
                $properties = $owner === null ? $properties->globalOnly() : $properties->forOwner($owner, includeGlobal: false);
            }

            $properties->lockForUpdate()->findOrFail($propertyId);
            $sortOrder = (int) (clone $query)->max('sort_order');
            $created = 0;

            foreach ($candidates as $candidate) {
                $name = trim((string) ($candidate['name'] ?? ''));

                if ($name === '') {
                    throw new InvalidArgumentException('A scanned interaction rule requires a name.');
                }

                $base = mb_substr(Str::slug($name) ?: 'interaction-rule', 0, 220);
                $settings = is_array($candidate['settings'] ?? null) ? $candidate['settings'] : [];
                $settings['scanner_confidence'] = $candidate['confidence'] ?? null;
                $settings['scanner_confidence_note'] = $candidate['confidence_note'] ?? null;
                $attributes = [
                    'tracked_property_id' => $propertyId,
                    'name' => mb_substr($name, 0, 255),
                    'trigger_type' => $triggerType,
                    'event_name' => $eventName,
                    'event_category' => $eventCategory,
                    'selector' => is_string($candidate['selector'] ?? null) ? $candidate['selector'] : null,
                    'page_pattern' => is_string($candidate['page_pattern'] ?? null) ? $candidate['page_pattern'] : null,
                    'settings' => $settings,
                    'sort_order' => ++$sortOrder,
                    'is_active' => $isActive,
                ];

                // The nested create transaction provides a savepoint on PostgreSQL.
                for ($suffix = 1; ; $suffix++) {
                    $slug = $suffix === 1 ? $base : $base . '-' . $suffix;

                    if ((clone $query)->where('slug', $slug)->exists()) {
                        continue;
                    }

                    try {
                        $this->create([...$attributes, 'slug' => $slug]);
                        $created++;

                        break;
                    } catch (QueryException $exception) {
                        if (! DuplicateKeyViolation::is($exception) || ! (clone $query)->where('slug', $slug)->exists()) {
                            throw $exception;
                        }
                    }
                }
            }

            return $created;
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
