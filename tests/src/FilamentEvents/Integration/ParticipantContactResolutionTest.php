<?php

declare(strict_types=1);

use AIArmada\Contacting\Models\ContactMethod;
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
