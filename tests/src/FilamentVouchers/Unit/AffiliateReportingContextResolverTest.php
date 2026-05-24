<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active as ActiveAffiliate;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Support\OwnerResolvers\FixedOwnerResolver;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentVouchers\Support\AffiliateReportingContextResolver;
use AIArmada\FilamentVouchers\Widgets\VoucherUsageTimelineWidget;
use AIArmada\Orders\Models\Order;
use AIArmada\Vouchers\Enums\VoucherType;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Models\VoucherUsage;
use AIArmada\Vouchers\States\Active as ActiveVoucher;
use Illuminate\Database\Eloquent\Model;

uses(TestCase::class);

function filamentVouchers_makeAffiliate(array $overrides = []): Affiliate
{
    return Affiliate::query()->create(array_merge([
        'code' => 'AFF-' . uniqid(),
        'name' => 'Affiliate Partner',
        'status' => ActiveAffiliate::class,
        'commission_type' => 'percentage',
        'commission_rate' => 500,
        'currency' => 'USD',
    ], $overrides));
}

function filamentVouchers_makeVoucher(string $code): Voucher
{
    return Voucher::query()->create([
        'code' => $code,
        'name' => $code . ' Voucher',
        'type' => VoucherType::Fixed,
        'value' => 1000,
        'currency' => 'USD',
        'status' => ActiveVoucher::class,
        'allows_manual_redemption' => true,
        'starts_at' => now()->subDay(),
    ]);
}

function filamentVouchers_makeOrder(string $orderNumber): Model
{
    $order = new class extends Model
    {
        public $timestamps = false;

        protected $guarded = [];
    };

    $order->forceFill([
        'order_number' => $orderNumber,
    ]);

    return $order;
}

it('resolves affiliate reporting context from a matching conversion and its attribution', function (): void {
    $affiliate = filamentVouchers_makeAffiliate([
        'code' => 'AFF-CONVERSION',
        'name' => 'Launch Partner',
    ]);

    $attribution = AffiliateAttribution::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'voucher_code' => 'TRACK-ORDER',
        'cart_instance' => 'default',
        'source' => 'google',
        'medium' => 'cpc',
        'campaign' => 'launch-promo',
    ]);

    AffiliateConversion::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'affiliate_attribution_id' => $attribution->id,
        'voucher_code' => 'TRACK-ORDER',
        'order_reference' => 'ORD-1001',
        'external_reference' => 'ORD-1001',
        'subtotal_minor' => 10000,
        'value_minor' => 12000,
        'total_minor' => 12000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
        'occurred_at' => now(),
    ]);

    $voucher = filamentVouchers_makeVoucher('TRACK-ORDER');

    $usage = VoucherUsage::query()->create([
        'voucher_id' => $voucher->id,
        'discount_amount' => 1500,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_API,
        'redeemed_by_type' => 'test-order',
        'redeemed_by_id' => '00000000-0000-0000-0000-000000000001',
        'used_at' => now(),
    ]);

    $usage->setRelation('voucher', $voucher);
    $usage->setRelation('redeemedBy', filamentVouchers_makeOrder('ORD-1001'));

    $context = app(AffiliateReportingContextResolver::class)->resolve($usage);

    expect($context)->toMatchArray([
        'affiliate_code' => 'AFF-CONVERSION',
        'affiliate_name' => 'Launch Partner',
        'source' => 'google',
        'medium' => 'cpc',
        'campaign' => 'launch-promo',
    ]);
});

it('falls back to voucher attribution when no conversion match exists', function (): void {
    $affiliate = filamentVouchers_makeAffiliate([
        'code' => 'AFF-FALLBACK',
        'name' => 'Email Partner',
    ]);

    AffiliateAttribution::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'voucher_code' => 'TRACK-ATTRIBUTION',
        'cart_instance' => 'default',
        'source' => 'newsletter',
        'medium' => 'email',
        'campaign' => 'welcome-sequence',
    ]);

    $voucher = filamentVouchers_makeVoucher('TRACK-ATTRIBUTION');

    $usage = VoucherUsage::query()->create([
        'voucher_id' => $voucher->id,
        'discount_amount' => 800,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_AUTOMATIC,
        'used_at' => now(),
    ]);

    $usage->setRelation('voucher', $voucher);

    $context = app(AffiliateReportingContextResolver::class)->resolve($usage);

    expect($context)->toMatchArray([
        'affiliate_code' => 'AFF-FALLBACK',
        'affiliate_name' => 'Email Partner',
        'source' => 'newsletter',
        'medium' => 'email',
        'campaign' => 'welcome-sequence',
    ]);
});

it('resolves order references from metadata order ids when the order relation is not loaded', function (): void {
    $affiliate = filamentVouchers_makeAffiliate([
        'code' => 'AFF-ORDER-ID',
        'name' => 'Order Lookup Partner',
    ]);

    $order = Order::factory()->create([
        'order_number' => 'ORD-META-9001',
    ]);

    $attribution = AffiliateAttribution::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'voucher_code' => 'ORDER-ID-FALLBACK',
        'cart_instance' => 'default',
        'source' => 'partner-site',
        'medium' => 'referral',
        'campaign' => 'meta-order-id',
    ]);

    AffiliateConversion::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'affiliate_attribution_id' => $attribution->id,
        'voucher_code' => 'ORDER-ID-FALLBACK',
        'order_reference' => $order->order_number,
        'external_reference' => $order->order_number,
        'subtotal_minor' => 10000,
        'value_minor' => 12000,
        'total_minor' => 12000,
        'commission_minor' => 500,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
        'occurred_at' => now(),
    ]);

    $voucher = filamentVouchers_makeVoucher('ORDER-ID-FALLBACK');

    $usage = VoucherUsage::query()->create([
        'voucher_id' => $voucher->id,
        'discount_amount' => 700,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_API,
        'metadata' => [
            'order_id' => (string) $order->id,
        ],
        'used_at' => now(),
    ]);

    $usage->setRelation('voucher', $voucher);

    $resolver = app(AffiliateReportingContextResolver::class);
    $context = $resolver->resolve($usage);

    expect($resolver->orderNumber($usage))->toBe('ORD-META-9001')
        ->and($context)->toMatchArray([
            'affiliate_code' => 'AFF-ORDER-ID',
            'affiliate_name' => 'Order Lookup Partner',
            'source' => 'partner-site',
            'medium' => 'referral',
            'campaign' => 'meta-order-id',
        ]);
});

it('adds affiliate reporting details to voucher timeline events', function (): void {
    $affiliate = filamentVouchers_makeAffiliate([
        'code' => 'AFF-TIMELINE',
        'name' => 'Timeline Partner',
    ]);

    AffiliateAttribution::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'voucher_code' => 'TIMELINE-TRACK',
        'cart_instance' => 'default',
        'source' => 'instagram',
        'medium' => 'social',
        'campaign' => 'creator-push',
    ]);

    $voucher = filamentVouchers_makeVoucher('TIMELINE-TRACK');

    VoucherUsage::query()->create([
        'voucher_id' => $voucher->id,
        'discount_amount' => 900,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_AUTOMATIC,
        'used_at' => now(),
    ]);

    $widget = app(VoucherUsageTimelineWidget::class);
    $widget->record = $voucher;

    $event = $widget->getTimelineEvents()->first();

    expect($event)->not->toBeNull()
        ->and($event['description'])->toContain('Timeline Partner')
        ->and($event['description'])->toContain('instagram / social')
        ->and($event['description'])->toContain('creator-push')
        ->and($event['details'])->toMatchArray([
            'affiliate_code' => 'AFF-TIMELINE',
            'affiliate_name' => 'Timeline Partner',
            'affiliate_source' => 'instagram',
            'affiliate_medium' => 'social',
            'affiliate_campaign' => 'creator-push',
        ]);
});

it('keeps affiliate reporting owner scoped', function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'owner-a-filament-vouchers@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'owner-b-filament-vouchers@example.com',
        'password' => 'secret',
    ]);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($ownerA));

    $voucher = filamentVouchers_makeVoucher('OWNER-SAFE');

    $usage = VoucherUsage::query()->create([
        'voucher_id' => $voucher->id,
        'discount_amount' => 300,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_API,
        'used_at' => now(),
    ])->load('voucher');

    OwnerContext::withOwner($ownerB, static function () use ($ownerB): void {
        $affiliate = filamentVouchers_makeAffiliate([
            'code' => 'AFF-OWNER-B',
            'name' => 'Owner B Affiliate',
        ]);

        AffiliateAttribution::query()->create([
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'voucher_code' => 'OWNER-SAFE',
            'cart_instance' => 'default',
            'source' => 'facebook',
            'medium' => 'paid',
            'campaign' => 'owner-b-only',
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => (string) $ownerB->getKey(),
        ]);
    });

    $context = app(AffiliateReportingContextResolver::class)->resolve($usage);

    expect($context)->toBe([
        'affiliate_code' => null,
        'affiliate_name' => null,
        'source' => null,
        'medium' => null,
        'campaign' => null,
    ]);
});

it('filters voucher usages by affiliate reporting context', function (): void {
    $affiliate = filamentVouchers_makeAffiliate([
        'code' => 'AFF-NEWS',
        'name' => 'Newsletter Partner',
    ]);

    AffiliateAttribution::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'voucher_code' => 'NEWS-CODE',
        'cart_instance' => 'default',
        'source' => 'newsletter',
        'medium' => 'email',
        'campaign' => 'spring-promo',
    ]);

    $otherAffiliate = filamentVouchers_makeAffiliate([
        'code' => 'AFF-SOCIAL',
        'name' => 'Social Partner',
    ]);

    AffiliateAttribution::query()->create([
        'affiliate_id' => $otherAffiliate->id,
        'affiliate_code' => $otherAffiliate->code,
        'voucher_code' => 'SOCIAL-CODE',
        'cart_instance' => 'default',
        'source' => 'instagram',
        'medium' => 'social',
        'campaign' => 'creator-push',
    ]);

    $newsletterVoucher = filamentVouchers_makeVoucher('NEWS-CODE');
    $socialVoucher = filamentVouchers_makeVoucher('SOCIAL-CODE');

    $newsletterUsage = VoucherUsage::query()->create([
        'voucher_id' => $newsletterVoucher->id,
        'discount_amount' => 400,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_API,
        'used_at' => now(),
    ]);

    VoucherUsage::query()->create([
        'voucher_id' => $socialVoucher->id,
        'discount_amount' => 400,
        'currency' => 'USD',
        'channel' => VoucherUsage::CHANNEL_API,
        'used_at' => now(),
    ]);

    $resolver = app(AffiliateReportingContextResolver::class);

    $filteredIds = $resolver
        ->applyUsageFilters(VoucherUsage::query(), [
            'affiliate_code' => 'AFF-NEWS',
            'source' => 'newsletter',
            'medium' => 'email',
            'campaign' => 'spring-promo',
        ])
        ->pluck('id')
        ->all();

    expect($filteredIds)->toBe([(string) $newsletterUsage->id]);
});

it('builds voucher usage table with affiliate reporting columns', function (): void {
    $source = file_get_contents(dirname(__DIR__, 4) . '/packages/filament-vouchers/src/Resources/VoucherUsageResource/Tables/VoucherUsagesTable.php');

    expect($source)
        ->toContain("TextColumn::make('affiliate_reporting')")
        ->toContain("TextColumn::make('affiliate_campaign')")
        ->toContain("SelectFilter::make('affiliate_code')")
        ->toContain("SelectFilter::make('affiliate_source')")
        ->toContain("SelectFilter::make('affiliate_medium')")
        ->toContain("SelectFilter::make('affiliate_campaign')")
        ->toContain('ExportAction::make()')
        ->toContain('VoucherUsageExporter::class');
});
