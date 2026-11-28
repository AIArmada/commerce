<?php

declare(strict_types=1);

use AIArmada\Contacting\Features\ContactMethods\Models\ContactMethod;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Events\Features\Registrations\Models\EventRegistrationParticipant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

it('resolves only valid participant contacts and prefers primary contacts without extra queries', function (string $type, string $resolver): void {
    $now = CarbonImmutable::now();
    $contact = static fn (string $value, bool $primary, int $order, array $attributes = []): ContactMethod => (new ContactMethod)->forceFill([
        'type' => $type,
        'value' => $value,
        'normalized_value' => $value,
        'is_primary' => $primary,
        'sort_order' => $order,
        'is_public' => false,
        ...$attributes,
    ]);

    $participant = new EventRegistrationParticipant;
    $participant->setRelation('contactMethods', new Collection([
        $contact('expired', true, 0, ['valid_until' => $now->subDay()]),
        $contact('future', true, 0, ['valid_from' => $now->addDay()]),
        $contact('secondary', false, 0, ['is_public' => true]),
        $contact('primary-later', true, 20),
        $contact('primary-first', true, 10),
    ]));

    expect($participant->{$resolver}())->toBe('primary-first')
        ->and($participant->{$resolver}(true))->toBe('secondary');
})->with([
    'email' => ['email', 'resolveEmail'],
    'phone' => ['phone', 'resolvePhone'],
]);

it('selects the primary participant for each registration and a deterministic fallback otherwise', function (): void {
    config()->set('events.features.owner.enabled', false);

    $event = Event::factory()->create();
    $registration = EventRegistration::factory()->create(['event_id' => $event->id]);
    $other = EventRegistration::factory()->create(['event_id' => $event->id]);

    $makeParticipant = static fn (EventRegistration $record, bool $primary, string $name, string $createdAt): EventRegistrationParticipant => EventRegistrationParticipant::factory()->create([
        'event_id' => $event->id,
        'event_registration_id' => $record->id,
        'name' => $name,
        'is_primary' => $primary,
        'created_at' => $createdAt,
    ]);

    $makeParticipant($registration, false, 'Secondary', '2027-01-01 09:00:00');
    $primary = $makeParticipant($registration, true, 'Primary', '2027-01-01 10:00:00');
    $makeParticipant($other, false, 'Later', '2027-01-01 11:00:00');
    $fallback = $makeParticipant($other, false, 'Earlier', '2027-01-01 08:00:00');

    $records = EventRegistration::query()->with('displayParticipant.contactMethods')
        ->whereKey([$registration->id, $other->id])->get()->keyBy('id');

    expect($records[$registration->id]->displayParticipant?->id)->toBe($primary->id)
        ->and($records[$other->id]->displayParticipant?->id)->toBe($fallback->id)
        ->and($records[$registration->id]->displayParticipant?->relationLoaded('contactMethods'))->toBeTrue();
});
