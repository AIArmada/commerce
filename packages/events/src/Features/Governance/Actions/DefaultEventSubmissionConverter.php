<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Governance\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventSubmissionConverter;
use AIArmada\Events\Features\Catalog\Api\CatalogApi;
use AIArmada\Events\Features\Catalog\Api\EventCreationData;
use AIArmada\Events\Features\Governance\States\EventModerationStatus\Converted;
use AIArmada\Events\Features\Governance\States\EventModerationStatus\EventModerationStatus as EventModerationStatusState;
use AIArmada\Events\Platform\EventContentNormalizer;
use AIArmada\Events\Platform\ModelResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class DefaultEventSubmissionConverter implements EventSubmissionConverter
{
    public function __construct(
        private readonly EventContentNormalizer $contentNormalizer,
        private readonly CatalogApi $catalog,
    ) {}

    public function convert(string $submissionId): string
    {
        $submissionClass = ModelResolver::submissionClass();

        $submission = $submissionClass::query()->whereKey($submissionId)->firstOrFail();

        $owner = $submission->getRelationValue('target');

        if (! $owner instanceof Model) {
            throw new InvalidArgumentException('Event submissions must resolve to an owner model before conversion.');
        }

        $data = $submission->getAttribute('submission_data');
        $data = is_array($data) ? $data : [];

        $eventTitle = blank($data['title'] ?? null)
            ? 'Untitled Event'
            : $this->contentNormalizer->normalizeTitle((string) $data['title']);

        $eventSummary = $this->contentNormalizer->normalizeSummary(
            blank($data['summary'] ?? null) ? null : (string) $data['summary'],
        );

        $eventDescription = $this->contentNormalizer->normalizeDescription(
            blank($data['description'] ?? null) ? null : (string) $data['description'],
        );

        return DB::transaction(function () use (
            $data,
            $eventDescription,
            $eventSummary,
            $eventTitle,
            $owner,
            $submissionClass,
            $submissionId,
        ): string {
            $locked = $submissionClass::query()
                ->whereKey($submissionId)
                ->lockForUpdate()
                ->firstOrFail();

            $state = $locked->getAttribute('status');

            if (! $state instanceof EventModerationStatusState) {
                throw new RuntimeException(sprintf(
                    'Event submission [%s] does not expose a moderation status state.',
                    $submissionId,
                ));
            }

            if ($state instanceof Converted) {
                throw new RuntimeException(sprintf('Event submission [%s] has already been converted.', $submissionId));
            }

            if (! $state->canTransitionTo(Converted::class)) {
                throw new RuntimeException(sprintf(
                    'Event submission [%s] cannot be converted from [%s].',
                    $submissionId,
                    $state->getValue(),
                ));
            }

            $eventId = OwnerContext::withOwner($owner, fn (): string => $this->catalog->createEventFromSubmission(
                new EventCreationData(
                    ownerType: $owner->getMorphClass(),
                    ownerId: (string) $owner->getKey(),
                    title: $eventTitle,
                    summary: $eventSummary,
                    description: $eventDescription,
                    type: isset($data['type']) ? (string) $data['type'] : null,
                    visibility: isset($data['visibility']) ? (string) $data['visibility'] : 'public',
                    deliveryMode: isset($data['delivery_mode']) ? (string) $data['delivery_mode'] : 'physical',
                    timezone: isset($data['timezone']) ? (string) $data['timezone'] : 'UTC',
                    startsAt: isset($data['starts_at']) ? (string) $data['starts_at'] : null,
                    endsAt: isset($data['ends_at']) ? (string) $data['ends_at'] : null,
                    involvements: $this->listOfArrays($data['involvements'] ?? null),
                    classifications: $this->listOfArrays($data['classifications'] ?? null),
                ),
            ));

            $locked->setAttribute('event_id', $eventId);
            $state->transitionTo(Converted::class);

            return $eventId;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listOfArrays(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];

        foreach ($value as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
