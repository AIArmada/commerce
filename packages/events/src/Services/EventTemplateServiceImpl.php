<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Actions\CreateEventOccurrenceAction;
use AIArmada\Events\Actions\CreateEventSessionAction;
use AIArmada\Events\Contracts\EventTemplateService;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventTemplate;
use AIArmada\Events\Models\EventTemplateItem;
use AIArmada\Events\Support\EventWriteGuard;
use AIArmada\Events\Support\Normalization\EventContentNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EventTemplateServiceImpl implements EventTemplateService
{
    public function __construct(
        private readonly EventContentNormalizer $contentNormalizer,
        private readonly CreateEventOccurrenceAction $createOccurrence,
        private readonly CreateEventSessionAction $createSession,
    ) {}

    public function createFromTemplate(EventTemplate $template, array $overrides = []): mixed
    {
        return match ($template->template_type) {
            'event' => $this->createEventFromTemplate($template, $overrides),
            'occurrence' => $this->createOccurrenceFromTemplate($template, $overrides),
            'session' => $this->createSessionFromTemplate($template, $overrides),
            default => throw new InvalidArgumentException("Unknown template type: {$template->template_type}"),
        };
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createEventFromTemplate(EventTemplate $template, array $overrides = []): Event
    {
        return DB::transaction(function () use ($template, $overrides): Event {
            $payload = array_merge($template->payload ?? [], $overrides);

            $event = Event::query()->create([
                'owner_type' => $template->owner_type,
                'owner_id' => $template->owner_id,
                'title' => $this->contentNormalizer->normalizeTitle((string) ($payload['title'] ?? $template->name)),
                'slug' => $payload['slug'] ?? Str::slug($template->name) . '-' . Str::random(6),
                'summary' => $this->contentNormalizer->normalizeSummary($payload['summary'] ?? $template->description),
                'description' => $this->contentNormalizer->normalizeDescription($payload['description'] ?? null),
                'type' => $payload['type'] ?? 'event',
                'status' => 'draft',
                'visibility' => $payload['visibility'] ?? 'public',
                'delivery_mode' => $payload['delivery_mode'] ?? 'physical',
                'timezone' => $payload['timezone'] ?? config('events.defaults.timezone', 'UTC'),
                'pricing_mode' => $payload['pricing_mode'] ?? null,
                'registration_mode' => $payload['registration_mode'] ?? null,
                'issue_passes_for_free' => $payload['issue_passes_for_free'] ?? false,
                'metadata' => $payload['metadata'] ?? null,
            ]);

            foreach ($template->items as $item) {
                $this->materializeItem($item, $event);
            }

            return $event;
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createOccurrenceFromTemplate(EventTemplate $template, array $overrides = []): EventOccurrence
    {
        $eventId = $overrides['event_id'] ?? $template->default_scope['event_id'] ?? null;

        if ($eventId === null) {
            throw new InvalidArgumentException('Cannot create occurrence from template without event_id in overrides or default_scope.');
        }

        $event = EventWriteGuard::findOrFail($eventId);

        return DB::transaction(function () use ($template, $overrides, $event): EventOccurrence {
            $payload = array_merge($template->payload ?? [], $overrides);

            $occurrence = $this->createOccurrence->handle($event, [
                'title' => $payload['title'] ?? $template->name,
                'slug' => $payload['slug'] ?? null,
                'starts_at' => $payload['starts_at'] ?? now()->addDay(),
                'ends_at' => $payload['ends_at'] ?? null,
                'timezone' => $payload['timezone'] ?? null,
                'visibility' => $payload['visibility'] ?? null,
                'delivery_mode' => $payload['delivery_mode'] ?? null,
                'capacity' => $payload['capacity'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
            ]);

            foreach ($template->items as $item) {
                $this->materializeItem($item, $event, $occurrence);
            }

            return $occurrence;
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSessionFromTemplate(EventTemplate $template, array $overrides = []): EventSession
    {
        $occurrenceId = $overrides['event_occurrence_id'] ?? $template->default_scope['event_occurrence_id'] ?? null;

        if ($occurrenceId === null) {
            throw new InvalidArgumentException('Cannot create session from template without event_occurrence_id in overrides or default_scope.');
        }

        $occurrence = EventOccurrence::query()->findOrFail($occurrenceId);
        EventWriteGuard::findOrFail($occurrence->event_id);

        return DB::transaction(function () use ($template, $overrides, $occurrence): EventSession {
            $payload = array_merge($template->payload ?? [], $overrides);

            return $this->createSession->handle($occurrence, [
                'title' => $payload['title'] ?? $template->name,
                'slug' => $payload['slug'] ?? null,
                'summary' => $payload['summary'] ?? $template->description,
                'description' => $payload['description'] ?? null,
                'starts_at' => $payload['starts_at'] ?? now(),
                'ends_at' => $payload['ends_at'] ?? null,
                'timezone' => $payload['timezone'] ?? null,
                'visibility' => $payload['visibility'] ?? null,
                'delivery_mode' => $payload['delivery_mode'] ?? null,
                'capacity' => $payload['capacity'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
            ]);
        });
    }

    private function materializeItem(
        EventTemplateItem $item,
        Event $event,
        ?EventOccurrence $occurrence = null,
    ): void {
        switch ($item->item_type) {
            case 'occurrence':
                $payload = $item->payload ?? [];

                $this->createOccurrence->handle($event, [
                    'title' => $payload['title'] ?? "Occurrence {$item->item_key}",
                    'slug' => $payload['slug'] ?? null,
                    'starts_at' => $payload['starts_at'] ?? now()->addDay(),
                    'ends_at' => $payload['ends_at'] ?? null,
                    'timezone' => $payload['timezone'] ?? null,
                    'visibility' => $payload['visibility'] ?? null,
                    'delivery_mode' => $payload['delivery_mode'] ?? null,
                    'capacity' => $payload['capacity'] ?? null,
                    'metadata' => $payload['metadata'] ?? null,
                ]);

                break;

            case 'session':
                $payload = $item->payload ?? [];
                $targetOccurrence = $occurrence ?? $event->occurrences()->first();

                if ($targetOccurrence === null) {
                    break;
                }

                $this->createSession->handle($targetOccurrence, [
                    'title' => $payload['title'] ?? "Session {$item->item_key}",
                    'slug' => $payload['slug'] ?? null,
                    'summary' => $payload['summary'] ?? null,
                    'description' => $payload['description'] ?? null,
                    'starts_at' => $payload['starts_at'] ?? now(),
                    'ends_at' => $payload['ends_at'] ?? null,
                    'timezone' => $payload['timezone'] ?? null,
                    'visibility' => $payload['visibility'] ?? null,
                    'delivery_mode' => $payload['delivery_mode'] ?? null,
                    'capacity' => $payload['capacity'] ?? null,
                    'metadata' => $payload['metadata'] ?? null,
                ]);

                break;

            default:
                break;
        }
    }
}
