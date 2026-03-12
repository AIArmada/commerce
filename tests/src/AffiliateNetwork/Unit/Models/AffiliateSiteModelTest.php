<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Carbon\CarbonImmutable;

describe('AffiliateSite Model', function (): void {
    describe('basic operations', function (): void {
        test('can create site', function (): void {
            $site = AffiliateSite::factory()->create([
                'name' => 'Test Network',
                'domain' => 'test-network.com',
            ]);

            expect($site->id)->not->toBeEmpty();
            expect($site->name)->toBe('Test Network');
            expect($site->domain)->toBe('test-network.com');
        });

        test('uses uuid primary key', function (): void {
            $site = AffiliateSite::factory()->create();

            expect($site->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
        });

        test('table name comes from config', function (): void {
            $site = new AffiliateSite;

            expect($site->getTable())->toBe('affiliate_network_sites');
        });
    });

    describe('statuses', function (): void {
        test('isVerified returns true for verified sites', function (): void {
            $site = AffiliateSite::factory()->verified()->create();

            expect($site->isVerified())->toBeTrue();
        });

        test('isVerified returns false for pending sites', function (): void {
            $site = AffiliateSite::factory()->pending()->create();

            expect($site->isVerified())->toBeFalse();
        });

        test('isVerified returns false when verified_at is null', function (): void {
            $site = AffiliateSite::factory()->create([
                'status' => AffiliateSite::STATUS_VERIFIED,
                'verified_at' => null,
            ]);

            expect($site->isVerified())->toBeFalse();
        });

        test('isPending returns true for pending sites', function (): void {
            $site = AffiliateSite::factory()->pending()->create();

            expect($site->isPending())->toBeTrue();
        });

        test('isPending returns false for verified sites', function (): void {
            $site = AffiliateSite::factory()->verified()->create();

            expect($site->isPending())->toBeFalse();
        });
    });

    describe('relationships', function (): void {
        test('has many offers', function (): void {
            $site = AffiliateSite::factory()->verified()->create();
            AffiliateOffer::factory()->count(3)->forSite($site)->create();

            expect($site->offers)->toHaveCount(3);
        });

        test('has morphable owner', function (): void {
            $user = User::factory()->create();
            $site = AffiliateSite::factory()->forOwner($user)->create();

            expect($site->owner)->toBeInstanceOf(User::class);
            expect($site->owner->id)->toBe($user->id);
        });
    });

    describe('cascading deletes', function (): void {
        test('deleting site deletes offers', function (): void {
            $site = AffiliateSite::factory()->verified()->create();
            $offers = AffiliateOffer::factory()->count(3)->forSite($site)->create();

            $site->delete();

            expect(AffiliateOffer::whereIn('id', $offers->pluck('id'))->count())->toBe(0);
        });
    });

    describe('casts', function (): void {
        test('settings is cast to array', function (): void {
            $site = AffiliateSite::factory()->create([
                'settings' => ['key' => 'value'],
            ]);

            expect($site->settings)->toBe(['key' => 'value']);
        });

        test('metadata is cast to array', function (): void {
            $site = AffiliateSite::factory()->create([
                'metadata' => ['extra' => 'data'],
            ]);

            expect($site->metadata)->toBe(['extra' => 'data']);
        });

        test('verified_at is immutable datetime', function (): void {
            $site = AffiliateSite::factory()->verified()->create();

            expect($site->verified_at)->toBeInstanceOf(CarbonImmutable::class);
        });
    });

    describe('owner scoping', function (): void {
        test('forOwner scope filters by owner when enabled', function (): void {
            config(['affiliate-network.owner.enabled' => true]);

            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            $site1 = AffiliateSite::factory()->forOwner($user1)->create();
            AffiliateSite::factory()->forOwner($user2)->create();

            $results = AffiliateSite::forOwner($user1)->get();

            expect($results)->toHaveCount(1);
            expect($results->first()->id)->toBe($site1->id);
        });

        test('forOwner scope returns all when disabled', function (): void {
            config(['affiliate-network.owner.enabled' => false]);

            AffiliateSite::factory()->count(3)->create();

            $results = AffiliateSite::forOwner()->get();

            expect($results)->toHaveCount(3);
        });
    });
});
