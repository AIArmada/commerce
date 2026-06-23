<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAttribute;
use AIArmada\Events\Models\EventAudience;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventTimeExpression;
use Illuminate\Database\Eloquent\Builder;

final class EventMetadataSyncService
{
    public function sync(Event | EventOccurrence | EventSession $target, string $source): void
    {
        match ($source) {
            'attribute' => $this->syncAttribute($target),
            'audience' => $this->syncAudience($target),
            'time_expression' => $this->syncTimeExpression($target),
            default => null,
        };
    }

    public function syncAttribute(Event | EventOccurrence | EventSession $target): void
    {
        $whitelist = config('events.attribute_sync.attribute_keys');

        $attributes = $this->attributeQuery($target)
            ->when($whitelist !== null, fn ($q) => $q->whereIn('attribute_key', $whitelist))
            ->get(['attribute_key', 'attribute_value', 'attribute_value_json']);

        $metadata = $target->metadata ?? [];
        $keysBefore = array_keys($metadata);

        foreach ($attributes as $attr) {
            $metadata[$attr->attribute_key] = $attr->attribute_value_json ?? $attr->attribute_value;
        }

        $syncedKeys = $attributes->pluck('attribute_key')->all();

        if (config('events.attribute_sync.always_rebuild', true)) {
            $stale = array_diff($keysBefore, $syncedKeys);
            foreach ($stale as $key) {
                if (! str_starts_with($key, '_')) {
                    unset($metadata[$key]);
                }
            }
        }

        $target->metadata = $metadata;
        $target->saveQuietly();
    }

    public function syncAudience(Event | EventOccurrence | EventSession $target): void
    {
        $whitelist = config('events.attribute_sync.audience_types');

        $audiences = $this->audienceQuery($target)
            ->when($whitelist !== null, fn ($q) => $q->whereIn('audience_type', $whitelist))
            ->get(['audience_type', 'value']);

        $metadata = $target->metadata ?? [];
        $grouped = [];

        foreach ($audiences as $audience) {
            $grouped[$audience->audience_type][] = $audience->value;
        }

        if ($grouped === []) {
            unset($metadata['_audiences']);
        } else {
            $metadata['_audiences'] = $grouped;
        }

        $target->metadata = $metadata;
        $target->saveQuietly();
    }

    public function syncTimeExpression(Event | EventOccurrence | EventSession $target): void
    {
        $expressions = $this->timeExpressionQuery($target)
            ->get(['anchor_type', 'anchor_code', 'relation', 'offset_minutes', 'display_label', 'resolver_class', 'resolver_context']);

        $metadata = $target->metadata ?? [];

        if ($expressions->isEmpty()) {
            unset($metadata['_time_expressions']);
        } else {
            $metadata['_time_expressions'] = $expressions->map(fn (EventTimeExpression $e) => array_filter([
                'anchor_type' => $e->anchor_type,
                'anchor_code' => $e->anchor_code,
                'relation' => $e->relation,
                'offset_minutes' => $e->offset_minutes,
                'display_label' => $e->display_label,
                'resolver_class' => $e->resolver_class,
                'resolver_context' => $e->resolver_context,
            ], fn ($v) => $v !== null))->values()->all();
        }

        $target->metadata = $metadata;
        $target->saveQuietly();
    }

    public function rebuild(Event | EventOccurrence | EventSession $target): void
    {
        $this->syncAttribute($target);
        $this->syncAudience($target);
        $this->syncTimeExpression($target);
    }

    /**
     * @return Builder<EventAttribute>
     */
    private function attributeQuery(Event | EventOccurrence | EventSession $target)
    {
        return match (true) {
            $target instanceof Event => EventAttribute::where('event_id', $target->id),
            $target instanceof EventOccurrence => EventAttribute::where('event_occurrence_id', $target->id),
            $target instanceof EventSession => EventAttribute::where('event_session_id', $target->id),
        };
    }

    /**
     * @return Builder<EventAudience>
     */
    private function audienceQuery(Event | EventOccurrence | EventSession $target)
    {
        return match (true) {
            $target instanceof Event => EventAudience::where('event_id', $target->id),
            $target instanceof EventOccurrence => EventAudience::where('event_occurrence_id', $target->id),
            $target instanceof EventSession => EventAudience::where('event_session_id', $target->id),
        };
    }

    /**
     * @return Builder<EventTimeExpression>
     */
    private function timeExpressionQuery(Event | EventOccurrence | EventSession $target)
    {
        return match (true) {
            $target instanceof Event => EventTimeExpression::where('event_id', $target->id),
            $target instanceof EventOccurrence => EventTimeExpression::where('event_occurrence_id', $target->id),
            $target instanceof EventSession => EventTimeExpression::where('event_session_id', $target->id),
        };
    }
}
