<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\FilamentPromotions\Actions\IssuePromotionVouchersAction;
use AIArmada\FilamentPromotions\Actions\IssuePromotionVouchersFromListAction;
use AIArmada\FilamentPromotions\Resources\PromotionResource;
use AIArmada\FilamentPromotions\Resources\PromotionResource\Schemas\PromotionForm;
use AIArmada\FilamentPromotions\Support\CachedPromotionInsights;
use AIArmada\Promotions\Models\Promotion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Unique;

beforeEach(function (): void {
    Cache::flush();
});

afterEach(function (): void {
    Cache::flush();
});

describe('voucher issue count guard', function (): void {
    it('clamps crafted counts into the 1-100 range', function (): void {
        foreach ([IssuePromotionVouchersAction::class, IssuePromotionVouchersFromListAction::class] as $action) {
            expect($action::clampIssueCount(0))->toBe(1)
                ->and($action::clampIssueCount(-50))->toBe(1)
                ->and($action::clampIssueCount(1))->toBe(1)
                ->and($action::clampIssueCount(42))->toBe(42)
                ->and($action::clampIssueCount(100))->toBe(100)
                ->and($action::clampIssueCount(101))->toBe(100)
                ->and($action::clampIssueCount(5000))->toBe(100)
                ->and($action::clampIssueCount('7'))->toBe(7);
        }
    });
});

describe('promotion select options', function (): void {
    it('caps options instead of hydrating the whole table', function (): void {
        Promotion::factory()->count(205)->create([
            'code' => null,
        ]);

        $method = new ReflectionMethod(IssuePromotionVouchersFromListAction::class, 'promotionOptions');
        $options = $method->invoke(null);

        expect($options)->toBeArray()->toHaveCount(200);
    });

    it('excludes promotions from other owners', function (): void {
        config()->set('promotions.features.owner.enabled', true);
        config()->set('promotions.features.owner.include_global', false);

        $ownerA = User::query()->create(['name' => 'Options A', 'email' => 'options-a@example.com', 'password' => 'secret']);
        $ownerB = User::query()->create(['name' => 'Options B', 'email' => 'options-b@example.com', 'password' => 'secret']);

        OwnerContext::withOwner($ownerB, static fn (): Promotion => Promotion::factory()->create([
            'name' => 'Foreign Promotion',
            'code' => null,
        ]));

        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

        $method = new ReflectionMethod(IssuePromotionVouchersFromListAction::class, 'promotionOptions');
        $options = OwnerContext::withOwner($ownerA, static fn (): array => $method->invoke(null));

        expect($options)->toBeArray()->toBeEmpty();
    });
});

describe('promo code uniqueness scope', function (): void {
    it('rejects duplicate codes within the same owner', function (): void {
        config()->set('promotions.features.owner.enabled', true);
        config()->set('promotions.features.owner.include_global', false);

        $owner = User::query()->create(['name' => 'Unique A', 'email' => 'unique-a@example.com', 'password' => 'secret']);
        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

        OwnerContext::withOwner($owner, static fn (): Promotion => Promotion::factory()->create([
            'name' => 'Owned Promotion',
            'code' => 'SHARED',
        ]));

        $rule = PromotionForm::scopeCodeUniqueRule(new Unique((new Promotion)->getTable(), 'code'));

        expect(Validator::make(['code' => 'SHARED'], ['code' => $rule])->fails())->toBeTrue();
    });

    it('allows code reuse across owners', function (): void {
        config()->set('promotions.features.owner.enabled', true);
        config()->set('promotions.features.owner.include_global', false);

        $ownerA = User::query()->create(['name' => 'Unique B', 'email' => 'unique-b@example.com', 'password' => 'secret']);
        $ownerB = User::query()->create(['name' => 'Unique C', 'email' => 'unique-c@example.com', 'password' => 'secret']);

        OwnerContext::withOwner($ownerA, static fn (): Promotion => Promotion::factory()->create([
            'name' => 'Owner A Promotion',
            'code' => 'REUSABLE',
        ]));

        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));

        $rule = OwnerContext::withOwner($ownerB, static fn (): Unique => PromotionForm::scopeCodeUniqueRule(
            new Unique((new Promotion)->getTable(), 'code')
        ));

        expect(Validator::make(['code' => 'REUSABLE'], ['code' => $rule])->passes())->toBeTrue();
    });

    it('stays global when owner scoping is disabled', function (): void {
        config()->set('promotions.features.owner.enabled', false);

        Promotion::factory()->create(['name' => 'Global Promotion', 'code' => 'GLOBAL']);

        $rule = PromotionForm::scopeCodeUniqueRule(new Unique((new Promotion)->getTable(), 'code'));

        expect(Validator::make(['code' => 'GLOBAL'], ['code' => $rule])->fails())->toBeTrue();
    });
});

describe('navigation badge cache', function (): void {
    it('serves repeated renders without extra queries', function (): void {
        Promotion::factory()->active()->count(3)->create();

        DB::enableQueryLog();
        DB::flushQueryLog();
        expect(PromotionResource::getNavigationBadge())->toBe('3');
        $firstPass = count(DB::getQueryLog());

        DB::flushQueryLog();
        expect(PromotionResource::getNavigationBadge())->toBe('3');
        $secondPass = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($firstPass)->toBe(1)->and($secondPass)->toBe(0);
    });
});

describe('cached promotion insights', function (): void {
    it('serves repeated overview reads without extra queries', function (): void {
        Promotion::factory()->active()->count(2)->create();

        $insights = app(CachedPromotionInsights::class);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $first = $insights->overview();
        $firstPass = count(DB::getQueryLog());

        DB::flushQueryLog();
        $second = $insights->overview();
        $secondPass = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($firstPass)->toBeGreaterThan(0)
            ->and($secondPass)->toBe(0)
            ->and($second)->toBe($first);
    });

    it('isolates cached insights per owner', function (): void {
        config()->set('promotions.features.owner.enabled', true);
        config()->set('promotions.features.owner.include_global', false);

        $ownerA = User::query()->create(['name' => 'Insights A', 'email' => 'insights-a@example.com', 'password' => 'secret']);
        $ownerB = User::query()->create(['name' => 'Insights B', 'email' => 'insights-b@example.com', 'password' => 'secret']);

        OwnerContext::withOwner($ownerA, static fn (): Promotion => Promotion::factory()->create([
            'name' => 'Owner A Promotion',
            'code' => null,
        ]));

        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));
        $forOwnerA = OwnerContext::withOwner($ownerA, static fn (): array => app(CachedPromotionInsights::class)->overview());

        app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerB));
        $forOwnerB = OwnerContext::withOwner($ownerB, static fn (): array => app(CachedPromotionInsights::class)->overview());

        expect($forOwnerA['total_promotions'])->toBe(1)
            ->and($forOwnerB['total_promotions'])->toBe(0);
    });
});
