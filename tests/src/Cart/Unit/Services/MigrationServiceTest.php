<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Services\CartMigrationService;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

describe('CartMigrationService Identifier Management', function (): void {
    beforeEach(function (): void {
        Cart::clear();
    });

    it('gets identifier for user', function (): void {
        $service = app(CartMigrationService::class);
        $identifier = $service->getUserIdentifier(42);

        expect($identifier)->toBe('42');
    });

    it('gets identifier for guest session', function (): void {
        $service = app(CartMigrationService::class);
        $sessionId = session()->getId();
        $identifier = $service->getGuestIdentifier($sessionId);

        expect($identifier)->toBe($sessionId);
    });

    it('gets current identifier based on auth state', function (): void {
        $service = app(CartMigrationService::class);
        $identifier = $service->getCurrentIdentifier();

        expect($identifier)->not->toBeEmpty();
    });
});

describe('CartMigrationService Swapping', function (): void {
    beforeEach(function (): void {
        Cart::clear();
    });

    it('swaps cart from one identifier to another', function (): void {
        // Add items to guest cart
        Cart::add('item-1', 'Item 1', 10.00, 1);

        $oldIdentifier = session()->getId();
        $newIdentifier = 'user-42';

        $service = app(CartMigrationService::class);
        $result = $service->swap($oldIdentifier, $newIdentifier, 'default');

        expect($result)->toBeTrue();
    });

    it('swaps guest cart to user on login', function (): void {
        Cart::add('guest-item', 'Guest Item', 25.00, 2);

        $service = app(CartMigrationService::class);
        $result = $service->swapGuestCartToUser(42, 'default');

        expect($result)->toBeTrue();
    });
});

describe('CartMigrationService Migration', function (): void {
    beforeEach(function (): void {
        Cart::clear();
    });

    it('migrates guest cart to user cart', function (): void {
        $sessionId = session()->getId();

        // Add items as guest
        Cart::add('item', 'Item', 50.00, 1);

        $service = app(CartMigrationService::class);
        $result = $service->migrateGuestCartToUser(42, 'default', $sessionId);

        expect($result)->toBeTrue();
    });

    it('handles empty guest cart migration', function (): void {
        $sessionId = session()->getId();

        $service = app(CartMigrationService::class);
        $result = $service->migrateGuestCartToUser(42, 'default', $sessionId);

        expect($result)->toBeFalse(); // No items to migrate
    });
});

describe('CartMigrationService Scalar User Keys', function (): void {
    beforeEach(function (): void {
        Cart::clear();
        Auth::guard()->forgetUser();
    });

    afterEach(function (): void {
        Auth::guard()->forgetUser();
    });

    it('preserves a UUID user key byte-for-byte', function (): void {
        $service = app(CartMigrationService::class);
        $uuid = '019a1234-5b6c-7d8e-9f01-23456789abcd';

        expect($service->getUserIdentifier($uuid))->toBe($uuid);
    });

    it('keeps distinct UUIDs with the same prefix distinct', function (): void {
        $service = app(CartMigrationService::class);

        $first = '019a1234-5b6c-7d8e-9f01-23456789abcd';
        $second = '019a1234-9999-0000-8888-ffffffffffff';

        expect($service->getUserIdentifier($first))->toBe($first)
            ->and($service->getUserIdentifier($second))->toBe($second)
            ->and($service->getUserIdentifier($first))->not->toBe('19');
    });

    it('preserves a UUID beginning with letters', function (): void {
        $service = app(CartMigrationService::class);
        $uuid = 'a1b2c3d4-e5f6-7890-abcd-ef0123456789';

        expect($service->getUserIdentifier($uuid))->toBe($uuid);
    });

    it('treats zero as a valid scalar user key', function (): void {
        $service = app(CartMigrationService::class);

        expect($service->getUserIdentifier(0))->toBe('0')
            ->and($service->getIdentifier(0))->toBe('0');
    });

    it('resolves the full UUID of the authenticated user as the current identifier', function (): void {
        $service = app(CartMigrationService::class);
        $uuid = '019a1234-5b6c-7d8e-9f01-23456789abcd';

        $user = new User;
        $user->setAttribute('id', $uuid);

        Auth::guard()->setUser($user);

        expect($service->getCurrentIdentifier())->toBe($uuid);
    });

    it('fails clearly when the authenticated user has no ID', function (): void {
        $service = app(CartMigrationService::class);

        Auth::guard()->setUser(new class implements Authenticatable
        {
            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return null;
            }

            public function getAuthPasswordName()
            {
                return 'password';
            }

            public function getAuthPassword()
            {
                return '';
            }

            public function getRememberToken()
            {
                return null;
            }

            public function setRememberToken($value) {}

            public function getRememberTokenName()
            {
                return null;
            }
        });

        expect(fn () => $service->getCurrentIdentifier())
            ->toThrow(RuntimeException::class, 'authenticated user has no ID');
    });

    it('resolves the guest identifier from the session when unauthenticated', function (): void {
        $service = app(CartMigrationService::class);

        expect($service->getCurrentIdentifier())->toBe(session()->getId());
    });

    it('keeps guest identifier resolution session-only while authenticated', function (): void {
        $service = app(CartMigrationService::class);

        $user = new User;
        $user->setAttribute('id', '019a1234-5b6c-7d8e-9f01-23456789abcd');

        Auth::guard()->setUser($user);

        expect($service->getGuestIdentifier('guest-session-xyz'))->toBe('guest-session-xyz');
    });
});
