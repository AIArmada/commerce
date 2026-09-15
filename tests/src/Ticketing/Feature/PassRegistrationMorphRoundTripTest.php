<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\RevokePassesForRegistrationAction;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\TicketType;
use AIArmada\Ticketing\Services\DefaultPassIssuer;
use AIArmada\Ticketing\Support\PassIssuanceContext;
use Illuminate\Database\Eloquent\Collection;

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

    $passes = OwnerContext::withOwner($owner, function () use ($registration): Collection {
        $ticketType = createRegistrationMorphTicketType();

        return app(DefaultPassIssuer::class)->issuePassesFor(
            new PassIssuanceContext(
                ticketType: $ticketType,
                quantity: 1,
                registrationType: $registration->getMorphClass(),
                registrationId: (string) $registration->getKey(),
            )
        );
    });

    $pass = $passes->first()->fresh();

    expect($pass->registration_type)->toBe($registration->getMorphClass());
    expect($pass->registration_id)->toBe((string) $registration->getKey());
});

it('round-trips an integer-like registration id as a string', function (): void {
    $owner = User::query()->create([
        'name' => 'Registration Morph Owner Two',
        'email' => 'registration-morph-two@example.com',
        'password' => 'secret',
    ]);

    // User is not an owner-scoped model, so the ticketing owner guard skips
    // existence validation and this pins pure storage semantics for the morph.
    $pass = OwnerContext::withOwner($owner, fn (): Pass => Pass::factory()->create([
        'registration_type' => User::class,
        'registration_id' => '42',
    ]));

    expect($pass->fresh()->registration_id)->toBe('42');
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

        app(DefaultPassIssuer::class)->issuePassesFor(
            new PassIssuanceContext(
                ticketType: $ticketType,
                quantity: 1,
                registrationType: $registration->getMorphClass(),
                registrationId: (string) $registration->getKey(),
            )
        );
    });

    $revoked = OwnerContext::withOwner($owner, fn (): int => app(RevokePassesForRegistrationAction::class)->handle($registration));

    expect($revoked)->toBe(1);
});
