<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Catalog\Models\EventTemplate;
use AIArmada\Events\Features\Catalog\Models\EventTemplateItem;
use AIArmada\Events\Platform\EventWriteGuard;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SaveEventTemplateAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createFromEvent(Event $event, array $attributes = []): EventTemplate
    {
        return DB::transaction(function () use ($event, $attributes): EventTemplate {
            EventWriteGuard::findOrFail($event);

            $template = $this->createTemplate([
                ...$attributes,
                'owner_type' => $event->owner_type,
                'owner_id' => $event->owner_id,
                'templateable_type' => $event->getMorphClass(),
                'templateable_id' => $event->getKey(),
                'template_type' => 'event',
                'name' => $attributes['name'] ?? $event->title . ' Template',
                'description' => $attributes['description'] ?? $event->summary,
                'payload' => $this->eventPayload($event),
            ]);

            $event->loadMissing('occurrences.sessions');

            foreach ($event->occurrences as $occurrence) {
                $item = $this->createItem($template, [
                    'item_type' => 'occurrence',
                    'item_key' => (string) $occurrence->getKey(),
                    'payload' => $this->occurrencePayload($occurrence),
                    'sort_order' => 0,
                ]);

                foreach ($occurrence->sessions as $session) {
                    $this->createItem($template, [
                        'item_type' => 'session',
                        'item_key' => $session->slug,
                        'payload' => $this->sessionPayload($session),
                        'metadata' => ['parent_occurrence_item_key' => $item->item_key],
                        'sort_order' => $session->sort_order,
                    ]);
                }
            }

            return $template;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createFromOccurrence(EventOccurrence $occurrence, array $attributes = []): EventTemplate
    {
        return DB::transaction(function () use ($occurrence, $attributes): EventTemplate {
            $event = EventWriteGuard::findOrFail($occurrence->event_id);

            if (! $event instanceof Event) {
                throw new InvalidArgumentException('The configured events model must extend the Catalog Event model.');
            }

            $template = $this->createTemplate([
                ...$attributes,
                'owner_type' => $event->owner_type,
                'owner_id' => $event->owner_id,
                'templateable_type' => $occurrence->getMorphClass(),
                'templateable_id' => $occurrence->getKey(),
                'template_type' => 'occurrence',
                'name' => $attributes['name'] ?? $occurrence->title . ' Template',
                'description' => $attributes['description'] ?? null,
                'payload' => $this->occurrencePayload($occurrence),
            ]);

            $occurrence->loadMissing('sessions');

            foreach ($occurrence->sessions as $session) {
                $this->createItem($template, [
                    'item_type' => 'session',
                    'item_key' => $session->slug,
                    'payload' => $this->sessionPayload($session),
                    'sort_order' => $session->sort_order,
                ]);
            }

            return $template;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createFromSession(EventSession $session, array $attributes = []): EventTemplate
    {
        $event = EventWriteGuard::findOrFail($session->event_id);

        if (! $event instanceof Event) {
            throw new InvalidArgumentException('The configured events model must extend the Catalog Event model.');
        }

        return $this->createTemplate([
            ...$attributes,
            'owner_type' => $event->owner_type,
            'owner_id' => $event->owner_id,
            'templateable_type' => $session->getMorphClass(),
            'templateable_id' => $session->getKey(),
            'template_type' => 'session',
            'name' => $attributes['name'] ?? $session->title . ' Template',
            'description' => $attributes['description'] ?? $session->summary,
            'payload' => $this->sessionPayload($session),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createTemplate(array $data): EventTemplate
    {
        $data['status'] ??= 'draft';
        $data['visibility'] ??= 'private';

        $template = new EventTemplate;
        $template->forceFill(Arr::only($data, [
            'owner_type', 'owner_id',
            'templateable_type', 'templateable_id',
            'code', 'name', 'description',
            'template_type', 'status', 'visibility',
            'payload', 'default_scope',
            'metadata',
        ]))->save();

        return $template;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createItem(EventTemplate $template, array $data): EventTemplateItem
    {
        $data['event_template_id'] = $template->id;
        $data['status'] ??= 'active';

        return EventTemplateItem::query()->create($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(Event $event): array
    {
        return [
            'title' => $event->title,
            'summary' => $event->summary,
            'description' => $event->description,
            'type' => $event->type,
            'visibility' => $event->visibility,
            'delivery_mode' => $event->delivery_mode,
            'timezone' => $event->timezone,
            'pricing_mode' => $event->pricing_mode,
            'registration_mode' => $event->registration_mode,
            'issue_passes_for_free' => $event->issue_passes_for_free,
            'metadata' => $event->metadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function occurrencePayload(EventOccurrence $occurrence): array
    {
        return [
            'title' => $occurrence->title,
            'starts_at' => $occurrence->starts_at?->toIso8601String(),
            'ends_at' => $occurrence->ends_at?->toIso8601String(),
            'timezone' => $occurrence->timezone,
            'visibility' => $occurrence->visibility,
            'delivery_mode' => $occurrence->delivery_mode,
            'capacity' => $occurrence->capacity,
            'pricing_mode' => $occurrence->pricing_mode,
            'registration_mode' => $occurrence->registration_mode,
            'issue_passes_for_free' => $occurrence->issue_passes_for_free,
            'metadata' => $occurrence->metadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionPayload(EventSession $session): array
    {
        return [
            'title' => $session->title,
            'sort_order' => $session->sort_order,
            'summary' => $session->summary,
            'description' => $session->description,
            'starts_at' => $session->starts_at?->toIso8601String(),
            'ends_at' => $session->ends_at?->toIso8601String(),
            'timezone' => $session->timezone,
            'visibility' => $session->visibility,
            'delivery_mode' => $session->delivery_mode,
            'capacity' => $session->capacity,
            'pricing_mode' => $session->pricing_mode,
            'registration_mode' => $session->registration_mode,
            'issue_passes_for_free' => $session->issue_passes_for_free,
            'metadata' => $session->metadata,
        ];
    }
}
