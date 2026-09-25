<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Adapters\Affiliates\AffiliatesIdentityReader;
use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Support\UserKeyAffiliateIdentityResolver;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;

describe('UserKeyAffiliateIdentityResolver', function (): void {
    test('find resolves a user by key with user coordinates', function (): void {
        $user = User::factory()->create();
        $affiliate = (new UserKeyAffiliateIdentityResolver)->find((string) $user->getKey());

        expect($affiliate)->not->toBeNull()
            ->and($affiliate->id)->toBe((string) $user->getKey())
            ->and($affiliate->code)->toBe((string) $user->getKey())
            ->and($affiliate->email)->toBe($user->email)
            ->and($affiliate->ownerType)->toBe($user->getMorphClass())
            ->and((string) $affiliate->ownerId)->toBe((string) $user->getKey())
            ->and($affiliate->owner()->getKey())->toBe($user->getKey());
    });

    test('find returns null for unknown keys', function (): void {
        expect((new UserKeyAffiliateIdentityResolver)->find('ghost'))->toBeNull();
    });

    test('findAccessible allows the scoped user and global scope', function (): void {
        $user = User::factory()->create();
        $resolver = new UserKeyAffiliateIdentityResolver;

        $scoped = OwnerContext::withOwner($user, fn () => $resolver->findAccessible((string) $user->getKey()));
        $global = OwnerContext::withOwner(null, fn () => $resolver->findAccessible((string) $user->getKey()));

        expect($scoped?->id)->toBe((string) $user->getKey())
            ->and($global?->id)->toBe((string) $user->getKey());
    });

    test('findAccessible rejects other users under a scope', function (): void {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $result = OwnerContext::withOwner($user, fn () => (new UserKeyAffiliateIdentityResolver)->findAccessible((string) $other->getKey()));

        expect($result)->toBeNull();
    });

    test('findIdForVerifiedEmail resolves by email', function (): void {
        $user = User::factory()->create(['email' => 'creator@example.com']);

        expect((new UserKeyAffiliateIdentityResolver)->findIdForVerifiedEmail('creator@example.com'))
            ->toBe((string) $user->getKey())
            ->and((new UserKeyAffiliateIdentityResolver)->findIdForVerifiedEmail('ghost@example.com'))->toBeNull();
    });

    test('engine adapter still wins the container binding when installed', function (): void {
        expect(app(AffiliateIdentityResolver::class))->toBeInstanceOf(AffiliatesIdentityReader::class);
    });
});
