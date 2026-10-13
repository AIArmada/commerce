<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\RecordAffiliateOutcome;
use AIArmada\Affiliates\Enums\AffiliateOutcomeDisposition;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\States\Pending;
use AIArmada\Affiliates\States\PendingConversion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

function createRedispatchAffiliate(string $code): Affiliate
{
    return Affiliate::create([
        'code' => $code,
        'name' => "Redispatch Test {$code}",
        'status' => Pending::class,
        'registration_approval_mode' => 'admin',
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
}

function createRedispatchAttribution(Affiliate $affiliate, string $cookie): AffiliateAttribution
{
    return AffiliateAttribution::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'cookie_value' => $cookie,
        'cart_instance' => 'default',
        'first_seen_at' => now()->subHour(),
        'last_seen_at' => now()->subHour(),
    ]);
}

test('outcome retry returns replayed with the original winner preserved', function (): void {
    Event::fake([AffiliateConversionRecorded::class]);

    $affiliate = createRedispatchAffiliate('REDISPATCH001');
    $attribution = createRedispatchAttribution($affiliate, 'cookie-redispatch-001');

    $payload = [
        'commission_minor' => 1000,
        'subtotal_minor' => 10000,
        'value_minor' => 10000,
        'commission_currency' => 'USD',
    ];

    $action = app(RecordAffiliateOutcome::class);
    $first = $action->handle($attribution, 'referral', 'redispatch-test:referral:ext-retry-1', $payload);
    $second = $action->handle($attribution, 'referral', 'redispatch-test:referral:ext-retry-1', $payload);

    expect($first->disposition)->toBe(AffiliateOutcomeDisposition::Created)
        ->and($second->disposition)->toBe(AffiliateOutcomeDisposition::Replayed)
        ->and($second->conversion?->id)->toBe($first->conversion?->id)
        ->and($second->conversion?->affiliateId)->toBe((string) $affiliate->getKey());

    Event::assertDispatched(AffiliateConversionRecorded::class, 2);
    expect(AffiliateConversion::query()->where('idempotency_key', hash('sha256', 'redispatch-test:referral:ext-retry-1'))->count())->toBe(1);
});

test('same business key from a different attribution replays the original winner without new accounting', function (): void {
    Event::fake([AffiliateConversionRecorded::class]);

    $winner = createRedispatchAffiliate('REDISPATCHWIN');
    $loser = createRedispatchAffiliate('REDISPATCHLOS');
    $winnerAttribution = createRedispatchAttribution($winner, 'cookie-redispatch-win');
    $loserAttribution = createRedispatchAttribution($loser, 'cookie-redispatch-los');

    $businessKey = 'redispatch-test:referral:shared-source-1';
    $payload = [
        'commission_minor' => 500,
        'subtotal_minor' => 5000,
        'value_minor' => 5000,
        'commission_currency' => 'USD',
    ];

    $action = app(RecordAffiliateOutcome::class);
    $first = $action->handle($winnerAttribution, 'referral', $businessKey, $payload);

    $balanceBefore = AffiliateBalance::query()
        ->where('affiliate_id', $winner->getKey())
        ->where('currency', 'USD')
        ->first();

    $second = $action->handle($loserAttribution, 'referral', $businessKey, $payload);

    $balanceAfter = AffiliateBalance::query()
        ->where('affiliate_id', $winner->getKey())
        ->where('currency', 'USD')
        ->first();

    expect($first->disposition)->toBe(AffiliateOutcomeDisposition::Created)
        ->and($second->disposition)->toBe(AffiliateOutcomeDisposition::Replayed)
        ->and($second->conversion?->affiliateId)->toBe((string) $winner->getKey())
        ->and($second->conversion?->id)->toBe($first->conversion?->id);

    expect(AffiliateConversion::query()->where('idempotency_key', hash('sha256', $businessKey))->count())->toBe(1);

    if ($balanceBefore instanceof AffiliateBalance && $balanceAfter instanceof AffiliateBalance) {
        expect($balanceAfter->holding_minor)->toBe($balanceBefore->holding_minor)
            ->and($balanceAfter->available_minor)->toBe($balanceBefore->available_minor)
            ->and($balanceAfter->lifetime_earnings_minor)->toBe($balanceBefore->lifetime_earnings_minor);
    }

    expect(AffiliateBalance::query()->where('affiliate_id', $loser->getKey())->count())->toBe(0);
});

test('different namespaces for one source yield separate rows', function (): void {
    $affiliate = createRedispatchAffiliate('REDISPATCHNS');
    $attribution = createRedispatchAttribution($affiliate, 'cookie-redispatch-ns');

    $action = app(RecordAffiliateOutcome::class);
    $first = $action->handle($attribution, 'referral', 'namespace-a:referral:source-1', ['dispatch_event' => false]);
    $second = $action->handle($attribution, 'referral', 'namespace-b:referral:source-1', ['dispatch_event' => false]);

    expect($first->disposition)->toBe(AffiliateOutcomeDisposition::Created)
        ->and($second->disposition)->toBe(AffiliateOutcomeDisposition::Created)
        ->and($second->conversion?->id)->not->toBe($first->conversion?->id);
});

test('existing idempotency row replays inside an outer transaction without poisoning it', function (): void {
    Event::fake([AffiliateConversionRecorded::class]);

    $affiliate = createRedispatchAffiliate('REDISPATCH002');
    $attribution = createRedispatchAttribution($affiliate, 'cookie-redispatch-002');
    $businessKey = 'redispatch-test:referral:ext-race-1';

    AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'affiliate_attribution_id' => $attribution->getKey(),
        'external_reference' => 'ext-other',
        'idempotency_key' => hash('sha256', $businessKey),
        'conversion_type' => 'referral',
        'subtotal_minor' => 10000,
        'value_minor' => 10000,
        'commission_minor' => 1000,
        'commission_currency' => 'USD',
        'status' => PendingConversion::class,
        'occurred_at' => now(),
    ]);

    $result = DB::transaction(function () use ($attribution, $businessKey) {
        $outcome = app(RecordAffiliateOutcome::class)->handle($attribution, 'referral', $businessKey, [
            'commission_minor' => 1000,
            'subtotal_minor' => 10000,
            'value_minor' => 10000,
            'commission_currency' => 'USD',
        ]);

        Affiliate::query()->whereKey($attribution->affiliate_id)->firstOrFail();

        return $outcome;
    });

    expect($result->disposition)->toBe(AffiliateOutcomeDisposition::Replayed);

    Event::assertDispatched(AffiliateConversionRecorded::class, 1);
});

test('missing affiliate returns no-affiliate without writes', function (): void {
    Event::fake([AffiliateConversionRecorded::class]);

    $affiliate = createRedispatchAffiliate('REDISPATCHNOA');
    $attribution = createRedispatchAttribution($affiliate, 'cookie-redispatch-noa');
    $attribution->forceFill(['affiliate_id' => (string) Str::uuid()])->save();

    $result = app(RecordAffiliateOutcome::class)->handle(
        $attribution->refresh(),
        'referral',
        'redispatch-test:referral:no-affiliate-1',
        ['dispatch_event' => false],
    );

    expect($result->disposition)->toBe(AffiliateOutcomeDisposition::NoAffiliate)
        ->and($result->conversion)->toBeNull();

    Event::assertNotDispatched(AffiliateConversionRecorded::class);
});

test('fraud-blocked outcome creates a rejected row with persisted signals and zero balance movement', function (): void {
    Event::fake([AffiliateConversionRecorded::class]);
    config(['affiliates.fraud.blocking_threshold' => 40]);

    $affiliate = createRedispatchAffiliate('REDISPATCHFRA');
    $attribution = AffiliateAttribution::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'cookie_value' => 'cookie-redispatch-fraud',
        'cart_instance' => 'default',
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    $result = app(RecordAffiliateOutcome::class)->handle(
        $attribution,
        'referral',
        'redispatch-test:referral:fraud-blocked-1',
        [
            'commission_minor' => 1000,
            'subtotal_minor' => 10000,
            'value_minor' => 10000,
            'commission_currency' => 'USD',
            'occurred_at' => now(),
        ],
    );

    expect($result->disposition)->toBe(AffiliateOutcomeDisposition::Rejected)
        ->and($result->conversion)->not->toBeNull();

    $stored = AffiliateConversion::query()->whereKey($result->conversion->id)->firstOrFail();

    expect($stored->status->getValue())->toBe('rejected');

    $balance = AffiliateBalance::query()->where('affiliate_id', $affiliate->getKey())->first();

    if ($balance instanceof AffiliateBalance) {
        expect($balance->holding_minor)->toBe(0)
            ->and($balance->available_minor)->toBe(0)
            ->and($balance->lifetime_earnings_minor)->toBe(0);
    }
});

test('cross-connection attribution fails closed with no partial writes', function (): void {
    config(['database.connections.wp2_mismatch_test' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]]);

    $affiliate = createRedispatchAffiliate('REDISPATCHXCON');
    $attribution = createRedispatchAttribution($affiliate, 'cookie-redispatch-xcon');
    $attribution->setConnection('wp2_mismatch_test');

    expect(fn () => app(RecordAffiliateOutcome::class)->handle(
        $attribution,
        'referral',
        'redispatch-test:referral:xcon-1',
        ['dispatch_event' => false],
    ))->toThrow(LogicException::class);

    expect(AffiliateConversion::query()->where('idempotency_key', hash('sha256', 'redispatch-test:referral:xcon-1'))->count())->toBe(0);
});

test('outer rollback removes conversion signals balances and suppresses events', function (): void {
    Event::fake([AffiliateConversionRecorded::class]);
    config(['affiliates.fraud.blocking_threshold' => 40]);

    $affiliate = createRedispatchAffiliate('REDISPATCHRB');
    $attribution = AffiliateAttribution::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'cookie_value' => 'cookie-redispatch-rollback',
        'cart_instance' => 'default',
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    $businessKey = 'redispatch-test:referral:rollback-1';

    try {
        DB::transaction(function () use ($attribution, $businessKey): void {
            app(RecordAffiliateOutcome::class)->handle($attribution, 'referral', $businessKey, [
                'commission_minor' => 1000,
                'commission_currency' => 'USD',
                'occurred_at' => now(),
            ]);

            throw new RuntimeException('Simulated caller failure after outcome.');
        });

        $this->fail('Outer transaction must roll back.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Simulated caller failure after outcome.');
    }

    expect(AffiliateConversion::query()->where('idempotency_key', hash('sha256', $businessKey))->count())->toBe(0);
    expect(AffiliateBalance::query()->where('affiliate_id', $affiliate->getKey())->count())->toBe(0);

    Event::assertNotDispatched(AffiliateConversionRecorded::class);
});

test('business key validation rejects empty and overlong identities', function (): void {
    $affiliate = createRedispatchAffiliate('REDISPATCHVAL');
    $attribution = createRedispatchAttribution($affiliate, 'cookie-redispatch-val');

    $action = app(RecordAffiliateOutcome::class);

    expect(fn () => $action->handle($attribution, 'referral', '   ', ['dispatch_event' => false]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $action->handle($attribution, 'referral', str_repeat('k', 192), ['dispatch_event' => false]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $action->handle($attribution, '   ', 'redispatch-test:referral:valid-1', ['dispatch_event' => false]))
        ->toThrow(InvalidArgumentException::class);
});

test('generic outcomes cannot record the reserved award type with alternate casing', function (): void {
    $affiliate = createRedispatchAffiliate('REDISPATCHAWD');
    $attribution = createRedispatchAttribution($affiliate, 'cookie-redispatch-award');

    expect(fn () => app(RecordAffiliateOutcome::class)->handle(
        $attribution,
        'REWARD_AWARD',
        'redispatch-test:reserved-award:1',
        ['commission_minor' => 1],
    ))->toThrow(InvalidArgumentException::class);

    expect(AffiliateConversion::query()->where('idempotency_key', hash('sha256', 'redispatch-test:reserved-award:1'))->exists())->toBeFalse();
});

test('a deadlock after saving the outcome retries with a fresh conversion', function (int $blockingThreshold, AffiliateOutcomeDisposition $disposition): void {
    config([
        'affiliates.fraud.velocity.max_conversions_per_day' => 0,
        'affiliates.fraud.blocking_threshold' => $blockingThreshold,
    ]);

    $suffix = $blockingThreshold === 999 ? 'ALLOW' : 'REJECT';
    $affiliate = createRedispatchAffiliate('REDISPATCHDL' . $suffix);
    $attribution = createRedispatchAttribution($affiliate, 'cookie-redispatch-deadlock-' . Str::lower($suffix));
    $businessKey = 'retry:after-save:' . $suffix;

    $attemptIds = [];
    $thrown = false;

    // First-attempt-only deadlock after the conversion row is saved.
    // Fraud signals are written after save, so throwing in the signal's
    // creating hook simulates a post-save concurrency error on attempt 1.
    // Production FraudDetectionService stays final; no mocking.
    AffiliateFraudSignal::creating(function (AffiliateFraudSignal $signal) use (&$attemptIds, &$thrown): void {
        $attemptIds[] = (string) $signal->conversion_id;

        if (! $thrown) {
            $thrown = true;

            throw new QueryException(
                DB::connection()->getName(),
                'insert into affiliate_fraud_signals',
                [],
                new PDOException('Deadlock found when trying to get lock', 40001),
            );
        }
    });

    Event::fake([AffiliateConversionRecorded::class]);

    // The action must own the outer transaction for Laravel to retry
    // deadlocks (nested transactions rethrow DeadlockException).
    DB::commit();

    try {
        $result = app(RecordAffiliateOutcome::class)->handle($attribution, 'referral', $businessKey, [
            'commission_minor' => 1000,
            'commission_currency' => 'USD',
        ]);

        expect($result->disposition)->toBe($disposition)
            ->and(array_unique($attemptIds))->toHaveCount(2)
            ->and($result->conversion?->id)->toBe(end($attemptIds))
            ->and(AffiliateConversion::query()->where('idempotency_key', hash('sha256', $businessKey))->count())->toBe(1)
            ->and(AffiliateFraudSignal::query()->where('conversion_id', $attemptIds[0])->count())->toBe(0)
            ->and(AffiliateFraudSignal::query()->where('conversion_id', (string) $result->conversion->id)->count())->toBe(1);

        $balance = AffiliateBalance::query()->where('affiliate_id', $affiliate->id)->first();
        expect($balance?->holding_minor ?? 0)->toBe($disposition === AffiliateOutcomeDisposition::Created ? 1000 : 0);
        Event::assertDispatchedTimes(AffiliateConversionRecorded::class, 1);
    } finally {
        // Prevent the scoped hook from firing after this test.
        $thrown = true;

        // DB::commit() above broke RefreshDatabase isolation; remove
        // committed fixtures (including the setUp owner) so the sibling
        // dataset stays clean regardless of worker reuse.
        AffiliateFraudSignal::query()->whereIn('conversion_id', $attemptIds)->delete();
        AffiliateConversion::query()->where('idempotency_key', hash('sha256', $businessKey))->delete();
        AffiliateBalance::query()->where('affiliate_id', $affiliate->id)->delete();
        AffiliateAttribution::query()->whereKey($attribution->id)->delete();
        Affiliate::query()->whereKey($affiliate->id)->delete();
        DB::table('users')->where('email', 'default-owner@example.com')->delete();
    }
})->with([
    'allowed' => [999, AffiliateOutcomeDisposition::Created],
    'rejected' => [1, AffiliateOutcomeDisposition::Rejected],
]);
