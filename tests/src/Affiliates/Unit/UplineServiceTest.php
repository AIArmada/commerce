<?php

declare(strict_types=1);

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\States\ApprovedConversion;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAdministration;
use AIArmada\Affiliates\Features\Upline\Api\UplineApi;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateUpline;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\Paused;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    config(['affiliates.upline.enabled' => true]);

    $this->service = app(UplineApi::class);

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

describe('UplineApi', function (): void {
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

            $this->service->add((string) $affiliate->getKey());

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
            $this->service->add((string) $this->rootAffiliate->getKey());

            // Add child under root
            $this->service->add((string) $affiliate->getKey(), (string) $this->rootAffiliate->getKey());

            // Verify relationship
            $entry = AffiliateUpline::where('descendant_id', $affiliate->id)
                ->where('ancestor_id', $this->rootAffiliate->id)
                ->first();

            expect($entry)->not->toBeNull();
            expect($entry->depth)->toBe(1);
        });

        test('updates network counts on sponsor', function (): void {
            $this->service->add((string) $this->rootAffiliate->getKey());

            $affiliate = Affiliate::create([
                'code' => 'COUNT-' . uniqid(),
                'name' => 'Count Test Affiliate',
                'contact_email' => 'count@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->add((string) $affiliate->getKey(), (string) $this->rootAffiliate->getKey());

            $this->rootAffiliate->refresh();
            expect($this->rootAffiliate->direct_downline_count)->toBeGreaterThanOrEqual(1);
        });
    });

    describe('removeFromUpline', function (): void {
        test('removes affiliate from network', function (): void {
            $this->service->add((string) $this->rootAffiliate->getKey());

            $affiliate = Affiliate::create([
                'code' => 'REMOVE-' . uniqid(),
                'name' => 'Remove Affiliate',
                'contact_email' => 'remove@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->add((string) $affiliate->getKey(), (string) $this->rootAffiliate->getKey());

            $this->service->remove((string) $affiliate->getKey());

            // Check that affiliate's entries are removed
            $count = AffiliateUpline::where('descendant_id', $affiliate->id)->count();
            expect($count)->toBe(0);
        });
    });

    describe('changeSponsor', function (): void {
        test('moves affiliate to new sponsor', function (): void {
            $this->service->add((string) $this->rootAffiliate->getKey());

            $newSponsor = Affiliate::create([
                'code' => 'NEW-SPONSOR-' . uniqid(),
                'name' => 'New Sponsor',
                'contact_email' => 'newsponsor@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);
            $this->service->add((string) $newSponsor->getKey());

            $affiliate = Affiliate::create([
                'code' => 'MOVE-' . uniqid(),
                'name' => 'Move Affiliate',
                'contact_email' => 'move@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->add((string) $affiliate->getKey(), (string) $this->rootAffiliate->getKey());

            // Move to new sponsor
            $this->service->move((string) $affiliate->getKey(), (string) $newSponsor->getKey());

            // Verify new relationship exists
            $entry = AffiliateUpline::where('descendant_id', $affiliate->id)
                ->where('ancestor_id', $newSponsor->id)
                ->where('depth', 1)
                ->first();

            expect($entry)->not->toBeNull();
        });
    });

    describe('getUpline', function (): void {
        test('returns records of ancestors', function (): void {
            $this->service->add((string) $this->rootAffiliate->getKey());

            $affiliate = Affiliate::create([
                'code' => 'UPLINE-' . uniqid(),
                'name' => 'Upline Test',
                'contact_email' => 'upline@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->add((string) $affiliate->getKey(), (string) $this->rootAffiliate->getKey());

            $upline = $this->service->ancestors((string) $affiliate->getKey());

            expect($upline)->toBeArray();
            expect(in_array($this->rootAffiliate->id, array_column($upline, 'id'), true))->toBeTrue();
        });

        test('returns empty array when no ancestors', function (): void {
            $this->service->add((string) $this->rootAffiliate->getKey());

            $upline = $this->service->ancestors((string) $this->rootAffiliate->getKey());

            expect($upline)->toBeArray();
            // Root only has itself as ancestor
        });
    });

    describe('getDownline', function (): void {
        test('returns collection of descendants', function (): void {
            $this->service->add((string) $this->rootAffiliate->getKey());

            $affiliate = Affiliate::create([
                'code' => 'DOWN-' . uniqid(),
                'name' => 'Downline Test',
                'contact_email' => 'down@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->add((string) $affiliate->getKey(), (string) $this->rootAffiliate->getKey());

            $downline = $this->service->descendants((string) $this->rootAffiliate->getKey());

            expect($downline)->toBeArray();
            expect(in_array($affiliate->id, array_column($downline, 'id'), true))->toBeTrue();
        });

        test('returns empty array when no descendants', function (): void {
            $affiliate = Affiliate::create([
                'code' => 'NODOWN-' . uniqid(),
                'name' => 'No Downline',
                'contact_email' => 'nodown@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->add((string) $affiliate->getKey());

            $downline = $this->service->descendants((string) $affiliate->getKey());

            expect($downline)->toBeArray();
            expect($downline)->toBeEmpty();
        });
    });

    describe('getDirectRecruits', function (): void {
        test('returns direct children only', function (): void {
            $this->service->add((string) $this->rootAffiliate->getKey());

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
            $this->service->add((string) $child->getKey(), (string) $this->rootAffiliate->getKey());

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
            $this->service->add((string) $grandchild->getKey(), (string) $child->getKey());

            $recruits = $this->service->descendants((string) $this->rootAffiliate->getKey(), 1);

            expect($recruits)->toBeArray();
            expect(in_array($child->id, array_column($recruits, 'id'), true))->toBeTrue();
            expect(in_array($grandchild->id, array_column($recruits, 'id'), true))->toBeFalse();
        });
    });

    describe('getTeamSales', function (): void {
        test('returns total sales from downline', function (): void {
            $this->service->add((string) $this->rootAffiliate->getKey());

            $affiliate = Affiliate::create([
                'code' => 'SALES-' . uniqid(),
                'name' => 'Sales Affiliate',
                'contact_email' => 'sales@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->add((string) $affiliate->getKey(), (string) $this->rootAffiliate->getKey());

            // Create conversion for the child
            AffiliateConversion::create([
                'commission_currency' => 'USD',
                'affiliate_id' => $affiliate->id,
                'affiliate_code' => $affiliate->code,
                'order_reference' => 'TEAM-SALE-001',
                'subtotal_minor' => 5000,
                'total_minor' => 5000,
                'value_minor' => 6500,
                'commission_minor' => 500,
                'status' => ApprovedConversion::class,
            ]);

            $teamSales = $this->service->teamSales((string) $this->rootAffiliate->getKey());

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

            $this->service->add((string) $affiliate->getKey());

            $teamSales = $this->service->teamSales((string) $affiliate->getKey());

            expect($teamSales)->toBe(0);
        });

        test('filters by date range', function (): void {
            $this->service->add((string) $this->rootAffiliate->getKey());

            $affiliate = Affiliate::create([
                'code' => 'DATE-' . uniqid(),
                'name' => 'Date Test Affiliate',
                'contact_email' => 'date@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->add((string) $affiliate->getKey(), (string) $this->rootAffiliate->getKey());

            // In range
            AffiliateConversion::create([
                'commission_currency' => 'USD',
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
                'commission_currency' => 'USD',
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

            $teamSales = $this->service->teamSales((string) $this->rootAffiliate->getKey(), $from, $to);

            expect($teamSales)->toBe(4500);
        });
    });

    describe('getActiveDownlineCount', function (): void {
        test('counts only active affiliates', function (): void {
            $this->service->add((string) $this->rootAffiliate->getKey());

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
            $this->service->add((string) $activeChild->getKey(), (string) $this->rootAffiliate->getKey());

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
            $this->service->add((string) $inactiveChild->getKey(), (string) $this->rootAffiliate->getKey());

            $count = $this->service->activeDownlineCount((string) $this->rootAffiliate->getKey());

            expect($count)->toBe(1);
        });

        test('returns zero when no downline', function (): void {
            $this->service->add((string) $this->rootAffiliate->getKey());

            $count = $this->service->activeDownlineCount((string) $this->rootAffiliate->getKey());

            expect($count)->toBe(0);
        });
    });

    test('rejects cycles and keeps ancestor counts and parent pointers consistent when moving a subtree', function (): void {
        $make = static fn (string $name): Affiliate => Affiliate::create([
            'code' => $name . '-' . uniqid(), 'name' => $name,
            'status' => Active::class, 'commission_type' => CommissionType::Percentage,
            'commission_rate' => 1000, 'currency' => 'USD',
        ]);
        $root = $this->rootAffiliate;
        $child = $make('CHILD');
        $grandchild = $make('GRANDCHILD');
        $other = $make('OTHER');
        $this->service->add((string) $root->getKey());
        $this->service->add((string) $child->getKey(), (string) $root->getKey());
        $this->service->add((string) $grandchild->getKey(), (string) $child->getKey());
        $this->service->add((string) $other->getKey());

        expect(fn () => $this->service->move((string) $root->getKey(), (string) $grandchild->getKey()))
            ->toThrow(InvalidArgumentException::class);
        expect($root->fresh()->total_downline_count)->toBe(2);

        $this->service->move((string) $child->getKey(), (string) $other->getKey());
        expect($root->fresh()->total_downline_count)->toBe(0)
            ->and($other->fresh()->total_downline_count)->toBe(2)
            ->and($child->fresh()->parent_affiliate_id)->toBe($other->getKey())
            ->and(array_column($this->service->ancestors((string) $grandchild->getKey()), 'id'))->toBe([$child->getKey(), $other->getKey()]);

        $this->service->remove((string) $child->getKey());
        expect($other->fresh()->total_downline_count)->toBe(0)
            ->and($child->fresh()->parent_affiliate_id)->toBeNull()
            ->and($grandchild->fresh()->parent_affiliate_id)->toBeNull();
    });

    describe('buildTree', function (): void {
        test('returns array with correct structure', function (): void {
            $this->service->add((string) $this->rootAffiliate->getKey());

            $tree = $this->service->tree((string) $this->rootAffiliate->getKey());

            expect($tree)->toBeArray();
            expect($tree)->toHaveKeys(['id', 'name', 'code', 'rank', 'status', 'stats', 'children']);
            expect($tree['id'])->toBe($this->rootAffiliate->id);
            expect($tree['name'])->toBe($this->rootAffiliate->name);
            expect($tree['code'])->toBe($this->rootAffiliate->code);
        });

        test('includes children in tree', function (): void {
            $this->service->add((string) $this->rootAffiliate->getKey());

            $child = Affiliate::create([
                'code' => 'TREE-' . uniqid(),
                'name' => 'Tree Child',
                'contact_email' => 'tree@example.com',
                'status' => Active::class,
                'commission_type' => CommissionType::Percentage,
                'commission_rate' => 1000,
                'currency' => 'USD',
            ]);

            $this->service->add((string) $child->getKey(), (string) $this->rootAffiliate->getKey());

            $tree = $this->service->tree((string) $this->rootAffiliate->getKey());

            expect($tree['children'])->toBeArray();
            expect($tree['children'])->toHaveCount(1);
            expect($tree['children'][0]['id'])->toBe($child->id);
        });

        test('respects max depth', function (): void {
            $this->service->add((string) $this->rootAffiliate->getKey());

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
                $this->service->add((string) $child->getKey(), (string) $parent->getKey());
                $parent = $child;
            }

            // With max depth of 1, should only show direct children
            $tree = $this->service->tree((string) $this->rootAffiliate->getKey(), 1);

            // L1 children visible, their children should be empty
            expect($tree['children'])->toHaveCount(1);
            expect($tree['children'][0]['children'])->toBeEmpty();
        });

        test('includes stats in nodes', function (): void {
            $this->service->add((string) $this->rootAffiliate->getKey());

            $tree = $this->service->tree((string) $this->rootAffiliate->getKey());

            expect($tree['stats'])->toBeArray();
            expect($tree['stats'])->toHaveKeys(['direct_recruits', 'total_downline']);
        });
    });
});

test('administrative parent changes keep the closure tree and counts consistent', function (): void {
    $admin = app(AffiliateAdministration::class);
    $root = $this->rootAffiliate;
    $this->service->add((string) $root->getKey());

    $childId = $admin->create([
        'code' => 'ADMIN-CHILD', 'name' => 'Child', 'status' => Active::class,
        'commission_type' => CommissionType::Percentage, 'commission_rate' => 1000,
        'currency' => 'USD', 'parent_affiliate_id' => $root->id,
    ]);
    $grandchildId = $admin->create([
        'code' => 'ADMIN-GRANDCHILD', 'name' => 'Grandchild', 'status' => Active::class,
        'commission_type' => CommissionType::Percentage, 'commission_rate' => 1000,
        'currency' => 'USD', 'parent_affiliate_id' => $childId,
    ]);

    expect($root->fresh()->total_downline_count)->toBe(2);
    $admin->update($childId, ['parent_affiliate_id' => null]);

    expect(Affiliate::findOrFail($childId)->parent_affiliate_id)->toBeNull()
        ->and(Affiliate::findOrFail($childId)->total_downline_count)->toBe(1)
        ->and(Affiliate::findOrFail($grandchildId)->parent_affiliate_id)->toBe($childId)
        ->and(Affiliate::findOrFail($grandchildId)->network_depth)->toBe(1)
        ->and($root->fresh()->total_downline_count)->toBe(0)
        ->and(AffiliateUpline::query()->where('ancestor_id', $root->id)->where('descendant_id', $grandchildId)->exists())->toBeFalse();
});


test('upline mutations reject malformed sponsors before writing closure rows', function (): void {
    $affiliate = $this->rootAffiliate;

    expect(fn () => $this->service->add((string) $affiliate->getKey(), 'invalid-uuid'))
        ->toThrow(InvalidArgumentException::class);

    expect(AffiliateUpline::query()->where('descendant_id', $affiliate->id)->exists())->toBeFalse()
        ->and($affiliate->fresh()->parent_affiliate_id)->toBeNull();
});

test('upline mutations reject a parent cycle even when closure rows are missing', function (): void {
    $root = $this->rootAffiliate;
    $child = Affiliate::create([
        'code' => 'MISSING-CLOSURE-CHILD', 'name' => 'Child', 'status' => Active::class,
        'commission_type' => CommissionType::Percentage, 'commission_rate' => 1000,
        'currency' => 'USD', 'parent_affiliate_id' => $root->id,
    ]);

    expect(fn () => $this->service->move((string) $root->getKey(), (string) $child->getKey()))
        ->toThrow(InvalidArgumentException::class);

    expect($root->fresh()->parent_affiliate_id)->toBeNull()
        ->and($child->fresh()->parent_affiliate_id)->toBe($root->id)
        ->and(AffiliateUpline::query()->where('descendant_id', $root->id)->exists())->toBeFalse();
});
