<?php

declare(strict_types=1);

namespace AIArmada\Events\Resolvers;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventChangeNoticeAudienceResolver;
use AIArmada\Events\Data\EventChangeNoticeAudienceData;
use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Events\Models\EventChange;
use AIArmada\Events\Models\EventEngagement;
use AIArmada\Events\Models\Registration;
use Illuminate\Database\Eloquent\Builder;

final class DefaultEventChangeNoticeAudienceResolver implements EventChangeNoticeAudienceResolver
{
    public function resolve(EventChange $notice): EventChangeNoticeAudienceData
    {
        $owner = OwnerContext::fromTypeAndId(
            is_string($notice->owner_type) ? $notice->owner_type : null,
            is_scalar($notice->owner_id) ? (string) $notice->owner_id : null,
        );

        return OwnerContext::withOwner($owner, function () use ($notice): EventChangeNoticeAudienceData {
            $occurrenceIds = $notice->event
                ->occurrences()
                ->pluck('id')
                ->all();

            $replacementOccurrenceId = is_string($notice->replacement_occurrence_id) && mb_trim($notice->replacement_occurrence_id) !== ''
                ? mb_trim($notice->replacement_occurrence_id)
                : null;

            if ($replacementOccurrenceId !== null && ! in_array($replacementOccurrenceId, $occurrenceIds, true)) {
                $occurrenceIds[] = $replacementOccurrenceId;
            }

            $registrations = Registration::query()
                ->whereIn('occurrence_id', $occurrenceIds)
                ->get();

            $engagements = EventEngagement::query()
                ->where('event_id', $notice->event_id)
                ->when($replacementOccurrenceId !== null, static function (Builder $query) use ($replacementOccurrenceId): void {
                    $query->orWhere('occurrence_id', $replacementOccurrenceId);
                })
                ->get();

            return new EventChangeNoticeAudienceData(
                noticeId: $notice->id,
                eventId: $notice->event_id,
                registered: $registrations
                    ->filter(static fn (Registration $registration): bool => $registration->status === RegistrationStatus::Confirmed)
                    ->pluck('id')
                    ->values()
                    ->all(),
                waitlisted: $registrations
                    ->filter(static fn (Registration $registration): bool => $registration->status === RegistrationStatus::Waitlisted)
                    ->pluck('id')
                    ->values()
                    ->all(),
                paid: $registrations
                    ->filter(static fn (Registration $registration): bool => $registration->order_id !== null)
                    ->pluck('id')
                    ->values()
                    ->all(),
                saved: $engagements
                    ->filter(static fn (EventEngagement $engagement): bool => $engagement->isSaved())
                    ->pluck('id')
                    ->values()
                    ->all(),
                going: $engagements
                    ->filter(static fn (EventEngagement $engagement): bool => $engagement->isGoing())
                    ->pluck('id')
                    ->values()
                    ->all(),
                interested: $engagements
                    ->filter(static fn (EventEngagement $engagement): bool => $engagement->isInterested())
                    ->pluck('id')
                    ->values()
                    ->all(),
            );
        });
    }
}
