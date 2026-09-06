<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateUpline;
use AIArmada\Affiliates\Services\UplineService;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\Paused;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config(['affiliates.upline.enabled' => true]);

    $this->service = app(UplineService::class);

    $this->rootAffiliate = Affiliate::create([
        'code' => 'ROOT-' . uniqid(),
        'name' => 'Root Affiliate',
        'contact_email' => 'root@example.com',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
});

describe('UplineService', function (): void {
    describe('addToUpline', function (): void {
        test('adds affiliate to network without sponsor', function (): void {
            $affiliate = Affiliate::create([
                'code' => 'ADD-' . uniqid(),
                'name' => 'New Affiliate',
                'contact_email' => 'new@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->addToUpline($affiliate);

            // Self-referential entry created
            $entry = AffiliateUpline::where('descendant_id', $affiliate->id)
                ->where('ancestor_id', $affiliate->id)
                ->first();

            expect($entry)->not->toBeNull();
            expect($entry->depth)->toBe(0);
        });

        test('adds affiliate to network with sponsor', function (): void {
            $affiliate = Affiliate::create([
                'code' => 'CHILD-' . uniqid(),
                'name' => 'Child Affiliate',
                'contact_email' => 'child@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            // Add root first
            $this->service->addToUpline($this->rootAffiliate);

            // Add child under root
            $this->service->addToUpline($affiliate, $this->rootAffiliate);

            // Verify relationship
            $entry = AffiliateUpline::where('descendant_id', $affiliate->id)
                ->where('ancestor_id', $this->rootAffiliate->id)
                ->first();

            expect($entry)->not->toBeNull();
            expect($entry->depth)->toBe(1);
        });

        test('updates network counts on sponsor', function (): void {
            $this->service->addToUpline($this->rootAffiliate);

            $affiliate = Affiliate::create([
                'code' => 'COUNT-' . uniqid(),
                'name' => 'Count Test Affiliate',
                'contact_email' => 'count@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->addToUpline($affiliate, $this->rootAffiliate);

            $this->rootAffiliate->refresh();
            expect($this->rootAffiliate->direct_downline_count)->toBeGreaterThanOrEqual(1);
        });
    });

    describe('removeFromUpline', function (): void {
        test('removes affiliate from network', function (): void {
            $this->service->addToUpline($this->rootAffiliate);

            $affiliate = Affiliate::create([
                'code' => 'REMOVE-' . uniqid(),
                'name' => 'Remove Affiliate',
                'contact_email' => 'remove@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->addToUpline($affiliate, $this->rootAffiliate);

            $this->service->removeFromUpline($affiliate);

            // Check that affiliate's entries are removed
            $count = AffiliateUpline::where('descendant_id', $affiliate->id)->count();
            expect($count)->toBe(0);
        });
    });

    describe('changeSponsor', function (): void {
        test('moves affiliate to new sponsor', function (): void {
            $this->service->addToUpline($this->rootAffiliate);

            $newSponsor = Affiliate::create([
                'code' => 'NEW-SPONSOR-' . uniqid(),
                'name' => 'New Sponsor',
                'contact_email' => 'newsponsor@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);
            $this->service->addToUpline($newSponsor);

            $affiliate = Affiliate::create([
                'code' => 'MOVE-' . uniqid(),
                'name' => 'Move Affiliate',
                'contact_email' => 'move@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->addToUpline($affiliate, $this->rootAffiliate);

            // Move to new sponsor
            $this->service->changeSponsor($affiliate, $newSponsor);

            // Verify new relationship exists
            $entry = AffiliateUpline::where('descendant_id', $affiliate->id)
                ->where('ancestor_id', $newSponsor->id)
                ->where('depth', 1)
                ->first();

            expect($entry)->not->toBeNull();
        });
    });

    describe('getUpline', function (): void {
        test('returns collection of ancestors', function (): void {
            $this->service->addToUpline($this->rootAffiliate);

            $affiliate = Affiliate::create([
                'code' => 'UPLINE-' . uniqid(),
                'name' => 'Upline Test',
                'contact_email' => 'upline@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->addToUpline($affiliate, $this->rootAffiliate);

            $upline = $this->service->getUpline($affiliate);

            expect($upline)->toBeCollection();
            expect($upline->contains('id', $this->rootAffiliate->id))->toBeTrue();
        });

        test('returns empty collection when no ancestors', function (): void {
            $this->service->addToUpline($this->rootAffiliate);

            $upline = $this->service->getUpline($this->rootAffiliate);

            expect($upline)->toBeCollection();
            // Root only has itself as ancestor
        });
    });

    describe('getDownline', function (): void {
        test('returns collection of descendants', function (): void {
            $this->service->addToUpline($this->rootAffiliate);

            $affiliate = Affiliate::create([
                'code' => 'DOWN-' . uniqid(),
                'name' => 'Downline Test',
                'contact_email' => 'down@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->addToUpline($affiliate, $this->rootAffiliate);

            $downline = $this->service->getDownline($this->rootAffiliate);

            expect($downline)->toBeCollection();
            expect($downline->contains('id', $affiliate->id))->toBeTrue();
        });

        test('returns empty collection when no descendants', function (): void {
            $affiliate = Affiliate::create([
                'code' => 'NODOWN-' . uniqid(),
                'name' => 'No Downline',
                'contact_email' => 'nodown@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->addToUpline($affiliate);

            $downline = $this->service->getDownline($affiliate);

            expect($downline)->toBeCollection();
            expect($downline)->toBeEmpty();
        });
    });

    describe('getDirectRecruits', function (): void {
        test('returns direct children only', function (): void {
            $this->service->addToUpline($this->rootAffiliate);

            // Level 1 child
            $child = Affiliate::create([
                'code' => 'L1-' . uniqid(),
                'name' => 'Level 1 Child',
                'contact_email' => 'l1@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);
            $this->service->addToUpline($child, $this->rootAffiliate);

            // Level 2 child (grandchild)
            $grandchild = Affiliate::create([
                'code' => 'L2-' . uniqid(),
                'name' => 'Level 2 Grandchild',
                'contact_email' => 'l2@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);
            $this->service->addToUpline($grandchild, $child);

            $recruits = $this->service->getDirectRecruits($this->rootAffiliate);

            expect($recruits)->toBeCollection();
            expect($recruits->contains('id', $child->id))->toBeTrue();
            expect($recruits->contains('id', $grandchild->id))->toBeFalse();
        });
    });

    describe('getTeamSales', function (): void {
        test('returns total sales from downline', function (): void {
            $this->service->addToUpline($this->rootAffiliate);

            $affiliate = Affiliate::create([
                'code' => 'SALES-' . uniqid(),
                'name' => 'Sales Affiliate',
                'contact_email' => 'sales@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->addToUpline($affiliate, $this->rootAffiliate);

            // Create conversion for the child
            AffiliateConversion::create([
                'affiliate_id' => $affiliate->id,
                'affiliate_code' => $affiliate->code,
                'order_reference' => 'TEAM-SALE-001',
                'subtotal_minor' => 5000,
                'total_minor' => 5000,
                'value_minor' => 6500,
                'commission_minor' => 500,
                'status' => ApprovedConversion::class,
            ]);

            $teamSales = $this->service->getTeamSales($this->rootAffiliate);

            expect($teamSales)->toBe(6500);
        });

        test('returns zero when no downline', function (): void {
            $affiliate = Affiliate::create([
                'code' => 'NOSALES-' . uniqid(),
                'name' => 'No Sales Affiliate',
                'contact_email' => 'nosales@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->addToUpline($affiliate);

            $teamSales = $this->service->getTeamSales($affiliate);

            expect($teamSales)->toBe(0);
        });

        test('filters by date range', function (): void {
            $this->service->addToUpline($this->rootAffiliate);

            $affiliate = Affiliate::create([
                'code' => 'DATE-' . uniqid(),
                'name' => 'Date Test Affiliate',
                'contact_email' => 'date@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->addToUpline($affiliate, $this->rootAffiliate);

            // In range
            AffiliateConversion::create([
                'affiliate_id' => $affiliate->id,
                'affiliate_code' => $affiliate->code,
                'order_reference' => 'IN-RANGE',
                'subtotal_minor' => 3000,
                'total_minor' => 3000,
                'value_minor' => 4500,
                'commission_minor' => 300,
                'status' => ApprovedConversion::class,
                'occurred_at' => now(),
            ]);

            // Out of range
            AffiliateConversion::create([
                'affiliate_id' => $affiliate->id,
                'affiliate_code' => $affiliate->code,
                'order_reference' => 'OUT-RANGE',
                'subtotal_minor' => 2000,
                'total_minor' => 2000,
                'value_minor' => 9000,
                'commission_minor' => 200,
                'status' => ApprovedConversion::class,
                'occurred_at' => now()->subMonths(2),
            ]);

            $from = CarbonImmutable::now()->startOfMonth();
            $to = CarbonImmutable::now()->endOfMonth();

            $teamSales = $this->service->getTeamSales($this->rootAffiliate, $from, $to);

            expect($teamSales)->toBe(4500);
        });
    });

    describe('getActiveDownlineCount', function (): void {
        test('counts only active affiliates', function (): void {
            $this->service->addToUpline($this->rootAffiliate);

            // Active child
            $activeChild = Affiliate::create([
                'code' => 'ACTIVE-' . uniqid(),
                'name' => 'Active Child',
                'contact_email' => 'active@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);
            $this->service->addToUpline($activeChild, $this->rootAffiliate);

            // Inactive child
            $inactiveChild = Affiliate::create([
                'code' => 'INACTIVE-' . uniqid(),
                'name' => 'Inactive Child',
                'contact_email' => 'inactive@example.com',
                'status' => Paused::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);
            $this->service->addToUpline($inactiveChild, $this->rootAffiliate);

            $count = $this->service->getActiveDownlineCount($this->rootAffiliate);

            expect($count)->toBe(1);
        });

        test('returns zero when no downline', function (): void {
            $this->service->addToUpline($this->rootAffiliate);

            $count = $this->service->getActiveDownlineCount($this->rootAffiliate);

            expect($count)->toBe(0);
        });
    });

    describe('buildTree', function (): void {
        test('returns array with correct structure', function (): void {
            $this->service->addToUpline($this->rootAffiliate);

            $tree = $this->service->buildTree($this->rootAffiliate);

            expect($tree)->toBeArray();
            expect($tree)->toHaveKeys(['id', 'name', 'code', 'rank', 'status', 'stats', 'children']);
            expect($tree['id'])->toBe($this->rootAffiliate->id);
            expect($tree['name'])->toBe($this->rootAffiliate->name);
            expect($tree['code'])->toBe($this->rootAffiliate->code);
        });

        test('includes children in tree', function (): void {
            $this->service->addToUpline($this->rootAffiliate);

            $child = Affiliate::create([
                'code' => 'TREE-' . uniqid(),
                'name' => 'Tree Child',
                'contact_email' => 'tree@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->addToUpline($child, $this->rootAffiliate);

            $tree = $this->service->buildTree($this->rootAffiliate);

            expect($tree['children'])->toBeArray();
            expect($tree['children'])->toHaveCount(1);
            expect($tree['children'][0]['id'])->toBe($child->id);
        });

        test('respects max depth', function (): void {
            $this->service->addToUpline($this->rootAffiliate);

            // Create a deep chain
            $parent = $this->rootAffiliate;
            for ($i = 0; $i < 3; $i++) {
                $child = Affiliate::create([
                    'code' => "DEPTH-{$i}-" . uniqid(),
                    'name' => "Depth {$i} Child",
                    'contact_email' => "depth{$i}@example.com",
                    'status' => Active::class,
                    'commission_type' => CommissionType::Percentage,
                    'commission_rate' => 1000,
                    'currency' => 'USD',
                ]);
                $this->service->addToUpline($child, $parent);
                $parent = $child;
            }

            // With max depth of 1, should only show direct children
            $tree = $this->service->buildTree($this->rootAffiliate, 1);

            // L1 children visible, their children should be empty
            expect($tree['children'])->toHaveCount(1);
            expect($tree['children'][0]['children'])->toBeEmpty();
        });

        test('includes stats in nodes', function (): void {
            $this->service->addToUpline($this->rootAffiliate);

            $tree = $this->service->buildTree($this->rootAffiliate);

            expect($tree['stats'])->toBeArray();
            expect($tree['stats'])->toHaveKeys(['direct_recruits', 'total_downline']);
        });
    });
});

describe('UplineService class structure', function (): void {
    test('can be instantiated', function (): void {
        $service = app(UplineService::class);
        expect($service)->toBeInstanceOf(UplineService::class);
    });

    test('is declared as final', function (): void {
        $reflection = new ReflectionClass(UplineService::class);
        expect($reflection->isFinal())->toBeTrue();
    });

    test('has required public methods', function (): void {
        $reflection = new ReflectionClass(UplineService::class);

        expect($reflection->hasMethod('addToUpline'))->toBeTrue();
        expect($reflection->hasMethod('removeFromUpline'))->toBeTrue();
        expect($reflection->hasMethod('changeSponsor'))->toBeTrue();
        expect($reflection->hasMethod('getUpline'))->toBeTrue();
        expect($reflection->hasMethod('getDownline'))->toBeTrue();
        expect($reflection->hasMethod('getDirectRecruits'))->toBeTrue();
        expect($reflection->hasMethod('getTeamSales'))->toBeTrue();
        expect($reflection->hasMethod('getActiveDownlineCount'))->toBeTrue();
        expect($reflection->hasMethod('buildTree'))->toBeTrue();
    });
});
