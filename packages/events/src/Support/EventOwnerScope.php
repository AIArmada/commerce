<?php

declare(strict_types=1);

namespace AIArmada\Events\Support;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAccessPolicy;
use AIArmada\Events\Models\EventAttendance;
use AIArmada\Events\Models\EventAttendanceLog;
use AIArmada\Events\Models\EventAttribute;
use AIArmada\Events\Models\EventAudience;
use AIArmada\Events\Models\EventAudienceProfile;
use AIArmada\Events\Models\EventChangeLog;
use AIArmada\Events\Models\EventClassification;
use AIArmada\Events\Models\EventEligibilityRule;
use AIArmada\Events\Models\EventFacility;
use AIArmada\Events\Models\EventHeadcountLog;
use AIArmada\Events\Models\EventInvolvement;
use AIArmada\Events\Models\EventItinerary;
use AIArmada\Events\Models\EventItineraryItem;
use AIArmada\Events\Models\EventLanguage;
use AIArmada\Events\Models\EventLink;
use AIArmada\Events\Models\EventLocation;
use AIArmada\Events\Models\EventMaterial;
use AIArmada\Events\Models\EventMedia;
use AIArmada\Events\Models\EventNotificationBatch;
use AIArmada\Events\Models\EventNotificationDelivery;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventPass;
use AIArmada\Events\Models\EventReference;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventRegistrationAnswer;
use AIArmada\Events\Models\EventRegistrationItem;
use AIArmada\Events\Models\EventRegistrationParticipant;
use AIArmada\Events\Models\EventSearchDocument;
use AIArmada\Events\Models\EventSeat;
use AIArmada\Events\Models\EventSeatAllocation;
use AIArmada\Events\Models\EventSeatHold;
use AIArmada\Events\Models\EventSeatMap;
use AIArmada\Events\Models\EventSeatSection;
use AIArmada\Events\Models\EventSeriesItem;
use AIArmada\Events\Models\EventSeriesRule;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventSubmissionAttachment;
use AIArmada\Events\Models\EventSubmissionLog;
use AIArmada\Events\Models\EventTemplateItem;
use AIArmada\Events\Models\EventTicketType;
use AIArmada\Events\Models\EventTicketTypeComponent;
use AIArmada\Events\Models\EventTicketTypeProduct;
use AIArmada\Events\Models\EventTicketTypeSeatingOption;
use AIArmada\Events\Models\EventTimeExpression;
use AIArmada\Events\Models\EventUpdate;
use AIArmada\Events\Models\EventUpdateItem;
use AIArmada\Events\Models\EventWalkIn;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;

final class EventOwnerScope implements Scope
{
    public function __construct(
        private readonly string $eventRelation,
    ) {}

    public function apply(Builder $builder, Model $model): void
    {
        if (! self::enabled()) {
            return;
        }

        $owner = OwnerContext::resolve();

        OwnerContext::assertResolvedOrExplicitGlobal(
            $owner,
            sprintf('%s requires an owner context or explicit global context.', $model::class),
        );

        if ($this->eventRelation === 'event') {
            $event = new Event;

            $builder->whereIn(
                $model->qualifyColumn('event_id'),
                Event::query()->select($event->qualifyColumn($event->getKeyName())),
            );

            return;
        }

        $builder->whereHas($this->eventRelation);
    }

    public static function registerDefaults(): void
    {
        foreach (self::eventRelations() as $modelClass => $eventRelation) {
            $modelClass::addGlobalScope(new self($eventRelation));

            $guard = static function (Model $model) use ($eventRelation): void {
                self::guardWrite($model, $eventRelation);
            };

            $modelClass::saving($guard);
            $modelClass::deleting($guard);
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function supports(string $modelClass): bool
    {
        return array_key_exists($modelClass, self::eventRelations());
    }

    private static function guardWrite(Model $model, string $eventRelation): void
    {
        if (! self::enabled()) {
            return;
        }

        $eventId = $model->getAttribute('event_id');

        if ($eventId !== null) {
            EventWriteGuard::findOrFail($eventId);
        }

        if ($eventRelation === 'event') {
            if ($eventId === null) {
                throw new AuthorizationException(sprintf(
                    'An event id is required to write %s records.',
                    $model::class,
                ));
            }

            return;
        }

        $parentRelation = str($eventRelation)->before('.')->toString();
        $relation = $model->{$parentRelation}();

        if (! $relation instanceof BelongsTo
            || $model->getAttribute($relation->getForeignKeyName()) === null) {
            throw new AuthorizationException(sprintf(
                'A visible owner-scoped parent is required to write %s records.',
                $model::class,
            ));
        }

        $model->unsetRelation($parentRelation);
        $related = $relation->getResults();

        if (! $related instanceof Model) {
            throw new AuthorizationException(sprintf(
                'A visible owner-scoped parent is required to write %s records.',
                $model::class,
            ));
        }

        EventTenantBoundary::assertWritable($related);
    }

    private static function enabled(): bool
    {
        return (bool) config('events.features.owner.enabled', true);
    }

    /**
     * @return array<class-string<Model>, string>
     */
    private static function eventRelations(): array
    {
        return [
            EventAccessPolicy::class => 'event',
            EventAttendance::class => 'event',
            EventAttendanceLog::class => 'attendance.event',
            EventAttribute::class => 'event',
            EventAudience::class => 'event',
            EventAudienceProfile::class => 'event',
            EventChangeLog::class => 'event',
            EventClassification::class => 'event',
            EventEligibilityRule::class => 'event',
            EventFacility::class => 'event',
            EventHeadcountLog::class => 'event',
            EventInvolvement::class => 'event',
            EventItinerary::class => 'event',
            EventItineraryItem::class => 'itinerary.event',
            EventLanguage::class => 'event',
            EventLink::class => 'event',
            EventLocation::class => 'event',
            EventMaterial::class => 'event',
            EventMedia::class => 'event',
            EventNotificationBatch::class => 'event',
            EventNotificationDelivery::class => 'batch.event',
            EventOccurrence::class => 'event',
            EventPass::class => 'event',
            EventReference::class => 'event',
            EventRegistration::class => 'event',
            EventRegistrationAnswer::class => 'registration.event',
            EventRegistrationItem::class => 'registration.event',
            EventRegistrationParticipant::class => 'registration.event',
            EventSearchDocument::class => 'event',
            EventSeriesItem::class => 'series',
            EventSeriesRule::class => 'series',
            EventSeat::class => 'section.map.event',
            EventSeatAllocation::class => 'event',
            EventSeatHold::class => 'event',
            EventSeatMap::class => 'event',
            EventSeatSection::class => 'map.event',
            EventSession::class => 'event',
            EventTicketType::class => 'event',
            EventTicketTypeComponent::class => 'parentTicketType.event',
            EventTicketTypeProduct::class => 'ticketType.event',
            EventTicketTypeSeatingOption::class => 'ticketType.event',
            EventSubmissionAttachment::class => 'submission',
            EventSubmissionLog::class => 'submission',
            EventTemplateItem::class => 'template',
            EventTimeExpression::class => 'event',
            EventUpdate::class => 'event',
            EventUpdateItem::class => 'eventUpdate.event',
            EventWalkIn::class => 'event',
        ];
    }
}
