<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\Features\Payouts\Models\AffiliatePayout;
use AIArmada\Affiliates\Features\Payouts\States\PendingPayout;
use AIArmada\Affiliates\States\Active;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Settings\ExchangeRateSettings;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeOverride;
use AIArmada\FilamentAffiliates\Features\Payouts\Queries\PayoutExportService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function (): void {
    AffiliatePayout::query()->delete();
    Affiliate::query()->delete();

    // Settings cache outlives RefreshDatabase rollbacks within a process.
    Artisan::call('settings:clear-cache');
    (new ExchangeRateSettings(['base' => 'USD', 'rates' => ['MYR' => 4.0], 'history' => []]))->save();
});

function settlementExportAffiliate(): Affiliate
{
    return Affiliate::create([
        'code' => 'SETEXP-' . uniqid(),
        'name' => 'Settlement Export Test',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
}

it('downloads a settlement csv with per-currency legs and a labeled converted total', function (): void {
    $affiliate = settlementExportAffiliate();

    foreach ([['USD', 10000, 'SETEXP-USD'], ['MYR', 40000, 'SETEXP-MYR']] as [$currency, $total, $ref]) {
        AffiliatePayout::create([
            'reference' => $ref,
            'payee_type' => $affiliate->getMorphClass(),
            'payee_id' => $affiliate->id,
            'total_minor' => $total,
            'currency' => $currency,
            'status' => PendingPayout::class,
            'created_at' => CarbonImmutable::parse('2026-06-15 12:00:00'),
        ]);
    }

    $response = app(PayoutExportService::class)->downloadSettlementCsv('2026-01-01', '2026-12-31');

    expect($response)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    // Per-currency legs.
    expect($content)->toContain('USD')
        ->and($content)->toContain('MYR')
        ->and($content)->toContain('SETEXP-USD')
        ->and($content)->toContain('SETEXP-MYR')
        // Labeled converted total: 10,000 USD @ 4.0 = 40,000 MYR + 40,000 MYR leg.
        ->and($content)->toContain('800.00')
        ->and($content)->toContain('2026-12-31');
});

it('keeps settlement rows and totals in the current owner scope and refuses mismatched payee labels', function (): void {
    config(['affiliates.owner.enabled' => true, 'affiliates.owner.include_global' => true]);
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $create = static function (string $reference): AffiliatePayout {
        $affiliate = settlementExportAffiliate();

        return AffiliatePayout::create([
            'reference' => $reference,
            'payee_type' => $affiliate->getMorphClass(),
            'payee_id' => $affiliate->id,
            'total_minor' => 10000,
            'currency' => 'USD',
            'status' => PendingPayout::class,
        ]);
    };

    $owned = OwnerContext::withOwner($owner, fn (): AffiliatePayout => $create('OWNED-SETTLEMENT'));
    $foreign = OwnerContext::withOwner($other, fn (): AffiliatePayout => $create('FOREIGN-SETTLEMENT'));
    OwnerContext::withOwner(null, fn (): AffiliatePayout => $create('GLOBAL-SETTLEMENT'));

    $foreignCode = OwnerContext::withOwner($other, fn (): string => Affiliate::query()->findOrFail($foreign->payee_id)->code);
    DB::table($owned->getTable())->where('id', $owned->id)->update(['payee_id' => $foreign->payee_id]);

    $content = OwnerContext::withOwner($owner, function (): string {
        return OwnerScopeOverride::withoutIncludeGlobal(function (): string {
            $response = app(PayoutExportService::class)->downloadSettlementCsv();
            ob_start();
            $response->sendContent();

            return (string) ob_get_clean();
        });
    });

    expect($content)->toContain('OWNED-SETTLEMENT')
        ->not->toContain('FOREIGN-SETTLEMENT', 'GLOBAL-SETTLEMENT', $foreignCode);
});
