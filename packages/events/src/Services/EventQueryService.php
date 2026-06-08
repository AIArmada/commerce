<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Contracts\EventSearchEngine;
use AIArmada\Events\Data\EventChangeNoticePayloadData;
use AIArmada\Events\Data\EventDetailData;
use AIArmada\Events\Data\EventReviewSchemaData;
use AIArmada\Events\Data\EventSearchCardData;
use AIArmada\Events\Data\EventSearchCriteria;
use AIArmada\Events\Data\EventSearchResultData;
use AIArmada\Events\Data\OccurrenceDetailData;
use AIArmada\Events\Data\RegistrationStatusData;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventChangeNotice;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;

final class EventQueryService
{
    public function __construct(
        private readonly EventSearchEngine $searchEngine,
    ) {}

    public function search(EventSearchCriteria $criteria): EventSearchResultData
    {
        return $this->searchEngine->search($criteria);
    }

    public function card(Event $event): EventSearchCardData
    {
        return EventSearchCardData::fromEvent($event);
    }

    public function detail(Event $event): EventDetailData
    {
        return EventDetailData::fromEvent($event);
    }

    public function occurrence(Occurrence $occurrence): OccurrenceDetailData
    {
        return OccurrenceDetailData::fromOccurrence($occurrence);
    }

    public function reviewSchema(Event $event): EventReviewSchemaData
    {
        return EventReviewSchemaData::fromEvent($event);
    }

    public function registrationStatus(Registration $registration): RegistrationStatusData
    {
        return RegistrationStatusData::fromRegistration($registration);
    }

    public function changeNoticePayload(EventChangeNotice $notice): EventChangeNoticePayloadData
    {
        return EventChangeNoticePayloadData::fromNotice($notice);
    }
}
