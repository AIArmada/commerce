<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Shipping\Feature\Policies;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Shipping\Models\ShippingZone;
use AIArmada\Shipping\Policies\ShippingZonePolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

function zonePolicyOwner(string $label): User
{
    return User::query()->create([
        'name' => $label,
        'email' => Str::lower(Str::random(10)) . '@example.com',
        'password' => 'secret',
    ]);
}

function zonePolicyUser(array $permissions): Authenticatable
{
    return new class($permissions) implements Authenticatable
    {
        public function __construct(private array $permissions) {}

        public function getAuthIdentifier(): mixed
        {
            return 'user-123';
        }

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return '';
        }

        public function getAuthPasswordName(): string
        {
            return '';
        }

        public function hasPermissionTo(string $permission): bool
        {
            return in_array($permission, $this->permissions, true);
        }
    };
}

function zonePolicyZone(User $owner, string $code): ShippingZone
{
    return OwnerContext::withOwner($owner, fn (): ShippingZone => ShippingZone::query()->create([
        'name' => 'Zone ' . $code,
        'code' => $code,
        'type' => 'country',
        'countries' => ['MY'],
    ]));
}

describe('ShippingZonePolicy owner boundary', function (): void {
    beforeEach(function (): void {
        config()->set('shipping.features.owner.enabled', true);
        config()->set('shipping.features.owner.include_global', false);
    });

    it('denies viewing a zone owned by another owner', function (): void {
        $ownerA = zonePolicyOwner('Zone Owner A');
        $ownerB = zonePolicyOwner('Zone Owner B');
        $zoneB = zonePolicyZone($ownerB, 'ZB-' . Str::upper(Str::random(6)));

        $policy = new ShippingZonePolicy;
        $user = zonePolicyUser(['shipping.zones.view']);

        $denied = OwnerContext::withOwner($ownerA, fn (): bool => $policy->view($user, $zoneB));
        $allowed = OwnerContext::withOwner($ownerB, fn (): bool => $policy->view($user, $zoneB));

        expect($denied)->toBeFalse()
            ->and($allowed)->toBeTrue();
    });

    it('denies updating and deleting a zone owned by another owner', function (): void {
        $ownerA = zonePolicyOwner('Zone Owner A');
        $ownerB = zonePolicyOwner('Zone Owner B');
        $zoneB = zonePolicyZone($ownerB, 'ZB-' . Str::upper(Str::random(6)));

        $policy = new ShippingZonePolicy;

        $results = OwnerContext::withOwner($ownerA, function () use ($policy, $zoneB): array {
            return [
                $policy->update(zonePolicyUser(['shipping.zones.update']), $zoneB),
                $policy->delete(zonePolicyUser(['shipping.zones.delete']), $zoneB),
                $policy->manageRates(zonePolicyUser(['shipping.zones.manage-rates']), $zoneB),
            ];
        });

        expect($results)->toBe([false, false, false]);
    });

    it('allows global zones when include global is enabled', function (): void {
        config()->set('shipping.features.owner.include_global', true);

        $owner = zonePolicyOwner('Zone Owner');
        $globalZone = OwnerContext::withOwner(null, fn (): ShippingZone => ShippingZone::query()->create([
            'name' => 'Global Zone',
            'code' => 'ZG-' . Str::upper(Str::random(6)),
            'type' => 'country',
            'countries' => ['MY'],
        ]));

        $policy = new ShippingZonePolicy;

        $allowed = OwnerContext::withOwner(
            $owner,
            fn (): bool => $policy->view(zonePolicyUser(['shipping.zones.view']), $globalZone)
        );

        expect($allowed)->toBeTrue();
    });
});
