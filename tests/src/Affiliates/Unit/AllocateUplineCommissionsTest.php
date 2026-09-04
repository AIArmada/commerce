<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\AllocateUplineCommissions;
use AIArmada\Affiliates\Actions\Conversions\ApplyConversionAccounting;
use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ConversionStatus;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\Support\Webhooks\WebhookDispatcher;
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
        app(WebhookDispatcher::class),
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
