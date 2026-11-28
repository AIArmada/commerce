<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Events\Features\Registrations\Models\EventRegistration;
use AIArmada\Ticketing\Contracts\PassTransferServiceInterface;
use AIArmada\Ticketing\Features\Passes\Actions\ActivatePassAction;
use AIArmada\Ticketing\Features\Passes\Actions\CancelPassAction;
use AIArmada\Ticketing\Features\Passes\Actions\ExpirePassAction;
use AIArmada\Ticketing\Features\Passes\Actions\RevokePassAction;
use AIArmada\Ticketing\Features\Passes\Actions\UsePassAction;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

beforeEach(function (): void {
    Relation::morphMap(['workshop' => User::class]);
});

it('issues a pass', function (): void {
    $pass = Pass::factory()->create(['status' => 'issued']);
    app(ActivatePassAction::class)->handle($pass);

    expect((string) $pass->fresh()->status)->toBe('activated');
});

it('receives owner assignment from HasOwner trait', function (): void {
    $pass = Pass::factory()->create();

    expect($pass->owner_type)->not->toBeNull();
    expect($pass->owner_id)->not->toBeNull();
});

it('prevents transfer on used pass', function (): void {
    $pass = Pass::factory()->create(['status' => 'used']);

    expect($pass->isValid())->toBeFalse();
});

it('transitions from issued to cancelled', function (): void {
    $pass = Pass::factory()->create(['status' => 'issued']);
    app(CancelPassAction::class)->handle($pass, 'cancelled by organiser');

    expect((string) $pass->fresh()->status)->toBe('cancelled')
        ->and($pass->fresh()->status_reason)->toBe('cancelled by organiser');
});

it('transitions from issued to revoked', function (): void {
    $pass = Pass::factory()->create(['status' => 'issued']);
    app(RevokePassAction::class)->handle($pass, 'revoked for fraud');

    expect((string) $pass->fresh()->status)->toBe('revoked');
});

it('transitions from activated to used', function (): void {
    $pass = Pass::factory()->create(['status' => 'activated']);
    app(UsePassAction::class)->handle($pass);

    expect((string) $pass->fresh()->status)->toBe('used');
});

it('transitions from activated to expired', function (): void {
    $pass = Pass::factory()->create(['status' => 'activated']);
    app(ExpirePassAction::class)->handle($pass);

    expect((string) $pass->fresh()->status)->toBe('expired');
});

it('blocks invalid transition', function (): void {
    $pass = Pass::factory()->create(['status' => 'issued']);

    expect(fn () => app(UsePassAction::class)->handle($pass))
        ->toThrow(TransitionNotFound::class);
});

it('returns isValid false for expired pass', function (): void {
    $pass = Pass::factory()->create(['status' => 'expired']);

    expect($pass->isValid())->toBeFalse();
});

it('returns isValid false when its registration has been refunded', function (): void {
    $registration = EventRegistration::factory()->create(['status' => 'refunded']);
    $pass = Pass::factory()->create([
        'registration_type' => $registration->getMorphClass(),
        'registration_id' => $registration->getKey(),
        'status' => 'activated',
    ]);
    $pass->setRelation('registration', $registration);

    expect($pass->isValid())->toBeFalse();
});

it('refuses activation when the associated registration has been refunded', function (): void {
    $registration = EventRegistration::factory()->create(['status' => 'refunded']);
    $pass = Pass::factory()->create([
        'registration_type' => $registration->getMorphClass(),
        'registration_id' => $registration->getKey(),
        'status' => 'issued',
    ]);

    expect(fn () => app(ActivatePassAction::class)->handle($pass))->toThrow(AuthorizationException::class);

    expect((string) $pass->fresh()->status)->toBe('issued')
        ->and($pass->fresh()->activated_at)->toBeNull();
});

it('refuses redemption when the associated registration has been refunded', function (): void {
    $registration = EventRegistration::factory()->create(['status' => 'refunded']);
    $pass = Pass::factory()->create([
        'registration_type' => $registration->getMorphClass(),
        'registration_id' => $registration->getKey(),
        'status' => 'activated',
    ]);

    expect(fn () => app(UsePassAction::class)->handle($pass))->toThrow(AuthorizationException::class);

    expect((string) $pass->fresh()->status)->toBe('activated')
        ->and($pass->fresh()->used_at)->toBeNull();
});

it('does not consider pending passes valid or transferable', function (): void {
    $pass = Pass::factory()->create(['status' => 'pending']);

    expect($pass->isValid())->toBeFalse()
        ->and(app(PassTransferServiceInterface::class)->canTransfer((string) $pass->getKey()))->toBeFalse();
});
