<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Ticketing\Events\PassCancelled;
use AIArmada\Ticketing\Events\PassExpired;
use AIArmada\Ticketing\Events\PassRevoked;
use AIArmada\Ticketing\Events\PassVoided;
use AIArmada\Ticketing\Features\Passes\Actions\CancelPassAction;
use AIArmada\Ticketing\Features\Passes\Actions\ExpirePassAction;
use AIArmada\Ticketing\Features\Passes\Actions\RevokePassAction;
use AIArmada\Ticketing\Features\Passes\Actions\VoidPassAction;
use AIArmada\Ticketing\Features\Passes\Models\Pass;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Relation::morphMap(['workshop' => User::class]);
    $this->pass = Pass::factory()->create(['status' => 'issued']);
});

it('fires PassRevoked on revoke', function (): void {
    Event::fake([PassRevoked::class]);
    app(RevokePassAction::class)->handle($this->pass, 'test');

    Event::assertDispatched(PassRevoked::class, fn (PassRevoked $event): bool => $event->pass->is($this->pass));
});

it('fires PassCancelled on cancel', function (): void {
    Event::fake([PassCancelled::class]);
    app(CancelPassAction::class)->handle($this->pass, 'test');

    Event::assertDispatched(PassCancelled::class, fn (PassCancelled $event): bool => $event->pass->is($this->pass));
});

it('fires PassVoided on void (from revoked)', function (): void {
    $pass = Pass::factory()->create(['status' => 'issued']);
    app(RevokePassAction::class)->handle($pass, 'prep for void');

    Event::fake([PassVoided::class]);
    app(VoidPassAction::class)->handle($pass, 'final void');

    Event::assertDispatched(PassVoided::class, fn (PassVoided $event): bool => $event->pass->is($pass));
});

it('fires PassExpired on expire', function (): void {
    $pass = Pass::factory()->create(['status' => 'activated']);

    Event::fake([PassExpired::class]);
    app(ExpirePassAction::class)->handle($pass);

    Event::assertDispatched(PassExpired::class, fn (PassExpired $event): bool => $event->pass->is($pass));
});
