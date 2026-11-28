<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventImportScopeValidator;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use Illuminate\Validation\ValidationException;

it('rejects unauthorized event and mismatched occurrence imports', function (): void {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $foreign = OwnerContext::withOwner($outsider, fn (): Event => Event::factory()->create());

    OwnerContext::withOwner($owner, function () use ($owner, $outsider, $foreign): void {
        $event = Event::factory()->create();
        $otherEvent = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $otherOccurrence = EventOccurrence::factory()->create(['event_id' => $otherEvent->id]);
        $validator = app(EventImportScopeValidator::class);

        $validator->validate($event->id, $occurrence->id, $owner, 'manageAdmissions');

        expect(fn () => $validator->validate($foreign->id, null, $owner, 'manageAdmissions'))->toThrow(ValidationException::class)
            ->and(fn () => $validator->validate($event->id, $otherOccurrence->id, $owner, 'manageAdmissions'))->toThrow(ValidationException::class)
            ->and(fn () => $validator->validate($event->id, $occurrence->id, $outsider, 'update'))->toThrow(ValidationException::class)
            ->and(fn () => $validator->validate($event->id, 'invalid', $owner, 'update'))->toThrow(ValidationException::class);
    });
});
