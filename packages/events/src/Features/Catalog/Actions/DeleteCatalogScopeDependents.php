<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Contracts\EventScopeCascadeDeleter;
use AIArmada\Events\Features\Catalog\Models\EventAccessPolicy;
use AIArmada\Events\Features\Catalog\Models\EventAttribute;
use AIArmada\Events\Features\Catalog\Models\EventAudience;
use AIArmada\Events\Features\Catalog\Models\EventAudienceProfile;
use AIArmada\Events\Features\Catalog\Models\EventAvailabilityBlock;
use AIArmada\Events\Features\Catalog\Models\EventEligibilityRule;
use AIArmada\Events\Features\Catalog\Models\EventInvolvement;
use AIArmada\Events\Features\Catalog\Models\EventItinerary;
use AIArmada\Events\Features\Catalog\Models\EventItineraryItem;
use AIArmada\Events\Features\Catalog\Models\EventLanguage;
use AIArmada\Events\Features\Catalog\Models\EventLink;
use AIArmada\Events\Features\Catalog\Models\EventMaterial;
use AIArmada\Events\Features\Catalog\Models\EventMedia;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventRecurrenceRule;
use AIArmada\Events\Features\Catalog\Models\EventReference;
use AIArmada\Events\Features\Catalog\Models\EventRevision;
use AIArmada\Events\Features\Catalog\Models\EventSeriesItem;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Catalog\Models\EventTimeExpression;
use AIArmada\Events\Platform\ExtensionRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DeleteCatalogScopeDependents
{
    /**
     * Catalog child tables keyed by their scope columns.
     *
     * Rows are deleted through their models so owner guards and deeper
     * deleting hooks still run.
     *
     * @var array<class-string<Model>, list<string>>
     */
    private const array SCOPED_CHILDREN = [
        EventAccessPolicy::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventAttribute::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventAudience::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventAudienceProfile::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventAvailabilityBlock::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventEligibilityRule::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventInvolvement::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventItinerary::class => ['event_id', 'event_occurrence_id'],
        EventItineraryItem::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventLanguage::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventLink::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventMaterial::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventMedia::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventRecurrenceRule::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventReference::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventRevision::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventSeriesItem::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventTimeExpression::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
    ];

    public function __construct(
        private readonly ExtensionRegistry $extensions,
    ) {}

    public function deleteForScope(string $scopeType, string $scopeId, ?string $morphClass = null): void
    {
        $column = match ($scopeType) {
            'event' => 'event_id',
            'occurrence' => 'event_occurrence_id',
            'session' => 'event_session_id',
            default => throw new InvalidArgumentException(sprintf('Unknown event scope type [%s].', $scopeType)),
        };

        DB::transaction(function () use ($scopeType, $scopeId, $column, $morphClass): void {
            if ($scopeType === 'event') {
                $this->deleteThroughModels(EventOccurrence::class, 'event_id', $scopeId);
                $this->deleteThroughModels(EventSession::class, 'event_id', $scopeId);
            }

            if ($scopeType === 'occurrence') {
                $this->deleteThroughModels(EventSession::class, 'event_occurrence_id', $scopeId);
            }

            foreach (self::SCOPED_CHILDREN as $modelClass => $columns) {
                if (in_array($column, $columns, true)) {
                    $this->deleteThroughModels($modelClass, $column, $scopeId);
                }
            }

            $this->deleteSeriesableRows($scopeId, $morphClass, $scopeType);

            foreach ($this->extensions->resolve('events.cascade-delete', EventScopeCascadeDeleter::class) as $deleter) {
                $deleter->deleteForScope($scopeType, $scopeId, $morphClass);
            }
        });
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function deleteThroughModels(string $modelClass, string $column, string $scopeId): void
    {
        $modelClass::query()
            ->where($column, $scopeId)
            ->chunkById(200, function (iterable $children): void {
                foreach ($children as $child) {
                    $child->delete();
                }
            });
    }

    private function deleteSeriesableRows(string $scopeId, ?string $morphClass, string $scopeType): void
    {
        if ($scopeType !== 'event' || $morphClass === null) {
            return;
        }

        EventSeriesItem::query()
            ->where('seriesable_type', $morphClass)
            ->where('seriesable_id', $scopeId)
            ->chunkById(200, function (iterable $children): void {
                foreach ($children as $child) {
                    $child->delete();
                }
            });
    }
}
