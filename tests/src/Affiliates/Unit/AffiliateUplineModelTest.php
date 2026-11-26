<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Upline\Api\UplineApi;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateUpline;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('AffiliateUpline Model', function (): void {
    beforeEach(function (): void {
        config(['affiliates.upline.enabled' => true]);
        $this->rootAffiliate = Affiliate::create([
            'code' => 'ROOT-001',
            'name' => 'Root Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        $this->childAffiliate = Affiliate::create([
            'code' => 'CHILD-001',
            'name' => 'Child Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
            'parent_affiliate_id' => $this->rootAffiliate->id,
        ]);

        $this->grandchildAffiliate = Affiliate::create([
            'code' => 'GRANDCHILD-001',
            'name' => 'Grandchild Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
            'parent_affiliate_id' => $this->childAffiliate->id,
        ]);
    });

    it('can be created with required fields', function (): void {
        $network = AffiliateUpline::create([
            'ancestor_id' => $this->rootAffiliate->id,
            'descendant_id' => $this->rootAffiliate->id,
            'depth' => 0,
        ]);

        expect($network)->toBeInstanceOf(AffiliateUpline::class)
            ->and($network->ancestor_id)->toBe($this->rootAffiliate->id)
            ->and($network->descendant_id)->toBe($this->rootAffiliate->id)
            ->and($network->depth)->toBe(0);
    });

    it('uses a uuid primary key', function (): void {
        $network = new AffiliateUpline;

        expect($network->incrementing)->toBeFalse()
            ->and($network->getKeyName())->toBe('id');
    });

    it('does not use timestamps', function (): void {
        $network = new AffiliateUpline;

        expect($network->timestamps)->toBeFalse();
    });

    it('resolves the ancestor through the upline API', function (): void {
        $network = AffiliateUpline::create([
            'ancestor_id' => $this->rootAffiliate->id,
            'descendant_id' => $this->childAffiliate->id,
            'depth' => 1,
        ]);

        expect(app(UplineApi::class)->ancestors($network->descendant_id)[0]->id)->toBe($this->rootAffiliate->id);
    });

    it('resolves the descendant through the upline API', function (): void {
        $network = AffiliateUpline::create([
            'ancestor_id' => $this->rootAffiliate->id,
            'descendant_id' => $this->childAffiliate->id,
            'depth' => 1,
        ]);

        expect(app(UplineApi::class)->descendants($network->ancestor_id)[0]->id)->toBe($this->childAffiliate->id);
    });

    it('casts depth as integer', function (): void {
        $network = AffiliateUpline::create([
            'ancestor_id' => $this->rootAffiliate->id,
            'descendant_id' => $this->childAffiliate->id,
            'depth' => '3',
        ]);

        expect($network->depth)->toBeInt()
            ->and($network->depth)->toBe(3);
    });

    it('can add affiliate to network without sponsor', function (): void {
        $affiliate = Affiliate::create([
            'code' => 'SOLO-001',
            'name' => 'Solo Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
        ]);

        app(UplineApi::class)->add((string) $affiliate->getKey());

        $selfReference = AffiliateUpline::query()
            ->where('ancestor_id', $affiliate->id)
            ->where('descendant_id', $affiliate->id)
            ->first();

        expect($selfReference)->not->toBeNull()
            ->and($selfReference->depth)->toBe(0);
    });

    it('can add affiliate to network with sponsor', function (): void {
        // Add root to network first
        app(UplineApi::class)->add((string) $this->rootAffiliate->getKey());

        $newAffiliate = Affiliate::create([
            'code' => 'NEW-001',
            'name' => 'New Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'USD',
            'parent_affiliate_id' => $this->rootAffiliate->id,
        ]);

        app(UplineApi::class)->add((string) $newAffiliate->getKey(), (string) $this->rootAffiliate->getKey());

        // Should have self-reference
        $selfReference = AffiliateUpline::query()
            ->where('ancestor_id', $newAffiliate->id)
            ->where('descendant_id', $newAffiliate->id)
            ->first();

        expect($selfReference)->not->toBeNull()
            ->and($selfReference->depth)->toBe(0);

        // Should have path from root
        $pathFromRoot = AffiliateUpline::query()
            ->where('ancestor_id', $this->rootAffiliate->id)
            ->where('descendant_id', $newAffiliate->id)
            ->first();

        expect($pathFromRoot)->not->toBeNull()
            ->and($pathFromRoot->depth)->toBe(1);
    });

    it('can get ancestors of affiliate', function (): void {
        // Set up network structure
        app(UplineApi::class)->add((string) $this->rootAffiliate->getKey());
        app(UplineApi::class)->add((string) $this->childAffiliate->getKey(), (string) $this->rootAffiliate->getKey());
        app(UplineApi::class)->add((string) $this->grandchildAffiliate->getKey(), (string) $this->childAffiliate->getKey());

        $ancestors = app(UplineApi::class)->ancestors((string) $this->grandchildAffiliate->getKey());

        expect($ancestors)->toHaveCount(2)
            ->and(array_column($ancestors, 'id'))->toContain($this->rootAffiliate->id)
            ->and(array_column($ancestors, 'id'))->toContain($this->childAffiliate->id);
    });

    it('can get descendants of affiliate', function (): void {
        // Set up network structure
        app(UplineApi::class)->add((string) $this->rootAffiliate->getKey());
        app(UplineApi::class)->add((string) $this->childAffiliate->getKey(), (string) $this->rootAffiliate->getKey());
        app(UplineApi::class)->add((string) $this->grandchildAffiliate->getKey(), (string) $this->childAffiliate->getKey());

        $descendants = app(UplineApi::class)->descendants((string) $this->rootAffiliate->getKey());

        expect($descendants)->toHaveCount(2)
            ->and(array_column($descendants, 'id'))->toContain($this->childAffiliate->id)
            ->and(array_column($descendants, 'id'))->toContain($this->grandchildAffiliate->id);
    });

    it('can get affiliates at specific depth', function (): void {
        // Set up network structure
        app(UplineApi::class)->add((string) $this->rootAffiliate->getKey());
        app(UplineApi::class)->add((string) $this->childAffiliate->getKey(), (string) $this->rootAffiliate->getKey());
        app(UplineApi::class)->add((string) $this->grandchildAffiliate->getKey(), (string) $this->childAffiliate->getKey());

        $atDepth1 = app(UplineApi::class)->descendants((string) $this->rootAffiliate->getKey(), 1);
        $atDepth2 = app(UplineApi::class)->descendants((string) $this->rootAffiliate->getKey(), 2);

        expect($atDepth1)->toHaveCount(1)
            ->and($atDepth1[0]->id)->toBe($this->childAffiliate->id)
            ->and($atDepth2)->toHaveCount(1)
            ->and($atDepth2[0]->id)->toBe($this->grandchildAffiliate->id);
    });

    it('can get direct children', function (): void {
        // Set up network structure
        app(UplineApi::class)->add((string) $this->rootAffiliate->getKey());
        app(UplineApi::class)->add((string) $this->childAffiliate->getKey(), (string) $this->rootAffiliate->getKey());
        app(UplineApi::class)->add((string) $this->grandchildAffiliate->getKey(), (string) $this->childAffiliate->getKey());

        $directChildren = app(UplineApi::class)->descendants((string) $this->rootAffiliate->getKey(), 1);

        expect($directChildren)->toHaveCount(1)
            ->and($directChildren[0]->id)->toBe($this->childAffiliate->id);
    });

    it('can get descendant count', function (): void {
        // Set up network structure
        app(UplineApi::class)->add((string) $this->rootAffiliate->getKey());
        app(UplineApi::class)->add((string) $this->childAffiliate->getKey(), (string) $this->rootAffiliate->getKey());
        app(UplineApi::class)->add((string) $this->grandchildAffiliate->getKey(), (string) $this->childAffiliate->getKey());

        $count = app(UplineApi::class)->descendantCount((string) $this->rootAffiliate->getKey());

        expect($count)->toBe(2);
    });

    it('can remove affiliate from network', function (): void {
        // Set up network structure
        app(UplineApi::class)->add((string) $this->rootAffiliate->getKey());
        app(UplineApi::class)->add((string) $this->childAffiliate->getKey(), (string) $this->rootAffiliate->getKey());
        app(UplineApi::class)->add((string) $this->grandchildAffiliate->getKey(), (string) $this->childAffiliate->getKey());

        app(UplineApi::class)->remove((string) $this->childAffiliate->getKey());

        // Child and grandchild paths should be removed
        $childPaths = AffiliateUpline::query()
            ->where('descendant_id', $this->childAffiliate->id)
            ->count();

        $grandchildPaths = AffiliateUpline::query()
            ->where('descendant_id', $this->grandchildAffiliate->id)
            ->count();

        expect($childPaths)->toBe(0)
            ->and($grandchildPaths)->toBe(0);
    });

    it('uses correct table name from config', function (): void {
        $network = new AffiliateUpline;

        expect($network->getTable())->toBe('affiliate_upline');
    });
});
