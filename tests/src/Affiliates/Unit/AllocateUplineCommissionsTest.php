<?php

declare(strict_types=1);

use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Features\Conversions\Actions\AllocateUplineCommissions;
use AIArmada\Affiliates\Features\Conversions\Actions\ApplyConversionAccounting;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Features\Conversions\States\ConversionStatus;
use AIArmada\Affiliates\Features\Conversions\States\PendingConversion;
use AIArmada\Affiliates\Features\Webhooks\Api\WebhookApi;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;

test('allocates upline commissions from voucher share overrides', function (): void {
    config([
        'affiliates.payouts.multi_level.enabled' => true,
        'affiliates.payouts.multi_level.levels' => [0.1],
        'affiliates.events.dispatch_conversion' => false,
        'affiliates.events.dispatch_webhooks' => false,
    ]);

    $parent = createTestAffiliate(['code' => 'UPLINE-PARENT']);
    $child = createTestAffiliate([
        'code' => 'UPLINE-CHILD',
        'parent_affiliate_id' => $parent->getKey(),
    ]);

    $action = new AllocateUplineCommissions(
        app(Dispatcher::class),
        app(WebhookApi::class),
        app(ApplyConversionAccounting::class),
    );

    $status = ConversionStatus::fromString(PendingConversion::class);
    $baseConversion = new AffiliateConversionData(
        id: (string) Str::uuid(),
        affiliateId: $child->getKey(),
        affiliateCode: $child->code,
        uplineLevels: [
            ['level' => 1, 'share' => 0.05],
        ],
        commissionMinor: 1000,
        commissionCurrency: 'MYR',
    );

    $action->handle([$baseConversion], false, $status, null);

    $uplineConversion = AffiliateConversion::query()
        ->where('affiliate_id', $parent->getKey())
        ->where('channel', 'upline')
        ->firstOrFail();

    expect($uplineConversion->commission_minor)->toBe(50)
        ->and($uplineConversion->metadata['weight'])->toBe(0.05);
});

test('upline allocation does not cross a foreign owner boundary', function (): void {
    config([
        'affiliates.owner.enabled' => true,
        'affiliates.owner.include_global' => true,
        'affiliates.payouts.multi_level.enabled' => true,
        'affiliates.payouts.multi_level.levels' => [0.1],
        'affiliates.events.dispatch_conversion' => false,
        'affiliates.events.dispatch_webhooks' => false,
    ]);

    $owner = User::factory()->create();
    $other = User::factory()->create();
    $parent = OwnerContext::withOwner($other, fn () => createTestAffiliate());
    $child = OwnerContext::withOwner($owner, fn () => createTestAffiliate());

    // Simulate a corrupt pointer without using the guarded identity writer.
    $child->getConnection()->table($child->getTable())->where('id', $child->id)->update(['parent_affiliate_id' => $parent->id]);

    OwnerContext::withOwner($owner, function () use ($child): void {
        $data = new AffiliateConversionData(
            id: (string) Str::uuid(),
            affiliateId: $child->id,
            affiliateCode: $child->code,
            commissionMinor: 1000,
            commissionCurrency: 'MYR',
        );

        app(AllocateUplineCommissions::class)->handle([$data], false, ConversionStatus::fromString(PendingConversion::class), null);
    });

    expect(AffiliateConversion::query()->withoutOwnerScope()->where('channel', 'upline')->exists())->toBeFalse();
});
