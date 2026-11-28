<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Features\Catalog\Models\EventAccessPolicy;
use AIArmada\Events\Features\Catalog\Models\EventAudience;
use AIArmada\Events\Features\Catalog\Models\EventAudienceProfile;
use AIArmada\Events\Features\Catalog\Models\EventEligibilityRule;
use AIArmada\Events\Features\Catalog\Models\EventInvolvement;
use AIArmada\Events\Features\Catalog\Models\EventLanguage;
use AIArmada\Events\Features\Catalog\Models\EventLink;
use AIArmada\Events\Features\Catalog\Models\EventMaterial;
use AIArmada\Events\Features\Catalog\Models\EventMedia;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventReference;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Catalog\Models\EventTimeExpression;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\MediaLibrary\HasMedia;

final class CloneEventContentsAction
{
    /**
     * @return array<string, class-string<Model>>
     */
    private static function modelMap(): array
    {
        return [
            'involvements' => EventInvolvement::class,
            'materials' => EventMaterial::class,
            'links' => EventLink::class,
            'mediaRecords' => EventMedia::class,
            'languages' => EventLanguage::class,
            'audiences' => EventAudience::class,
            'audienceProfiles' => EventAudienceProfile::class,
            'eligibilityRules' => EventEligibilityRule::class,
            'timeExpressions' => EventTimeExpression::class,
            'accessPolicies' => EventAccessPolicy::class,
            'referenceRecords' => EventReference::class,
        ];
    }

    public const BLUEPRINT_RELATIONS = [
        'involvements', 'materials',
        'links', 'mediaRecords', 'languages',
        'audiences', 'audienceProfiles', 'eligibilityRules',
        'timeExpressions', 'accessPolicies',
        'referenceRecords',
    ];

    /**
     * @param  array<int, string>  $relations
     */
    public function handle(
        string $sourceEventId,
        string $targetEventId,
        ?string $sourceOccurrenceId = null,
        ?string $targetOccurrenceId = null,
        ?string $sourceSessionId = null,
        ?string $targetSessionId = null,
        array $relations = [],
    ): Collection {
        $relations = $relations !== [] ? $relations : self::BLUEPRINT_RELATIONS;

        foreach ($relations as $relation) {
            if (! array_key_exists($relation, self::modelMap())) {
                throw new InvalidArgumentException(sprintf('Unknown cloneable event relation [%s].', $relation));
            }
        }

        EventWriteGuard::findOrFail($sourceEventId);
        EventWriteGuard::findOrFail($targetEventId);

        return DB::transaction(function () use (
            $relations,
            $sourceEventId,
            $sourceOccurrenceId,
            $sourceSessionId,
            $targetEventId,
            $targetOccurrenceId,
            $targetSessionId,
        ): Collection {
            if (($sourceOccurrenceId === null) !== ($targetOccurrenceId === null)
                || ($sourceSessionId === null) !== ($targetSessionId === null)) {
                throw new InvalidArgumentException('Source and target clone scopes must have the same type.');
            }

            if ($sourceEventId === $targetEventId && $sourceOccurrenceId === $targetOccurrenceId && $sourceSessionId === $targetSessionId) {
                throw new InvalidArgumentException('Cannot clone contents into the source scope.');
            }

            $this->validateScope($sourceEventId, $sourceOccurrenceId, $sourceSessionId);
            $this->validateScope($targetEventId, $targetOccurrenceId, $targetSessionId);

            $cloned = new Collection;

            foreach ($relations as $relation) {
                $modelClass = self::modelMap()[$relation];

                $query = $modelClass::query()->where('event_id', $sourceEventId);

                if ($sourceSessionId !== null) {
                    $query->where('event_session_id', $sourceSessionId);
                } elseif ($sourceOccurrenceId !== null) {
                    $query->where('event_occurrence_id', $sourceOccurrenceId)
                        ->whereNull('event_session_id');
                } else {
                    $query->whereNull('event_occurrence_id')
                        ->whereNull('event_session_id');
                }

                $query->chunkById(200, function ($children) use (
                    $cloned,
                    $targetEventId,
                    $targetOccurrenceId,
                    $targetSessionId,
                ): void {
                    foreach ($children as $child) {
                        $replica = $child->replicate(['id', 'created_at', 'updated_at']);
                        $replica->setAttribute('event_id', $targetEventId);

                        if ($targetOccurrenceId !== null) {
                            $replica->setAttribute('event_occurrence_id', $targetOccurrenceId);
                        }

                        if ($targetSessionId !== null) {
                            $replica->setAttribute('event_session_id', $targetSessionId);
                        }

                        $replica->save();

                        $cloned->push($replica);
                    }
                });
            }

            $this->cloneSpatieMedia(
                sourceEventId: $sourceEventId,
                targetEventId: $targetEventId,
                sourceOccurrenceId: $sourceOccurrenceId,
                targetOccurrenceId: $targetOccurrenceId,
                sourceSessionId: $sourceSessionId,
                targetSessionId: $targetSessionId,
            );

            return $cloned;
        });
    }

    private function validateScope(string $eventId, ?string $occurrenceId, ?string $sessionId): void
    {
        if ($occurrenceId !== null) {
            EventOccurrence::query()->where('event_id', $eventId)->findOrFail($occurrenceId);
        }

        if ($sessionId !== null) {
            EventSession::query()
                ->where('event_id', $eventId)
                ->where('event_occurrence_id', $occurrenceId)
                ->findOrFail($sessionId);
        }
    }

    private function cloneSpatieMedia(
        string $sourceEventId,
        string $targetEventId,
        ?string $sourceOccurrenceId,
        ?string $targetOccurrenceId,
        ?string $sourceSessionId,
        ?string $targetSessionId,
    ): void {
        if ($sourceSessionId !== null && $targetSessionId !== null) {
            $source = EventSession::query()
                ->where('event_id', $sourceEventId)
                ->findOrFail($sourceSessionId);
            $target = EventSession::query()
                ->where('event_id', $targetEventId)
                ->findOrFail($targetSessionId);
        } elseif ($sourceOccurrenceId !== null && $targetOccurrenceId !== null) {
            $source = EventOccurrence::query()
                ->where('event_id', $sourceEventId)
                ->findOrFail($sourceOccurrenceId);
            $target = EventOccurrence::query()
                ->where('event_id', $targetEventId)
                ->findOrFail($targetOccurrenceId);
        } else {
            $source = ModelResolver::eventClass()::query()->findOrFail($sourceEventId);
            $target = ModelResolver::eventClass()::query()->findOrFail($targetEventId);
        }

        if (! $source instanceof HasMedia || ! $target instanceof HasMedia) {
            return;
        }

        foreach ($source->media()->get() as $media) {
            $media->copy($target, $media->collection_name, $media->disk);
        }
    }
}
