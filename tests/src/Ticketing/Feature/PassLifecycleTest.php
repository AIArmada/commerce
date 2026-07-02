<?php

declare(strict_types=1);

use AIArmada\Ticketing\Models\Pass;

it('issues a pass', function () {
    $pass = Pass::factory()->create(['status' => 'issued']);
    $pass->markActivated();

    expect((string) $pass->status)->toBe('activated');
});

it('receives owner assignment from HasOwner trait', function () {
    $pass = Pass::factory()->create();

    expect($pass->owner_type)->not->toBeNull();
    expect($pass->owner_id)->not->toBeNull();
});

it('prevents transfer on used pass', function () {
    $pass = Pass::factory()->create(['status' => 'used']);

    expect($pass->isValid())->toBeFalse();
});

it('transitions from issued to cancelled', function () {
    $pass = Pass::factory()->create(['status' => 'issued']);
    $pass->markCancelled('cancelled by organiser');

    expect((string) $pass->status)->toBe('cancelled')
        ->and($pass->status_reason)->toBe('cancelled by organiser');
});

it('transitions from issued to revoked', function () {
    $pass = Pass::factory()->create(['status' => 'issued']);
    $pass->markRevoked('revoked for fraud');

    expect((string) $pass->status)->toBe('revoked');
});

it('transitions from activated to used', function () {
    $pass = Pass::factory()->create(['status' => 'activated']);
    $pass->markUsed();

    expect((string) $pass->status)->toBe('used');
});

it('transitions from activated to expired', function () {
    $pass = Pass::factory()->create(['status' => 'activated']);
    $pass->markExpired();

    expect((string) $pass->status)->toBe('expired');
});

it('blocks invalid transition', function () {
    $pass = Pass::factory()->create(['status' => 'issued']);

    expect(fn () => $pass->markUsed())
        ->toThrow(Spatie\ModelStates\Exceptions\TransitionNotFound::class);
});

it('returns isValid false for expired pass', function () {
    $pass = Pass::factory()->create(['status' => 'expired']);

    expect($pass->isValid())->toBeFalse();
});
