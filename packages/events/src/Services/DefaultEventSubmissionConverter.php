<?php
declare(strict_types=1);
namespace AIArmada\Events\Services;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventSubmissionConverter;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSubmission;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class DefaultEventSubmissionConverter implements EventSubmissionConverter
{
    public function convert(EventSubmission $submission): Event
    {
        $data = $submission->submission_data ?? [];
        $owner = $submission->target;

        if (! $owner instanceof Model) {
            throw new InvalidArgumentException('Event submissions must resolve to an owner model before conversion.');
        }

        $event = OwnerContext::withOwner($owner, function () use ($data): Event {
            $event = Event::query()->create([
                'title' => $data['title'] ?? 'Untitled Event',
                'summary' => $data['summary'] ?? null,
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? null,
                'status' => Event::DRAFT,
                'visibility' => $data['visibility'] ?? Event::PUBLIC,
                'delivery_mode' => $data['delivery_mode'] ?? Event::DELIVERY_PHYSICAL,
                'timezone' => $data['timezone'] ?? 'UTC',
            ]);

            if (isset($data['starts_at'])) {
                $event->occurrences()->create([
                    'title' => $event->title,
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

        $submission->update([
            'status' => 'converted',
            'event_id' => $event->id,
        ]);

        return $event;
    }
}
