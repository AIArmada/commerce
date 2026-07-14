<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventTemplate;
use AIArmada\Events\Models\EventTemplateItem;
use AIArmada\Events\Support\EventWriteGuard;

final class SaveEventTemplateAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createFromEvent(Event $event, array $attributes = []): EventTemplate
    {
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
                'item_key' => $occurrence->slug,
                'payload' => $this->occurrencePayload($occurrence),
                'sort_order' => 0,
            ]);

            foreach ($occurrence->sessions as $session) {
                $this->createItem($template, [
                    'item_type' => 'session',
                    'item_key' => $session->slug,
                    'payload' => $this->sessionPayload($session),
                    'sort_order' => $session->sort_order,
                ]);
            }
        }

        return $template;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createFromOccurrence(EventOccurrence $occurrence, array $attributes = []): EventTemplate
    {
        $event = EventWriteGuard::findOrFail($occurrence->event_id);

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
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createFromSession(EventSession $session, array $attributes = []): EventTemplate
    {
        $event = EventWriteGuard::findOrFail($session->event_id);

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

        return EventTemplate::query()->create($data);
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
            'summary' => $session->summary,
            'description' => $session->description,
            'starts_at' => $session->starts_at?->toIso8601String(),
            'ends_at' => $session->ends_at?->toIso8601String(),
            'timezone' => $session->timezone,
            'visibility' => $session->visibility,
            'delivery_mode' => $session->delivery_mode,
            'capacity' => $session->capacity,
            'metadata' => $session->metadata,
        ];
    }
}
