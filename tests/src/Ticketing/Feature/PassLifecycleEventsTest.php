<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Ticketing\Events\PassCancelled;
use AIArmada\Ticketing\Events\PassExpired;
use AIArmada\Ticketing\Events\PassRevoked;
use AIArmada\Ticketing\Events\PassVoided;
use AIArmada\Ticketing\Models\Pass;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Relation::morphMap(['workshop' => User::class]);
    $this->pass = Pass::factory()->create(['status' => 'issued']);
});

it('fires PassRevoked on revoke', function (): void {
    Event::fake([PassRevoked::class]);
    $this->pass->markRevoked('test');

    Event::assertDispatched(PassRevoked::class, fn (PassRevoked $event): bool => $event->pass->is($this->pass));
});

it('fires PassCancelled on cancel', function (): void {
    Event::fake([PassCancelled::class]);
    $this->pass->markCancelled('test');

    Event::assertDispatched(PassCancelled::class, fn (PassCancelled $event): bool => $event->pass->is($this->pass));
});

it('fires PassVoided on void (from revoked)', function (): void {
    $pass = Pass::factory()->create(['status' => 'issued']);
    $pass->markRevoked('prep for void');

    Event::fake([PassVoided::class]);
    $pass->markVoided('final void');

    Event::assertDispatched(PassVoided::class, fn (PassVoided $event): bool => $event->pass->is($pass));
});

it('fires PassExpired on expire', function (): void {
    $pass = Pass::factory()->create(['status' => 'activated']);

    Event::fake([PassExpired::class]);
    $pass->markExpired();

    Event::assertDispatched(PassExpired::class, fn (PassExpired $event): bool => $event->pass->is($pass));
});
