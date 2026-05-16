<?php

declare(strict_types=1);

namespace AIArmada\Growth\Actions;

use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class ProjectExperimentContextIntoSignalProperties
{
    public function __construct(
        private readonly BuildExperimentSignalProperties $buildExperimentSignalProperties,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public function handle(Model $source, TrackedProperty $trackedProperty, array $properties = []): array
    {
        if (! config('growth.integrations.signals.enabled', true)) {
            return $properties;
        }

        $assignments = $this->resolveAssignments($source, $trackedProperty);

        if ($assignments->isEmpty()) {
            return $properties;
        }

        $contexts = $this->buildExperimentSignalProperties->contextsForAssignments($assignments);

        if ($contexts === []) {
            return $properties;
        }

        /** @var array<string, string> $primaryContext */
        $primaryContext = $contexts[0];

        return array_filter(
            array_merge($properties, $primaryContext, [
                'experiment_contexts' => $contexts,
            ]),
            static fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * @return Collection<int, Assignment>
     */
    private function resolveAssignments(Model $source, TrackedProperty $trackedProperty): Collection
    {
        $identityIds = $this->resolveIdentityIds($source, $trackedProperty);
        $subjectKeys = $this->candidateSubjectKeys($source);

        if ($identityIds === [] && $subjectKeys === []) {
            return new Collection;
        }

        return Assignment::query()
            ->withoutOwnerScope()
            ->with(['experiment', 'variant'])
            ->whereIn(
                'experiment_id',
                Experiment::query()
                    ->withoutOwnerScope()
                    ->where('tracked_property_id', $trackedProperty->getKey())
                    ->select('id'),
            )
            ->where(function (Builder $query) use ($identityIds, $subjectKeys): void {
                if ($identityIds !== []) {
                    $query->whereIn('signal_identity_id', $identityIds);
                }

                if ($subjectKeys === []) {
                    return;
                }

                if ($identityIds !== []) {
                    $query->orWhereIn('subject_key', $subjectKeys);

                    return;
                }

                $query->whereIn('subject_key', $subjectKeys);
            })
            ->orderByDesc('last_seen_at')
            ->orderByDesc('assigned_at')
            ->get()
            ->unique('experiment_id')
            ->values();
    }

    /**
     * @return list<string>
     */
    private function resolveIdentityIds(Model $source, TrackedProperty $trackedProperty): array
    {
        $externalIds = $this->candidateExternalIds($source);
        $anonymousIds = $this->candidateAnonymousIds($source);

        if ($externalIds === [] && $anonymousIds === []) {
            return [];
        }

        return SignalIdentity::query()
            ->withoutOwnerScope()
            ->where('tracked_property_id', $trackedProperty->getKey())
            ->where(function (Builder $query) use ($anonymousIds, $externalIds): void {
                if ($externalIds !== []) {
                    $query->whereIn('external_id', $externalIds);
                }

                if ($anonymousIds === []) {
                    return;
                }

                if ($externalIds !== []) {
                    $query->orWhereIn('anonymous_id', $anonymousIds);

                    return;
                }

                $query->whereIn('anonymous_id', $anonymousIds);
            })
            ->pluck('id')
            ->filter(static fn (mixed $value): bool => is_scalar($value))
            ->map(static fn (mixed $value): string => (string) $value)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function candidateExternalIds(Model $source): array
    {
        return $this->uniqueStrings([
            $this->stringValue($source->getAttribute('customer_id')),
        ]);
    }

    /**
     * @return list<string>
     */
    private function candidateAnonymousIds(Model $source): array
    {
        $metadata = $source->getAttribute('metadata');

        return $this->uniqueStrings([
            $this->stringValue($source->getAttribute('cart_id')),
            is_array($metadata) ? $this->stringValue(data_get($metadata, 'cart_id')) : null,
        ]);
    }

    /**
     * @return list<string>
     */
    private function candidateSubjectKeys(Model $source): array
    {
        return array_map(
            static fn (string $anonymousId): string => 'anonymous:' . $anonymousId,
            $this->candidateAnonymousIds($source),
        );
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value) || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param  list<string|null>  $values
     * @return list<string>
     */
    private function uniqueStrings(array $values): array
    {
        return array_values(array_unique(array_filter($values, static fn (?string $value): bool => $value !== null && $value !== '')));
    }
}