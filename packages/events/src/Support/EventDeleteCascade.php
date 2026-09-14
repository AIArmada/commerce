<?php

declare(strict_types=1);

namespace AIArmada\Events\Support;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAccessPolicy;
use AIArmada\Events\Models\EventApprovalRequest;
use AIArmada\Events\Models\EventAttendance;
use AIArmada\Events\Models\EventAttribute;
use AIArmada\Events\Models\EventAudience;
use AIArmada\Events\Models\EventAudienceProfile;
use AIArmada\Events\Models\EventAvailabilityBlock;
use AIArmada\Events\Models\EventChangeLog;
use AIArmada\Events\Models\EventClassification;
use AIArmada\Events\Models\EventEligibilityRule;
use AIArmada\Events\Models\EventEscalation;
use AIArmada\Events\Models\EventFacility;
use AIArmada\Events\Models\EventHeadcountLog;
use AIArmada\Events\Models\EventInvolvement;
use AIArmada\Events\Models\EventItinerary;
use AIArmada\Events\Models\EventItineraryItem;
use AIArmada\Events\Models\EventLanguage;
use AIArmada\Events\Models\EventLink;
use AIArmada\Events\Models\EventLocation;
use AIArmada\Events\Models\EventManagementAssignment;
use AIArmada\Events\Models\EventManagementAssignmentRequest;
use AIArmada\Events\Models\EventMaterial;
use AIArmada\Events\Models\EventMedia;
use AIArmada\Events\Models\EventModerationAction;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRecurrenceRule;
use AIArmada\Events\Models\EventReference;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventRegistrationQuestion;
use AIArmada\Events\Models\EventReport;
use AIArmada\Events\Models\EventRevision;
use AIArmada\Events\Models\EventSearchDocument;
use AIArmada\Events\Models\EventSeriesItem;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventSubmission;
use AIArmada\Events\Models\EventTimeExpression;
use AIArmada\Events\Models\EventUpdate;
use AIArmada\Events\Models\EventVerification;
use AIArmada\Events\Models\EventWalkIn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Application-level cascades for the event aggregate.
 *
 * The package uses no database foreign keys, so deleting an event,
 * occurrence, session, or registration must delete the owned subtree here.
 * Every row is deleted through its model so owner guards and deeper
 * deleting hooks still run.
 */
final class EventDeleteCascade
{
    /**
     * First-level children keyed by their scope columns.
     *
     * Second-level rows (participants, answers, items, logs, attachments,
     * itinerary/update items) cascade through their parent's deleting hook.
     * EventItineraryItem is listed as well because its parent has no
     * session column, so session deletes would otherwise orphan it.
     *
     * @var array<class-string<Model>, list<string>>
     */
    private const array SCOPED_CHILDREN = [
        EventAccessPolicy::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventAttendance::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventAttribute::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventAudience::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventAudienceProfile::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventAvailabilityBlock::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventChangeLog::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventClassification::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventEligibilityRule::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventEscalation::class => ['event_id'],
        EventFacility::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventHeadcountLog::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventInvolvement::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventItinerary::class => ['event_id', 'event_occurrence_id'],
        EventItineraryItem::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventLanguage::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventLink::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventLocation::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventManagementAssignment::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventMaterial::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventMedia::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventModerationAction::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventRecurrenceRule::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventReference::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventRegistration::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventRegistrationQuestion::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventReport::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventRevision::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventSearchDocument::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventSeriesItem::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventSubmission::class => ['event_id', 'event_occurrence_id'],
        EventTimeExpression::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventUpdate::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventVerification::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
        EventWalkIn::class => ['event_id', 'event_occurrence_id', 'event_session_id'],
    ];

    /**
     * Morph relations that can point at an event, occurrence, session, or
     * registration without a matching scope column (e.g. rows created with
     * a null event_id). The value is the morph prefix: `{prefix}_type/id`.
     *
     * @var array<class-string<Model>, string>
     */
    private const array MORPH_CHILDREN = [
        EventManagementAssignment::class => 'manageable',
        EventManagementAssignmentRequest::class => 'manageable',
        EventSeriesItem::class => 'seriesable',
        EventApprovalRequest::class => 'approvable',
    ];

    public static function deleteForEvent(Event $event): void
    {
        DB::transaction(function () use ($event): void {
            EventOccurrence::query()->where('event_id', $event->getKey())->chunkById(200, function ($occurrences): void {
                foreach ($occurrences as $occurrence) {
                    $occurrence->delete();
                }
            });

            EventSession::query()->where('event_id', $event->getKey())->chunkById(200, function ($sessions): void {
                foreach ($sessions as $session) {
                    $session->delete();
                }
            });

            self::deleteScopedChildren('event_id', (string) $event->getKey());
            self::deleteMorphChildren($event);
            self::deleteTicketingChildren($event);
        });
    }

    public static function deleteForOccurrence(EventOccurrence $occurrence): void
    {
        DB::transaction(function () use ($occurrence): void {
            EventSession::query()->where('event_occurrence_id', $occurrence->getKey())->chunkById(200, function ($sessions): void {
                foreach ($sessions as $session) {
                    $session->delete();
                }
            });

            self::deleteScopedChildren('event_occurrence_id', (string) $occurrence->getKey());
            self::deleteMorphChildren($occurrence);
            self::deleteTicketingChildren($occurrence);
        });
    }

    public static function deleteForSession(EventSession $session): void
    {
        DB::transaction(function () use ($session): void {
            self::deleteScopedChildren('event_session_id', (string) $session->getKey());
            self::deleteMorphChildren($session);
            self::deleteTicketingChildren($session);
        });
    }

    public static function deleteForRegistration(EventRegistration $registration): void
    {
        DB::transaction(function () use ($registration): void {
            foreach (['participants', 'answers', 'items', 'attendances'] as $relation) {
                $registration->{$relation}()->chunkById(200, function ($children): void {
                    foreach ($children as $child) {
                        $child->delete();
                    }
                });
            }

            $registration->passes()->chunkById(200, function ($passes): void {
                foreach ($passes as $pass) {
                    $pass->delete();
                }
            });

            EventRegistration::query()->where('parent_registration_id', $registration->getKey())->chunkById(200, function ($children): void {
                foreach ($children as $child) {
                    $child->delete();
                }
            });

            self::deleteMorphChildren($registration);
        });
    }

    private static function deleteScopedChildren(string $column, string $id): void
    {
        foreach (self::SCOPED_CHILDREN as $modelClass => $columns) {
            if (! in_array($column, $columns, true)) {
                continue;
            }

            $modelClass::query()->where($column, $id)->chunkById(200, function ($children): void {
                foreach ($children as $child) {
                    $child->delete();
                }
            });
        }
    }

    private static function deleteMorphChildren(Model $target): void
    {
        foreach (self::MORPH_CHILDREN as $modelClass => $prefix) {
            $modelClass::query()
                ->where($prefix . '_type', $target->getMorphClass())
                ->where($prefix . '_id', $target->getKey())
                ->chunkById(200, function ($children): void {
                    foreach ($children as $child) {
                        $child->delete();
                    }
                });
        }
    }

    private static function deleteTicketingChildren(Event | EventOccurrence | EventSession $target): void
    {
        foreach (['ticketTypes', 'passes', 'seatMaps'] as $relation) {
            $target->{$relation}()->chunkById(200, function ($children): void {
                foreach ($children as $child) {
                    $child->delete();
                }
            });
        }
    }
}
