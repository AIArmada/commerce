<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Catalog\Actions;

use AIArmada\Events\Contracts\EventTemplateService;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Catalog\Models\EventTemplate;
use AIArmada\Events\Features\Catalog\Models\EventTemplateItem;
use AIArmada\Events\Platform\EventContentNormalizer;
use AIArmada\Events\Platform\EventWriteGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class DefaultEventTemplateService implements EventTemplateService
{
    private const array EVENT_OVERRIDES = [
        'title', 'slug', 'summary', 'description', 'type', 'visibility',
        'delivery_mode', 'timezone', 'pricing_mode', 'registration_mode',
        'issue_passes_for_free', 'metadata',
    ];

    private const array OCCURRENCE_OVERRIDES = [
        'title', 'slug', 'starts_at', 'ends_at', 'timezone', 'visibility',
        'delivery_mode', 'capacity', 'metadata',
    ];

    private const array SESSION_OVERRIDES = [
        'title', 'slug', 'summary', 'description', 'starts_at', 'ends_at',
        'timezone', 'visibility', 'delivery_mode', 'capacity', 'sort_order', 'metadata',
    ];

    private const array GUARDED_OVERRIDES = [
        'id', 'owner_type', 'owner_id', 'created_by_type', 'created_by_id',
        'status', 'published_at', 'archived_at', 'cancelled_at',
        'event_template_id', 'templateable_type', 'templateable_id',
    ];

    public function __construct(
        private readonly EventContentNormalizer $contentNormalizer,
        private readonly CreateEventOccurrenceAction $createOccurrence,
        private readonly CreateEventSessionAction $createSession,
    ) {}

    public function createFromTemplate(string $templateId, array $overrides = []): string
    {
        $template = $this->findTemplate($templateId);

        $created = match ($template->template_type) {
            'event' => $this->createEventFromTemplate($template, $overrides),
            'occurrence' => $this->createOccurrenceFromTemplate($template, $overrides),
            'session' => $this->createSessionFromTemplate($template, $overrides),
            default => throw new InvalidArgumentException("Unknown template type: {$template->template_type}"),
        };

        return (string) $created->getKey();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createEventFromTemplate(EventTemplate $template, array $overrides): Event
    {
        $payload = $this->payload($template, $overrides, self::EVENT_OVERRIDES);

        return DB::transaction(function () use ($template, $payload): Event {
            $event = new Event;
            $event->forceFill([
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
            ])->save();

            foreach ($template->items as $item) {
                $this->materializeItem($item, $event);
            }

            return $event;
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createOccurrenceFromTemplate(EventTemplate $template, array $overrides): EventOccurrence
    {
        $eventId = $overrides['event_id'] ?? $template->default_scope['event_id'] ?? null;

        if (! is_string($eventId) || $eventId === '') {
            throw new InvalidArgumentException('Cannot create occurrence from template without event_id in overrides or default_scope.');
        }

        $event = EventWriteGuard::findOrFail($eventId);

        if (! $event instanceof Event) {
            throw new InvalidArgumentException('The configured events model must extend the Catalog Event model.');
        }

        $payload = $this->payload($template, $overrides, self::OCCURRENCE_OVERRIDES);

        return DB::transaction(function () use ($template, $payload, $event): EventOccurrence {
            $occurrence = $this->createOccurrence->handle($event, [
                'title' => $payload['title'] ?? $template->name,
                'slug' => $payload['slug'] ?? null,
                'starts_at' => $payload['starts_at'] ?? CarbonImmutable::now()->addDay(),
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
    private function createSessionFromTemplate(EventTemplate $template, array $overrides): EventSession
    {
        $occurrenceId = $overrides['event_occurrence_id'] ?? $template->default_scope['event_occurrence_id'] ?? null;

        if (! is_string($occurrenceId) || $occurrenceId === '') {
            throw new InvalidArgumentException('Cannot create session from template without event_occurrence_id in overrides or default_scope.');
        }

        $occurrence = EventOccurrence::query()->whereKey($occurrenceId)->firstOrFail();
        EventWriteGuard::findOrFail($occurrence->event_id);

        $payload = $this->payload($template, $overrides, self::SESSION_OVERRIDES);

        return DB::transaction(fn (): EventSession => $this->createSession->handle($occurrence, [
            'title' => $payload['title'] ?? $template->name,
            'slug' => $payload['slug'] ?? null,
            'summary' => $payload['summary'] ?? $template->description,
            'description' => $payload['description'] ?? null,
            'starts_at' => $payload['starts_at'] ?? CarbonImmutable::now(),
            'ends_at' => $payload['ends_at'] ?? null,
            'timezone' => $payload['timezone'] ?? null,
            'visibility' => $payload['visibility'] ?? null,
            'delivery_mode' => $payload['delivery_mode'] ?? null,
            'capacity' => $payload['capacity'] ?? null,
            'metadata' => $payload['metadata'] ?? null,
        ]));
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
                    'starts_at' => $payload['starts_at'] ?? CarbonImmutable::now()->addDay(),
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

                if (! $targetOccurrence instanceof EventOccurrence) {
                    break;
                }

                $this->createSession->handle($targetOccurrence, [
                    'title' => $payload['title'] ?? "Session {$item->item_key}",
                    'slug' => $payload['slug'] ?? null,
                    'summary' => $payload['summary'] ?? null,
                    'description' => $payload['description'] ?? null,
                    'starts_at' => $payload['starts_at'] ?? CarbonImmutable::now(),
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

    /**
     * @param  array<string, mixed>  $overrides
     * @param  list<string>  $allowed
     * @return array<string, mixed>
     */
    private function payload(EventTemplate $template, array $overrides, array $allowed): array
    {
        $this->assertOverridesSafe($overrides);

        return array_merge(
            is_array($template->payload) ? $template->payload : [],
            Arr::only($overrides, $allowed),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function assertOverridesSafe(array $overrides): void
    {
        foreach (self::GUARDED_OVERRIDES as $key) {
            if (array_key_exists($key, $overrides)) {
                throw new InvalidArgumentException(sprintf('Template overrides cannot set [%s].', $key));
            }
        }
    }

    private function findTemplate(string $templateId): EventTemplate
    {
        return EventTemplate::query()->whereKey($templateId)->firstOrFail();
    }
}
