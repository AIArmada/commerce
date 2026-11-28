<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Features\Commerce\Actions\RevokePassesForRegistrationAction;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Ticketing\Data\PassIssuanceContext;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use AIArmada\Ticketing\Features\Passes\Persistence\PassIssuer;
use AIArmada\Ticketing\Features\TicketTypes\Models\TicketType;

function createRegistrationMorphTicketType(): TicketType
{
    return TicketType::factory()->create([
        'ticketable_type' => TicketType::class,
        'ticketable_id' => TicketType::factory()->create()->getKey(),
    ]);
}

it('round-trips a uuid registration id through pass issuance', function (): void {
    $owner = User::query()->create([
        'name' => 'Registration Morph Owner',
        'email' => 'registration-morph@example.com',
        'password' => 'secret',
    ]);

    $registration = OwnerContext::withOwner($owner, fn (): EventRegistration => EventRegistration::factory()->create());

    $passes = OwnerContext::withOwner($owner, function () use ($registration): array {
        $ticketType = createRegistrationMorphTicketType();

        return app(PassIssuer::class)->issuePassesFor(
            new PassIssuanceContext(
                ticketTypeId: (string) $ticketType->getKey(),
                quantity: 1,
                registrationType: $registration->getMorphClass(),
                registrationId: (string) $registration->getKey(),
            )
        );
    });

    $pass = OwnerContext::withOwner($owner, fn (): Pass => Pass::query()->findOrFail($passes[0]));

    expect($pass->registration_type)->toBe($registration->getMorphClass());
    expect($pass->registration_id)->toBe((string) $registration->getKey());
});

it('round-trips a custom uuid registration id on a non-owner-scoped morph target', function (): void {
    $owner = User::query()->create([
        'name' => 'Registration Morph Owner Two',
        'email' => 'registration-morph-two@example.com',
        'password' => 'secret',
    ]);
    $registrationId = '00000000-0000-4000-8000-000000000042';

    // User is not an owner-scoped model, so the ticketing owner guard skips
    // existence validation and this pins pure storage semantics for the morph.
    $pass = OwnerContext::withOwner($owner, fn (): Pass => Pass::factory()->create([
        'registration_type' => User::class,
        'registration_id' => $registrationId,
    ]));

    expect($pass->fresh()->registration_id)->toBe($registrationId);
});

it('revokes passes by uuid registration through the string morph', function (): void {
    $owner = User::query()->create([
        'name' => 'Registration Morph Owner Three',
        'email' => 'registration-morph-three@example.com',
        'password' => 'secret',
    ]);

    $registration = OwnerContext::withOwner($owner, fn (): EventRegistration => EventRegistration::factory()->create());

    OwnerContext::withOwner($owner, function () use ($registration): void {
        $ticketType = createRegistrationMorphTicketType();

        app(PassIssuer::class)->issuePassesFor(
            new PassIssuanceContext(
                ticketTypeId: (string) $ticketType->getKey(),
                quantity: 1,
                registrationType: $registration->getMorphClass(),
                registrationId: (string) $registration->getKey(),
            )
        );
    });

    $revoked = OwnerContext::withOwner($owner, fn (): int => app(RevokePassesForRegistrationAction::class)->handle((string) $registration->getKey()));

    expect($revoked)->toBe(1);
});
