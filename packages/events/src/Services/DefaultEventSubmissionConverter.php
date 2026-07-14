<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventSubmissionConverter;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSubmission;
use AIArmada\Events\States\EventModerationStatus\Converted;
use AIArmada\Events\Support\ModelResolver;
use AIArmada\Events\Support\Normalization\EventContentNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class DefaultEventSubmissionConverter implements EventSubmissionConverter
{
    public function __construct(
        private readonly EventContentNormalizer $contentNormalizer,
    ) {}

    public function convert(EventSubmission $submission): Event
    {
        $data = $submission->submission_data ?? [];
        $owner = $submission->target;

        if (! $owner instanceof Model) {
            throw new InvalidArgumentException('Event submissions must resolve to an owner model before conversion.');
        }

        $eventTitle = blank($data['title'] ?? null)
            ? 'Untitled Event'
            : $this->contentNormalizer->normalizeTitle((string) $data['title']);

        $eventSummary = $this->contentNormalizer->normalizeSummary(
            blank($data['summary'] ?? null) ? null : (string) $data['summary'],
        );

        $eventDescription = $this->contentNormalizer->normalizeDescription(
            blank($data['description'] ?? null) ? null : (string) $data['description'],
        );

        $event = OwnerContext::withOwner($owner, function () use ($data, $eventDescription, $eventSummary, $eventTitle): Event {
            $eventClass = ModelResolver::eventClass();
            $event = $eventClass::query()->create([
                'title' => $eventTitle,
                'summary' => $eventSummary,
                'description' => $eventDescription,
                'type' => $data['type'] ?? null,
                'status' => Event::DRAFT,
                'visibility' => $data['visibility'] ?? Event::PUBLIC,
                'delivery_mode' => $data['delivery_mode'] ?? Event::DELIVERY_PHYSICAL,
                'timezone' => $data['timezone'] ?? 'UTC',
            ]);

            if (isset($data['starts_at'])) {
                $event->occurrences()->create([
                    'title' => $eventTitle,
                    'starts_at' => CarbonImmutable::parse($data['starts_at']),
                    'ends_at' => isset($data['ends_at']) ? CarbonImmutable::parse($data['ends_at']) : CarbonImmutable::parse($data['starts_at'])->addHours(2),
                    'timezone' => $data['timezone'] ?? 'UTC',
                    'status' => 'scheduled',
                    'visibility' => $event->visibility,
                ]);
            }

            if (isset($data['involvements'])) {
                foreach ($data['involvements'] as $involvement) {
                    $event->involvements()->create($involvement);
                }
            }

            if (isset($data['classifications'])) {
                foreach ($data['classifications'] as $classification) {
                    $event->classifications()->create($classification);
                }
            }

            return $event;
        });

        $submission->status->transitionTo(Converted::class);
        $submission->update(['event_id' => $event->id]);

        return $event;
    }
}
