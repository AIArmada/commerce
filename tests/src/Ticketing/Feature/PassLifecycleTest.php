<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Ticketing\Models\Pass;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

beforeEach(function (): void {
    Relation::morphMap(['workshop' => User::class]);
});

it('issues a pass', function (): void {
    $pass = Pass::factory()->create(['status' => 'issued']);
    $pass->markActivated();

    expect((string) $pass->status)->toBe('activated');
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
    $pass->markCancelled('cancelled by organiser');

    expect((string) $pass->status)->toBe('cancelled')
        ->and($pass->status_reason)->toBe('cancelled by organiser');
});

it('transitions from issued to revoked', function (): void {
    $pass = Pass::factory()->create(['status' => 'issued']);
    $pass->markRevoked('revoked for fraud');

    expect((string) $pass->status)->toBe('revoked');
});

it('transitions from activated to used', function (): void {
    $pass = Pass::factory()->create(['status' => 'activated']);
    $pass->markUsed();

    expect((string) $pass->status)->toBe('used');
});

it('transitions from activated to expired', function (): void {
    $pass = Pass::factory()->create(['status' => 'activated']);
    $pass->markExpired();

    expect((string) $pass->status)->toBe('expired');
});

it('blocks invalid transition', function (): void {
    $pass = Pass::factory()->create(['status' => 'issued']);

    expect(fn () => $pass->markUsed())
        ->toThrow(TransitionNotFound::class);
});

it('returns isValid false for expired pass', function (): void {
    $pass = Pass::factory()->create(['status' => 'expired']);

    expect($pass->isValid())->toBeFalse();
});
