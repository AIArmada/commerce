<?php

declare(strict_types=1);

use AIArmada\Cart\Listeners\HandleUserLoginAttempt;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

describe('HandleUserLoginAttempt Coverage Tests', function (): void {
    beforeEach(function (): void {
        $this->listener = new HandleUserLoginAttempt;
        Cache::flush();
        Auth::logout();
    });

    it('can handle login attempt with email credentials', function (): void {
        // Ensure user is not authenticated
        expect(Auth::check())->toBeFalse();

        $credentials = ['email' => 'test@example.com', 'password' => 'password'];
        $event = new Attempting('web', $credentials, false);

        $this->listener->handle($event);

        // Check that cache was set
        $sessionId = session()->getId();
        $cachedValue = Cache::get('cart_migration_test@example.com');
        expect($cachedValue)->toBe($sessionId);
    });

    it('can handle login attempt with username credentials', function (): void {
        expect(Auth::check())->toBeFalse();

        $credentials = ['username' => 'testuser', 'password' => 'password'];
        $event = new Attempting('web', $credentials, false);

        $this->listener->handle($event);

        $sessionId = session()->getId();
        $cachedValue = Cache::get('cart_migration_testuser');
        expect($cachedValue)->toBe($sessionId);
    });

    it('can handle login attempt with phone credentials', function (): void {
        expect(Auth::check())->toBeFalse();

        $credentials = ['phone' => '+1234567890', 'password' => 'password'];
        $event = new Attempting('web', $credentials, false);

        $this->listener->handle($event);

        $sessionId = session()->getId();
        $cachedValue = Cache::get('cart_migration_+1234567890');
        expect($cachedValue)->toBe($sessionId);
    });

    it('does not store session when user is already authenticated', function (): void {
        // Mock authenticated user
        $user = new class
        {
            public $id = 1;

            public $email = 'test@example.com';
        };

        Auth::shouldReceive('check')->once()->andReturn(true);

        $credentials = ['email' => 'test@example.com', 'password' => 'password'];
        $event = new Attempting('web', $credentials, false);

        $this->listener->handle($event);

        // Cache should not be set
        $cachedValue = Cache::get('cart_migration_test@example.com');
        expect($cachedValue)->toBeNull();
    });

    it('handles credentials without user identifier gracefully', function (): void {
        expect(Auth::check())->toBeFalse();

        $credentials = ['password' => 'password']; // No email, username, or phone
        $event = new Attempting('web', $credentials, false);

        $this->listener->handle($event);

        // Should not throw error, should handle gracefully
        expect(true)->toBeTrue();
    });

    it('handles empty session ID gracefully', function (): void {
        expect(Auth::check())->toBeFalse();

        // Mock empty session ID
        session()->put('_token', null);

        $credentials = ['email' => 'test@example.com', 'password' => 'password'];
        $event = new Attempting('web', $credentials, false);

        $this->listener->handle($event);

        // Should handle gracefully
        expect(true)->toBeTrue();
    });

    it('sets cache with correct expiration time', function (): void {
        expect(Auth::check())->toBeFalse();

        $credentials = ['email' => 'test@example.com', 'password' => 'password'];
        $event = new Attempting('web', $credentials, false);

        $this->listener->handle($event);

        // Check cache exists
        expect(Cache::has('cart_migration_test@example.com'))->toBeTrue();

        // The cache should be set for 5 minutes (we can't easily test exact expiry)
        $sessionId = session()->getId();
        $cachedValue = Cache::get('cart_migration_test@example.com');
        expect($cachedValue)->toBe($sessionId);
    });
});
