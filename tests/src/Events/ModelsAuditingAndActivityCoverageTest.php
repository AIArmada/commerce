<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAgendaItem;
use AIArmada\Events\Models\EventAsset;
use AIArmada\Events\Models\EventAttendance;
use AIArmada\Events\Models\EventChangeNotice;
use AIArmada\Events\Models\EventClassification;
use AIArmada\Events\Models\EventEngagement;
use AIArmada\Events\Models\EventPerson;
use AIArmada\Events\Models\EventReferenceAssignment;
use AIArmada\Events\Models\EventReview;
use AIArmada\Events\Models\EventSeries;
use AIArmada\Events\Models\EventSubmission;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Events\Models\Venue;
use OwenIt\Auditing\Contracts\Auditable;

it('events models are auditable and activity loggable', function (): void {
    $models = [
        Event::class,
        EventSeries::class,
        EventPerson::class,
        EventClassification::class,
        EventAsset::class,
        EventReferenceAssignment::class,
        EventAgendaItem::class,
        EventSubmission::class,
        EventReview::class,
        EventChangeNotice::class,
        EventAttendance::class,
        EventEngagement::class,
        Occurrence::class,
        Registration::class,
        Venue::class,
    ];

    foreach ($models as $model) {
        $traits = class_uses_recursive($model);

        expect($traits)->toContain(HasCommerceAudit::class)
            ->and($traits)->toContain(LogsCommerceActivity::class)
            ->and(in_array(Auditable::class, class_implements($model), true))->toBeTrue();
    }
});
