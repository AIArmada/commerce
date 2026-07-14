<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\EventAccessPolicy;
use AIArmada\Events\Models\EventAudience;
use AIArmada\Events\Models\EventAudienceProfile;
use AIArmada\Events\Models\EventClassification;
use AIArmada\Events\Models\EventEligibilityRule;
use AIArmada\Events\Models\EventFacility;
use AIArmada\Events\Models\EventInvolvement;
use AIArmada\Events\Models\EventLanguage;
use AIArmada\Events\Models\EventLink;
use AIArmada\Events\Models\EventLocation;
use AIArmada\Events\Models\EventMaterial;
use AIArmada\Events\Models\EventMedia;
use AIArmada\Events\Models\EventReference;
use AIArmada\Events\Models\EventTimeExpression;
use Illuminate\Support\Collection;

final class CloneEventContentsAction
{
    private const MODEL_MAP = [
        'locations' => EventLocation::class,
        'facilities' => EventFacility::class,
        'involvements' => EventInvolvement::class,
        'materials' => EventMaterial::class,
        'links' => EventLink::class,
        'mediaRecords' => EventMedia::class,
        'languages' => EventLanguage::class,
        'audiences' => EventAudience::class,
        'audienceProfiles' => EventAudienceProfile::class,
        'eligibilityRules' => EventEligibilityRule::class,
        'classifications' => EventClassification::class,
        'timeExpressions' => EventTimeExpression::class,
        'accessPolicies' => EventAccessPolicy::class,
        'referenceRecords' => EventReference::class,
    ];

    public const BLUEPRINT_RELATIONS = [
        'locations', 'facilities', 'involvements', 'materials',
        'links', 'mediaRecords', 'languages',
        'audiences', 'audienceProfiles', 'eligibilityRules',
        'classifications', 'timeExpressions', 'accessPolicies',
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
        $cloned = new Collection;

        foreach ($relations as $relation) {
            $modelClass = self::MODEL_MAP[$relation] ?? null;

            if ($modelClass === null) {
                continue;
            }

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

            $children = $query->get();

            foreach ($children as $child) {
                $replica = $child->replicate(['id', 'created_at', 'updated_at']);
                $replica->event_id = $targetEventId;

                if ($targetOccurrenceId !== null) {
                    $replica->event_occurrence_id = $targetOccurrenceId;
                }

                if ($targetSessionId !== null) {
                    $replica->event_session_id = $targetSessionId;
                }

                $replica->save();

                $cloned->push($replica);
            }
        }

        return $cloned;
    }
}
