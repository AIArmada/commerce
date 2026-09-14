<?php

declare(strict_types=1);

use AIArmada\Cart\Listeners\HandleUserLoginAttempt;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Support\Facades\Auth;

describe('HandleUserLoginAttempt Coverage Tests', function (): void {
    beforeEach(function (): void {
        $this->listener = new HandleUserLoginAttempt;
        Auth::logout();
        session()->forget(HandleUserLoginAttempt::PRE_LOGIN_SESSION_KEY);
    });

    it('stashes the pre-login session id in the guest session', function (): void {
        expect(Auth::check())->toBeFalse();

        $credentials = ['email' => 'test@example.com', 'password' => 'password'];
        $event = new Attempting('web', $credentials, false);

        $this->listener->handle($event);

        expect(session(HandleUserLoginAttempt::PRE_LOGIN_SESSION_KEY))->toBe(session()->getId());
    });

    it('stashes regardless of credential shape', function (): void {
        expect(Auth::check())->toBeFalse();

        $event = new Attempting('web', ['password' => 'password'], false);

        $this->listener->handle($event);

        expect(session(HandleUserLoginAttempt::PRE_LOGIN_SESSION_KEY))->toBe(session()->getId());
    });

    it('does not stash when user is already authenticated', function (): void {
        Auth::shouldReceive('check')->once()->andReturn(true);

        $credentials = ['email' => 'test@example.com', 'password' => 'password'];
        $event = new Attempting('web', $credentials, false);

        $this->listener->handle($event);

        expect(session(HandleUserLoginAttempt::PRE_LOGIN_SESSION_KEY))->toBeNull();
    });

    it('does not leak the session id into shared cache', function (): void {
        expect(Auth::check())->toBeFalse();

        $event = new Attempting('web', ['email' => 'test@example.com', 'password' => 'password'], false);

        $this->listener->handle($event);

        expect(Cache::getStore()->get('cart.migration.' . hash('sha256', 'test@example.com')))->toBeNull();
    });
});
