<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Payouts\ClaimScheduledPayout;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\ApprovedConversion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function f05CreateAffiliate(string $code, int $availableMinor, string $currency = 'USD'): array
{
    $affiliate = Affiliate::query()->create([
        'code' => $code . '-' . Str::lower(Str::random(6)),
        'name' => 'F05 Allocation Affiliate',
        'contact_email' => Str::lower(Str::random(8)) . '@example.test',
        'status' => Active::class,
        'commission_type' => CommissionType::Percentage,
        'commission_rate' => 1000,
        'currency' => $currency,
    ]);

    $balance = AffiliateBalance::query()->create([
        'affiliate_id' => $affiliate->id,
        'available_minor' => $availableMinor,
        'minimum_payout_minor' => 5_000,
        'currency' => $currency,
    ]);

    return [$affiliate, $balance];
}

function f05InsertConversions(Affiliate $affiliate, array $specs, CarbonImmutable $base): void
{
    $table = (new AffiliateConversion)->getTable();
    $now = CarbonImmutable::now()->toDateTimeString();
    $rows = [];

    foreach ($specs as $i => $spec) {
        $rows[] = [
            'id' => (string) Str::uuid(),
            'affiliate_id' => $affiliate->id,
            'affiliate_code' => $affiliate->code,
            'external_reference' => 'F05-' . Str::random(8) . '-' . $i,
            'value_minor' => 50_000,
            'commission_minor' => $spec[0],
            'commission_currency' => $spec[1],
            // Raw inserts bypass the Spatie state cast, so store the
            // persisted value ('approved'), not the state class name.
            'status' => ApprovedConversion::value(),
            'occurred_at' => $base->addSeconds($i)->toDateTimeString(),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table($table)->insert($chunk);
    }
}

test('allocation skips other currencies and zero commissions before the payable prefix', function (): void {
    [$affiliate, $balance] = f05CreateAffiliate('F05SKIP', 10_000);
    $base = CarbonImmutable::now()->subDays(60);

    $specs = [];
    for ($i = 0; $i < 1_500; $i++) {
        $specs[] = [100, 'EUR'];
    }
    for ($i = 0; $i < 500; $i++) {
        $specs[] = [0, 'USD'];
    }
    $specs[] = [3_000, 'USD'];
    $specs[] = [3_000, 'USD'];

    f05InsertConversions($affiliate, $specs, $base);

    $operation = app(ClaimScheduledPayout::class)->handle((string) $affiliate->id, 5_000, 'USD');

    expect($operation?->amount_minor)->toBe(6_000)
        ->and($operation?->payout?->conversion_count)->toBe(2)
        ->and($balance->refresh()->available_minor)->toBe(4_000)
        ->and(AffiliateConversion::query()->where('affiliate_id', $affiliate->id)->whereNotNull('affiliate_payout_id')->count())->toBe(2);
});

test('allocation stops at the first unfundable conversion and excludes later cheap rows', function (): void {
    [$affiliate, $balance] = f05CreateAffiliate('F05STOP', 10_000);
    $base = CarbonImmutable::now()->subDays(60);

    f05InsertConversions($affiliate, [
        [7_000, 'USD'],
        [5_000, 'USD'],
        [1_000, 'USD'],
    ], $base);

    $operation = app(ClaimScheduledPayout::class)->handle((string) $affiliate->id, 5_000, 'USD');

    $linked = AffiliateConversion::query()
        ->where('affiliate_id', $affiliate->id)
        ->whereNotNull('affiliate_payout_id')
        ->orderBy('occurred_at')
        ->pluck('commission_minor')
        ->all();

    expect($operation?->amount_minor)->toBe(7_000)
        ->and($linked)->toBe([7_000])
        ->and($balance->refresh()->available_minor)->toBe(3_000);
});

test('allocation normalizes commission currency case', function (): void {
    [$affiliate] = f05CreateAffiliate('F05CASE', 10_000);
    $base = CarbonImmutable::now()->subDays(60);

    f05InsertConversions($affiliate, [
        [6_000, 'usd'],
    ], $base);

    // The FIFO selector uppercases before comparing, so a lowercase
    // stored currency still funds the snapshot.
    expect(app(ClaimScheduledPayout::class)->isEligibleSnapshot((string) $affiliate->id, 5_000, 'USD'))->toBeTrue();
});

test('allocation hydrates at most one batch when the prefix breaks early', function (): void {
    [$affiliate] = f05CreateAffiliate('F05BOUND', 5_000);
    $base = CarbonImmutable::now()->subDays(60);

    $specs = [[3_000, 'USD'], [3_000, 'USD']];
    for ($i = 0; $i < 5_000; $i++) {
        $specs[] = [100, 'USD'];
    }

    f05InsertConversions($affiliate, $specs, $base);

    $hydrated = 0;
    $counting = true;
    AffiliateConversion::retrieved(function () use (&$hydrated, &$counting): void {
        if ($counting) {
            $hydrated++;
        }
    });

    try {
        $operation = app(ClaimScheduledPayout::class)->handle((string) $affiliate->id, 5_000, 'USD');
    } finally {
        $counting = false;
    }

    // The FIFO prefix breaks at row 2, so lazy(1000) fetches only the first
    // chunk; the 5,000 trailing rows are never hydrated. The old ->get()
    // would hydrate all 5,002.
    expect($operation)->toBeNull()
        ->and($hydrated)->toBeLessThanOrEqual(1_000);
});

test('allocation pages large scans in fixed-size batches', function (): void {
    [$affiliate] = f05CreateAffiliate('F05PAGE', 100_000);
    $base = CarbonImmutable::now()->subDays(60);

    $specs = [];
    for ($i = 0; $i < 2_100; $i++) {
        $specs[] = [10, 'USD'];
    }

    f05InsertConversions($affiliate, $specs, $base);

    $table = (new AffiliateConversion)->getTable();
    $conversionSelects = [];
    $listening = true;
    DB::listen(function (QueryExecuted $query) use (&$conversionSelects, &$listening, $table): void {
        if (! $listening) {
            return;
        }

        if (str_contains($query->sql, "\"{$table}\"") && str_contains($query->sql, 'select')) {
            $conversionSelects[] = $query->sql;
        }
    });

    try {
        app(ClaimScheduledPayout::class)->isEligibleSnapshot((string) $affiliate->id, 5_000, 'USD');
    } finally {
        $listening = false;
    }

    $paged = array_filter($conversionSelects, static fn (string $sql): bool => str_contains($sql, 'limit'));

    // 2,100 traversed rows in batches of 1,000 → 3 chunk selects. The old
    // ->get() issued a single unbounded select.
    expect($paged)->toHaveCount(3);
});
