<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\RecordAffiliateAward;
use AIArmada\Affiliates\Contracts\AffiliateAwardPolicy;
use AIArmada\Affiliates\Data\AffiliateAwardDecision;
use AIArmada\Affiliates\Data\AffiliateAwardProposal;
use AIArmada\Affiliates\Enums\AffiliateOutcomeDisposition;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Events\FraudSignalDetected;
use AIArmada\Affiliates\Events\HoldingShortfallDetected;
use AIArmada\Affiliates\Exceptions\AffiliateAwardPolicyLimitException;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateBalance;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\Affiliates\Models\AffiliateUpline;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\Disabled;
use AIArmada\Affiliates\States\PaidConversion;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\ReversedConversion;
use AIArmada\Affiliates\Support\Awards\AffiliateAwardKey;
use AIArmada\Affiliates\Support\Awards\NullAffiliateAwardPolicy;
use AIArmada\Commerce\Tests\AffiliateCashPosting\CashPostingTestCase;
use AIArmada\Commerce\Tests\AffiliateCashPosting\Support\CashPostingFailureInjector;
use AIArmada\Commerce\Tests\AffiliateCashPosting\Support\FixtureAffiliateAwardPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(CashPostingTestCase::class);

function bindCashPostAllow(?int $min = null, ?int $max = null): void
{
    app()->bind(AffiliateAwardPolicy::class, fn (): FixtureAffiliateAwardPolicy => FixtureAffiliateAwardPolicy::allow($min, $max));
}

function cashPostBalanceFor(Affiliate $affiliate, string $currency): ?AffiliateBalance
{
    return AffiliateBalance::query()
        ->where('affiliate_id', $affiliate->getKey())
        ->where('currency', $currency)
        ->first();
}

test('posts the exact amount below the commercial minimum without clamping', function (): void {
    config()->set('affiliates.commissions.minimum_minor', 5000);
    config()->set('affiliates.commissions.maximum_minor', 10000);
    bindCashPostAllow();
    $recipient = $this->makeRecipient();

    $result = app(RecordAffiliateAward::class)->handle($recipient, $this->awardKey('below-min'), 100, 'MYR');

    expect($result->disposition)->toBe(AffiliateOutcomeDisposition::Created)
        ->and($result->conversion?->commissionMinor)->toBe(100)
        ->and($result->conversion?->commissionCurrency)->toBe('MYR');

    $balance = cashPostBalanceFor($recipient, 'MYR');
    expect($balance)->not->toBeNull()
        ->and((int) $balance->available_minor)->toBe(100)
        ->and((int) $balance->lifetime_earnings_minor)->toBe(100)
        ->and((int) $balance->holding_minor)->toBe(0);
});

test('posts the exact amount above the commercial maximum without clamping', function (): void {
    config()->set('affiliates.commissions.minimum_minor', 5000);
    config()->set('affiliates.commissions.maximum_minor', 10000);
    bindCashPostAllow();
    $recipient = $this->makeRecipient();

    $result = app(RecordAffiliateAward::class)->handle($recipient, $this->awardKey('above-max'), 250000, 'MYR');

    expect($result->disposition)->toBe(AffiliateOutcomeDisposition::Created)
        ->and($result->conversion?->commissionMinor)->toBe(250000);

    $balance = cashPostBalanceFor($recipient, 'MYR');
    expect((int) $balance->available_minor)->toBe(250000)
        ->and((int) $balance->lifetime_earnings_minor)->toBe(250000);
});

test('approved awards credit available with a dedicated UTC approved timestamp', function (): void {
    bindCashPostAllow();
    $recipient = $this->makeRecipient();

    $result = app(RecordAffiliateAward::class)->handle(
        $recipient,
        $this->awardKey('approved'),
        1500,
        'MYR',
        ['status' => ApprovedConversion::class]
    );

    expect($result->disposition)->toBe(AffiliateOutcomeDisposition::Created);

    $stored = AffiliateConversion::query()->whereKey($result->conversion->id)->firstOrFail();
    expect((int) $stored->held_minor)->toBe(0)
        ->and($stored->approved_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($stored->approved_at->toIso8601String())->toEndWith('+00:00')
        ->and($stored->occurred_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($stored->conversion_type)->toBe(RecordAffiliateAward::CONVERSION_TYPE)
        ->and((int) $stored->subtotal_minor)->toBe(0)
        ->and((int) $stored->value_minor)->toBe(0);

    $balance = cashPostBalanceFor($recipient, 'MYR');
    expect((int) $balance->available_minor)->toBe(1500)
        ->and((int) $balance->holding_minor)->toBe(0)
        ->and((int) $balance->lifetime_earnings_minor)->toBe(1500);
});

test('pending awards hold funds with a held stamp and no approved timestamp', function (): void {
    bindCashPostAllow();
    $recipient = $this->makeRecipient();

    $result = app(RecordAffiliateAward::class)->handle(
        $recipient,
        $this->awardKey('pending'),
        2200,
        'MYR',
        ['status' => PendingConversion::class]
    );

    expect($result->disposition)->toBe(AffiliateOutcomeDisposition::Created);

    $stored = AffiliateConversion::query()->whereKey($result->conversion->id)->firstOrFail();
    expect((int) $stored->held_minor)->toBe(2200)
        ->and($stored->approved_at)->toBeNull();

    $balance = cashPostBalanceFor($recipient, 'MYR');
    expect((int) $balance->available_minor)->toBe(0)
        ->and((int) $balance->holding_minor)->toBe(2200)
        ->and((int) $balance->lifetime_earnings_minor)->toBe(2200);
});

test('omitted status posts approved with exact accounting', function (): void {
    bindCashPostAllow();
    $recipient = $this->makeRecipient();

    $result = app(RecordAffiliateAward::class)->handle($recipient, $this->awardKey('default-status'), 700, 'MYR');

    $stored = AffiliateConversion::query()->whereKey($result->conversion->id)->firstOrFail();
    expect($stored->status->equals(ApprovedConversion::class))->toBeTrue()
        ->and($stored->approved_at)->not->toBeNull()
        ->and((int) cashPostBalanceFor($recipient, 'MYR')->available_minor)->toBe(700);
});

test('lowercase currency is normalized to uppercase ISO code', function (): void {
    bindCashPostAllow();
    $recipient = $this->makeRecipient();

    $result = app(RecordAffiliateAward::class)->handle($recipient, $this->awardKey('ccy'), 300, 'myr');

    expect($result->conversion?->commissionCurrency)->toBe('MYR')
        ->and(cashPostBalanceFor($recipient, 'MYR'))->not->toBeNull();
});

test('paid reversed unknown and non-string statuses are refused at creation', function (): void {
    bindCashPostAllow();
    $recipient = $this->makeRecipient();
    $action = app(RecordAffiliateAward::class);

    foreach ([PaidConversion::class, ReversedConversion::class, 'paid', 'reversed', 'nope', 42] as $status) {
        try {
            $action->handle($recipient, $this->awardKey('bad-status'), 100, 'MYR', ['status' => $status]);
            $this->fail('Status refusal expected.');
        } catch (InvalidArgumentException) {
        }
    }

    expect(AffiliateConversion::query()->where('affiliate_id', $recipient->getKey())->count())->toBe(0)
        ->and(cashPostBalanceFor($recipient, 'MYR'))->toBeNull();
});

test('non-positive amounts are refused with zero effects', function (): void {
    bindCashPostAllow();
    $recipient = $this->makeRecipient();
    $action = app(RecordAffiliateAward::class);

    foreach ([0, -1, -999999] as $amount) {
        try {
            $action->handle($recipient, $this->awardKey('bad-amount'), $amount, 'MYR');
            $this->fail('Amount refusal expected.');
        } catch (InvalidArgumentException) {
        }
    }

    expect(AffiliateConversion::query()->where('affiliate_id', $recipient->getKey())->count())->toBe(0);
});

test('malformed currencies are refused with zero effects', function (): void {
    bindCashPostAllow();
    $recipient = $this->makeRecipient();
    $action = app(RecordAffiliateAward::class);

    foreach (['', 'US', 'USDD', '12$', 'M Y R'] as $currency) {
        try {
            $action->handle($recipient, $this->awardKey('bad-ccy'), 100, $currency);
            $this->fail('Currency refusal expected.');
        } catch (InvalidArgumentException) {
        }
    }

    expect(AffiliateConversion::query()->where('affiliate_id', $recipient->getKey())->count())->toBe(0);
});

test('malformed award keys are refused with zero effects', function (): void {
    bindCashPostAllow();
    $recipient = $this->makeRecipient();
    $action = app(RecordAffiliateAward::class);

    foreach (['', '   ', str_repeat('k', 192), "bad\0key"] as $key) {
        try {
            $action->handle($recipient, $key, 100, 'MYR');
            $this->fail('Key refusal expected.');
        } catch (InvalidArgumentException) {
        }
    }

    expect(AffiliateConversion::query()->where('affiliate_id', $recipient->getKey())->count())->toBe(0);
});

test('unknown and attribution-bearing attributes are refused', function (): void {
    bindCashPostAllow();
    $recipient = $this->makeRecipient();
    $action = app(RecordAffiliateAward::class);

    $forbidden = [
        'affiliate_attribution_id' => (string) Str::uuid(),
        'subject_type' => 'post',
        'subject_key' => 'post:1',
        'subject_id' => (string) Str::uuid(),
        'subject_instance' => 'default',
        'affiliate_link_id' => (string) Str::uuid(),
        'upline_levels' => [],
        'commission_override' => [],
        'voucher_code' => 'VOUCHER',
        'commission_minor' => 1,
        'policy_min_minor' => 1,
    ];

    foreach ($forbidden as $name => $value) {
        try {
            $action->handle($recipient, $this->awardKey('bad-attr'), 100, 'MYR', [$name => $value]);
            $this->fail("Attribute [{$name}] refusal expected.");
        } catch (InvalidArgumentException) {
        }
    }

    expect(AffiliateConversion::query()->where('affiliate_id', $recipient->getKey())->count())->toBe(0);
});

test('malformed metadata references and occurred_at are refused', function (): void {
    bindCashPostAllow();
    $recipient = $this->makeRecipient();
    $action = app(RecordAffiliateAward::class);

    $badShapes = [
        ['metadata' => 'nope'],
        ['metadata' => array_fill_keys(array_map(fn ($i): string => "k{$i}", range(1, 33)), 1)],
        ['metadata' => ['' => 1]],
        ['metadata' => ['nested' => ['deep' => 1]]],
        ['metadata' => ['big' => str_repeat('x', 501)]],
        ['external_reference' => str_repeat('r', 121)],
        ['external_reference' => 42],
        ['source_ref' => str_repeat('r', 121)],
        ['channel' => str_repeat('c', 65)],
        ['origin' => str_repeat('o', 33)],
        ['occurred_at' => 'not-a-date-object'],
    ];

    foreach ($badShapes as $attributes) {
        try {
            $action->handle($recipient, $this->awardKey('bad-shape'), 100, 'MYR', $attributes);
            $this->fail('Shape refusal expected.');
        } catch (InvalidArgumentException) {
        }
    }

    expect(AffiliateConversion::query()->where('affiliate_id', $recipient->getKey())->count())->toBe(0);
});

test('unbound host policy returns a truthful refusal with zero effects', function (): void {
    expect(app()->bound(AffiliateAwardPolicy::class))->toBeFalse();
    $recipient = $this->makeRecipient();
    $key = $this->awardKey('unbound');

    $result = app(RecordAffiliateAward::class)->handle($recipient, $key, 100, 'MYR');

    expect($result->disposition)->toBe(AffiliateOutcomeDisposition::Rejected)
        ->and($result->conversion)->toBeNull()
        ->and(AffiliateConversion::query()->where('idempotency_key', hash('sha256', $key))->count())->toBe(0)
        ->and(AffiliateConversion::query()->where('affiliate_id', $recipient->getKey())->count())->toBe(0)
        ->and(cashPostBalanceFor($recipient, 'MYR'))->toBeNull();
});

test('explicit host denial returns a refusal with zero effects', function (): void {
    Event::fake([AffiliateConversionRecorded::class]);
    app()->bind(AffiliateAwardPolicy::class, fn (): FixtureAffiliateAwardPolicy => FixtureAffiliateAwardPolicy::deny());
    $recipient = $this->makeRecipient();

    $result = app(RecordAffiliateAward::class)->handle($recipient, $this->awardKey('denied'), 100, 'MYR');

    expect($result->disposition)->toBe(AffiliateOutcomeDisposition::Rejected)
        ->and($result->conversion)->toBeNull()
        ->and(AffiliateConversion::query()->where('affiliate_id', $recipient->getKey())->count())->toBe(0)
        ->and(cashPostBalanceFor($recipient, 'MYR'))->toBeNull();

    Event::assertNotDispatched(AffiliateConversionRecorded::class);
});

test('inactive and missing recipients return no-affiliate with zero effects', function (): void {
    bindCashPostAllow();
    $action = app(RecordAffiliateAward::class);

    $inactive = $this->makeRecipient(['status' => Disabled::class]);
    $missing = new Affiliate;
    $missing->id = (string) Str::uuid();

    $first = $action->handle($inactive, $this->awardKey('inactive'), 100, 'MYR');
    $second = $action->handle($missing, $this->awardKey('missing'), 100, 'MYR');

    expect($first->disposition)->toBe(AffiliateOutcomeDisposition::NoAffiliate)
        ->and($first->conversion)->toBeNull()
        ->and($second->disposition)->toBe(AffiliateOutcomeDisposition::NoAffiliate)
        ->and($second->conversion)->toBeNull()
        ->and(AffiliateConversion::query()->where('affiliate_id', $inactive->getKey())->count())->toBe(0)
        ->and(cashPostBalanceFor($inactive, 'MYR'))->toBeNull();
});

test('identical retry replays the frozen row with zero new effects and a silent second call', function (): void {
    Event::fake([AffiliateConversionRecorded::class, HoldingShortfallDetected::class, FraudSignalDetected::class]);
    bindCashPostAllow();
    $recipient = $this->makeRecipient();
    $key = $this->awardKey('replay');
    $action = app(RecordAffiliateAward::class);

    $first = $action->handle($recipient, $key, 900, 'MYR', ['source_ref' => 'ledger-1']);
    $storedBefore = AffiliateConversion::query()->whereKey($first->conversion->id)->firstOrFail();
    $updatedBefore = $storedBefore->updated_at->toIso8601String();

    usleep(1500);

    $second = $action->handle($recipient, $key, 900, 'MYR', ['source_ref' => 'ledger-1']);

    expect($second->disposition)->toBe(AffiliateOutcomeDisposition::Replayed)
        ->and($second->conversion?->id)->toBe($first->conversion?->id)
        ->and($second->conversion?->affiliateId)->toBe((string) $recipient->getKey())
        ->and($second->conversion?->commissionMinor)->toBe(900)
        ->and($second->conversion?->commissionCurrency)->toBe('MYR');

    $storedAfter = AffiliateConversion::query()->whereKey($first->conversion->id)->firstOrFail();
    expect($storedAfter->updated_at->toIso8601String())->toBe($updatedBefore)
        ->and(AffiliateConversion::query()->where('idempotency_key', hash('sha256', $key))->count())->toBe(1);

    $balance = cashPostBalanceFor($recipient, 'MYR');
    expect((int) $balance->available_minor)->toBe(900)
        ->and((int) $balance->lifetime_earnings_minor)->toBe(900);

    Event::assertDispatched(AffiliateConversionRecorded::class, 1);
    Event::assertNotDispatched(HoldingShortfallDetected::class);
    Event::assertNotDispatched(FraudSignalDetected::class);
    expect(AffiliateFraudSignal::query()->where('affiliate_id', $recipient->getKey())->count())->toBe(0);
});

test('mismatched recipient owner amount currency or backing fails explicitly without disclosure', function (): void {
    bindCashPostAllow();
    $winner = $this->makeRecipient();
    $winner->forceFill(['owner_type' => 'cashpost-owner', 'owner_id' => (string) Str::uuid()])->save();
    $rival = $this->makeRecipient();
    $rival->forceFill(['owner_type' => 'cashpost-owner', 'owner_id' => (string) Str::uuid()])->save();
    $key = $this->awardKey('mismatch');
    $action = app(RecordAffiliateAward::class);

    $created = $action->handle($winner, $key, 1200, 'MYR');
    $winnerConversionId = $created->conversion->id;

    $attempts = [
        fn (): mixed => $action->handle($rival, $key, 1200, 'MYR'),
        fn (): mixed => $action->handle($winner, $key, 1201, 'MYR'),
        fn (): mixed => $action->handle($winner, $key, 1200, 'USD'),
    ];

    foreach ($attempts as $attempt) {
        try {
            $attempt();
            $this->fail('Mismatch failure expected.');
        } catch (InvalidArgumentException $exception) {
            expect($exception->getMessage())->not->toContain((string) $winner->getKey())
                ->and($exception->getMessage())->not->toContain($winnerConversionId)
                ->and($exception->getMessage())->not->toContain((string) $rival->getKey());
        }
    }

    expect(AffiliateConversion::query()->where('idempotency_key', hash('sha256', $key))->count())->toBe(1)
        ->and(AffiliateConversion::query()->where('affiliate_id', $rival->getKey())->count())->toBe(0)
        ->and(cashPostBalanceFor($rival, 'MYR'))->toBeNull()
        ->and((int) cashPostBalanceFor($winner, 'MYR')->available_minor)->toBe(1200);
});

test('key recorded under another backing type cannot be reused as an award', function (): void {
    bindCashPostAllow();
    $recipient = $this->makeRecipient();
    $key = $this->awardKey('foreign-backing');

    AffiliateConversion::create([
        'idempotency_key' => hash('sha256', $key),
        'affiliate_id' => $recipient->getKey(),
        'affiliate_code' => $recipient->code,
        'conversion_type' => 'outcome_fact',
        'commission_minor' => 1200,
        'commission_currency' => 'MYR',
        'status' => PendingConversion::class,
    ]);

    try {
        app(RecordAffiliateAward::class)->handle($recipient, $key, 1200, 'MYR');
        $this->fail('Backing-type mismatch failure expected.');
    } catch (InvalidArgumentException) {
    }

    expect(AffiliateConversion::query()->where('idempotency_key', hash('sha256', $key))->count())->toBe(1)
        ->and(cashPostBalanceFor($recipient, 'MYR'))->toBeNull();
});

test('caller policy limits reject outside amounts with zero accounting and accept boundaries', function (): void {
    app()->bind(AffiliateAwardPolicy::class, fn (): FixtureAffiliateAwardPolicy => FixtureAffiliateAwardPolicy::allow(1000, 5000));
    $recipient = $this->makeRecipient();
    $action = app(RecordAffiliateAward::class);

    foreach ([999, 5001] as $amount) {
        try {
            $action->handle($recipient, $this->awardKey('policy'), $amount, 'MYR');
            $this->fail('Policy-limit rejection expected.');
        } catch (AffiliateAwardPolicyLimitException $exception) {
            expect($exception->getMessage())->toContain((string) $amount);
        }
    }

    expect(AffiliateConversion::query()->where('affiliate_id', $recipient->getKey())->count())->toBe(0)
        ->and(cashPostBalanceFor($recipient, 'MYR'))->toBeNull();

    $low = $action->handle($recipient, $this->awardKey('policy-low'), 1000, 'MYR');
    $high = $action->handle($recipient, $this->awardKey('policy-high'), 5000, 'MYR');

    expect($low->disposition)->toBe(AffiliateOutcomeDisposition::Created)
        ->and($high->disposition)->toBe(AffiliateOutcomeDisposition::Created)
        ->and((int) cashPostBalanceFor($recipient, 'MYR')->available_minor)->toBe(6000);
});

test('pre-write balance failure prevents any reservation and the retry commits exactly once', function (): void {
    bindCashPostAllow();
    $recipient = $this->makeRecipient();
    $key = $this->awardKey('pre-write');
    $action = app(RecordAffiliateAward::class);

    CashPostingFailureInjector::armOnce();

    try {
        $action->handle($recipient, $key, 1750, 'MYR');
        $this->fail('Injected failure expected.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('cashpost-injected-pre-write-failure');
    }

    expect(AffiliateConversion::query()->where('idempotency_key', hash('sha256', $key))->count())->toBe(0)
        ->and(cashPostBalanceFor($recipient, 'MYR'))->toBeNull();

    $retry = $action->handle($recipient, $key, 1750, 'MYR');

    expect($retry->disposition)->toBe(AffiliateOutcomeDisposition::Created)
        ->and(AffiliateConversion::query()->where('idempotency_key', hash('sha256', $key))->count())->toBe(1)
        ->and((int) cashPostBalanceFor($recipient, 'MYR')->available_minor)->toBe(1750)
        ->and((int) cashPostBalanceFor($recipient, 'MYR')->lifetime_earnings_minor)->toBe(1750);
});

test('post-mutation failure rolls back the conversion and balance writes and the retry reconciles exactly', function (): void {
    bindCashPostAllow();
    $recipient = $this->makeRecipient();
    $action = app(RecordAffiliateAward::class);

    $pendingKey = $this->awardKey('pre-hold');
    $pending = $action->handle($recipient, $pendingKey, 2200, 'MYR', ['status' => PendingConversion::class]);
    expect($pending->disposition)->toBe(AffiliateOutcomeDisposition::Created);

    $before = cashPostBalanceFor($recipient, 'MYR');
    expect((int) $before->available_minor)->toBe(0)
        ->and((int) $before->holding_minor)->toBe(2200)
        ->and((int) $before->lifetime_earnings_minor)->toBe(2200)
        ->and((int) AffiliateConversion::query()->where('idempotency_key', hash('sha256', $pendingKey))->firstOrFail()->held_minor)->toBe(2200);

    $key = $this->awardKey('post-mutation');
    CashPostingFailureInjector::armAfterFirstBalanceWrite();

    try {
        $action->handle($recipient, $key, 1750, 'MYR');
        $this->fail('Injected post-mutation failure expected.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('cashpost-injected-post-mutation-failure');
    }

    expect(CashPostingFailureInjector::$balanceWritesObserved)->toBe(1);

    expect(AffiliateConversion::query()->where('idempotency_key', hash('sha256', $key))->count())->toBe(0);

    $rolledBack = cashPostBalanceFor($recipient, 'MYR');
    expect((int) $rolledBack->available_minor)->toBe(0)
        ->and((int) $rolledBack->holding_minor)->toBe(2200)
        ->and((int) $rolledBack->lifetime_earnings_minor)->toBe(2200)
        ->and((int) AffiliateConversion::query()->where('idempotency_key', hash('sha256', $pendingKey))->firstOrFail()->held_minor)->toBe(2200);

    $retry = $action->handle($recipient, $key, 1750, 'MYR');

    expect($retry->disposition)->toBe(AffiliateOutcomeDisposition::Created)
        ->and(AffiliateConversion::query()->where('idempotency_key', hash('sha256', $key))->count())->toBe(1);

    $after = cashPostBalanceFor($recipient, 'MYR');
    expect((int) $after->available_minor)->toBe(1750)
        ->and((int) $after->holding_minor)->toBe(2200)
        ->and((int) $after->lifetime_earnings_minor)->toBe(3950);
});

test('connection mismatch fails closed with zero effects', function (): void {
    bindCashPostAllow();
    $recipient = $this->makeRecipient();

    config()->set('database.connections.cashpost_other', array_merge(
        config()->get('database.connections.cashpost'),
        ['name' => 'cashpost_other']
    ));
    $recipient->setConnection('cashpost_other');

    try {
        app(RecordAffiliateAward::class)->handle($recipient, $this->awardKey('conn'), 100, 'MYR');
        $this->fail('Connection failure expected.');
    } catch (LogicException) {
    }

    expect(AffiliateConversion::query()->where('affiliate_id', $recipient->getKey())->count())->toBe(0);
});

test('awards create no attribution fraud upline payout or zero-fact mutation', function (): void {
    Event::fake([AffiliateConversionRecorded::class, HoldingShortfallDetected::class, FraudSignalDetected::class]);
    bindCashPostAllow();
    $recipient = $this->makeRecipient();

    $zeroFact = AffiliateConversion::create([
        'idempotency_key' => hash('sha256', $this->awardKey('zero-fact')),
        'affiliate_id' => $recipient->getKey(),
        'affiliate_code' => $recipient->code,
        'conversion_type' => 'share_outcome',
        'commission_minor' => 0,
        'commission_currency' => 'MYR',
        'status' => ApprovedConversion::class,
    ]);
    $zeroBefore = $zeroFact->refresh()->toArray();

    $key = $this->awardKey('clean');
    $result = app(RecordAffiliateAward::class)->handle(
        $recipient,
        $key,
        800,
        'MYR',
        ['metadata' => ['note' => 'clean']]
    );

    expect($result->disposition)->toBe(AffiliateOutcomeDisposition::Created);

    $stored = AffiliateConversion::query()->whereKey($result->conversion->id)->firstOrFail();
    expect($stored->affiliate_attribution_id)->toBeNull()
        ->and($stored->subject_type)->toBeNull()
        ->and($stored->subject_key)->toBeNull()
        ->and($stored->subject_id)->toBeNull()
        ->and($stored->subject_instance)->toBeNull()
        ->and($stored->upline_levels)->toBeNull()
        ->and($stored->affiliate_link_id)->toBeNull()
        ->and($stored->commission_override)->toBeNull()
        ->and($stored->metadata)->toBe(['note' => 'clean'])
        ->and($stored->external_reference)->toBe(mb_substr($key, 0, 120));

    expect(AffiliateAttribution::query()->where('affiliate_id', $recipient->getKey())->count())->toBe(0)
        ->and(AffiliateFraudSignal::query()->where('affiliate_id', $recipient->getKey())->count())->toBe(0)
        ->and(AffiliateUpline::query()->where('descendant_id', $recipient->getKey())->count())->toBe(0)
        ->and(AffiliatePayout::query()->where('payee_id', $recipient->getKey())->count())->toBe(0);

    $zeroAfter = $zeroFact->refresh()->toArray();
    expect($zeroAfter['commission_minor'])->toBe(0)
        ->and($zeroAfter['conversion_type'])->toBe('share_outcome')
        ->and((string) $zeroAfter['updated_at'])->toBe((string) $zeroBefore['updated_at']);

    Event::assertDispatched(AffiliateConversionRecorded::class, 1);
    Event::assertNotDispatched(HoldingShortfallDetected::class);
    Event::assertNotDispatched(FraudSignalDetected::class);
});

test('explicit references channel origin and occurred_at are stored bounded', function (): void {
    bindCashPostAllow();
    $recipient = $this->makeRecipient();
    $occurred = CarbonImmutable::now('UTC')->subHour()->startOfSecond();

    $result = app(RecordAffiliateAward::class)->handle($recipient, $this->awardKey('refs'), 450, 'USD', [
        'external_reference' => 'EXT-9',
        'source_ref' => 'SRC-9',
        'channel' => 'test-channel',
        'origin' => 'test-origin',
        'occurred_at' => $occurred,
    ]);

    $stored = AffiliateConversion::query()->whereKey($result->conversion->id)->firstOrFail();
    expect($stored->external_reference)->toBe('EXT-9')
        ->and($stored->source_ref)->toBe('SRC-9')
        ->and($stored->channel)->toBe('test-channel')
        ->and($stored->origin)->toBe('test-origin')
        ->and($stored->occurred_at->toIso8601String())->toBe($occurred->toIso8601String())
        ->and($stored->owner_type)->toBe($recipient->owner_type)
        ->and((string) $stored->owner_id)->toBe((string) $recipient->owner_id);

    $balance = cashPostBalanceFor($recipient, 'USD');
    expect((int) $balance->available_minor)->toBe(450);
});

test('non-UTC occurred_at round-trips the exact instant as UTC', function (): void {
    bindCashPostAllow();
    $recipient = $this->makeRecipient();
    $occurred = CarbonImmutable::now('Asia/Kuala_Lumpur')->subDay()->startOfSecond();

    $result = app(RecordAffiliateAward::class)->handle($recipient, $this->awardKey('tz'), 450, 'MYR', [
        'occurred_at' => $occurred,
    ]);

    $stored = AffiliateConversion::query()->whereKey($result->conversion->id)->firstOrFail();
    expect($stored->occurred_at->timestamp)->toBe($occurred->timestamp)
        ->and($stored->occurred_at->toIso8601String())->toEndWith('+00:00');
});

test('dispatch_event false suppresses the creation announcement', function (): void {
    Event::fake([AffiliateConversionRecorded::class]);
    bindCashPostAllow();
    $recipient = $this->makeRecipient();

    $result = app(RecordAffiliateAward::class)->handle(
        $recipient,
        $this->awardKey('silent'),
        600,
        'MYR',
        ['dispatch_event' => false]
    );

    expect($result->disposition)->toBe(AffiliateOutcomeDisposition::Created)
        ->and((int) cashPostBalanceFor($recipient, 'MYR')->available_minor)->toBe(600);

    Event::assertNotDispatched(AffiliateConversionRecorded::class);
});

test('award key hashes the exact identity and validates bounds', function (): void {
    $key = AffiliateAwardKey::parse('  cashpost:award:1  ');

    expect((string) $key)->toBe('cashpost:award:1')
        ->and($key->idempotencyKey())->toBe(hash('sha256', 'cashpost:award:1'));

    foreach (['', str_repeat('k', 192), "nul\0key"] as $bad) {
        try {
            AffiliateAwardKey::parse($bad);
            $this->fail('Key validation expected.');
        } catch (InvalidArgumentException) {
        }
    }
});

test('award decisions validate authorization proof and policy limits', function (): void {
    $allow = AffiliateAwardDecision::allow(10, 20, 'ok');
    expect($allow->authorized)->toBeTrue()
        ->and($allow->policyMinMinor)->toBe(10)
        ->and($allow->policyMaxMinor)->toBe(20);

    expect(FixtureAffiliateAwardPolicy::deny()->decide($this->makeRecipient(), new AffiliateAwardProposal('k', 1, 'MYR', ApprovedConversion::class))->authorized)->toBeFalse();

    expect(fn (): AffiliateAwardDecision => AffiliateAwardDecision::deny(''))->toThrow(InvalidArgumentException::class);
    expect(fn (): AffiliateAwardDecision => AffiliateAwardDecision::allow(-1))->toThrow(InvalidArgumentException::class);
    expect(fn (): AffiliateAwardDecision => AffiliateAwardDecision::allow(20, 10))->toThrow(InvalidArgumentException::class);

    $null = new NullAffiliateAwardPolicy;
    expect($null->decide($this->makeRecipient(), new AffiliateAwardProposal('k', 1, 'MYR', ApprovedConversion::class))->authorized)->toBeFalse();
});

test('host authorization runs inside the posting transaction on the award connection', function (): void {
    FixtureAffiliateAwardPolicy::$observe = true;
    bindCashPostAllow();
    $recipient = $this->makeRecipient();
    $key = $this->awardKey('txn-member');

    $result = app(RecordAffiliateAward::class)->handle($recipient, $key, 500, 'MYR');

    expect($result->disposition)->toBe(AffiliateOutcomeDisposition::Created);

    $observed = FixtureAffiliateAwardPolicy::$observedDecisions;
    expect($observed)->not->toBeEmpty();

    foreach ($observed as $seen) {
        expect($seen['transaction_level'])->toBeGreaterThanOrEqual(1)
            ->and($seen['connection'])->toBe('cashpost')
            ->and($seen['recipient'])->toBe((string) $recipient->getKey())
            ->and($seen['award_key'])->toBe($key)
            ->and($seen['observed_attributable'])->toBeTrue();
    }
});

test('identical retry still settles the earned award after recipient deactivation with zero new effects', function (): void {
    Event::fake([AffiliateConversionRecorded::class]);
    bindCashPostAllow();
    $recipient = $this->makeRecipient();
    $key = $this->awardKey('settle-after-deactivate');
    $action = app(RecordAffiliateAward::class);

    $first = $action->handle($recipient, $key, 900, 'MYR');
    expect($first->disposition)->toBe(AffiliateOutcomeDisposition::Created);

    $updatedBefore = AffiliateConversion::query()->whereKey($first->conversion->id)->firstOrFail()->updated_at->toIso8601String();

    Affiliate::query()->whereKey($recipient->getKey())->firstOrFail()->status->transitionTo(Disabled::class);

    usleep(1500);

    $second = $action->handle($recipient->fresh(), $key, 900, 'MYR');

    expect($second->disposition)->toBe(AffiliateOutcomeDisposition::Replayed)
        ->and($second->conversion?->id)->toBe($first->conversion?->id)
        ->and($second->conversion?->commissionMinor)->toBe(900);

    $storedAfter = AffiliateConversion::query()->whereKey($first->conversion->id)->firstOrFail();
    expect($storedAfter->updated_at->toIso8601String())->toBe($updatedBefore)
        ->and(AffiliateConversion::query()->where('idempotency_key', hash('sha256', $key))->count())->toBe(1);

    $balance = cashPostBalanceFor($recipient, 'MYR');
    expect((int) $balance->available_minor)->toBe(900)
        ->and((int) $balance->lifetime_earnings_minor)->toBe(900);

    Event::assertDispatched(AffiliateConversionRecorded::class, 1);

    $third = $action->handle($recipient->fresh(), $this->awardKey('new-after-deactivate'), 100, 'MYR');

    expect($third->disposition)->toBe(AffiliateOutcomeDisposition::NoAffiliate)
        ->and($third->conversion)->toBeNull()
        ->and((int) cashPostBalanceFor($recipient, 'MYR')->available_minor)->toBe(900);
});

test('identical retry still settles without new effects when the host later denies or unbinds', function (): void {
    bindCashPostAllow();
    $recipient = $this->makeRecipient();
    $key = $this->awardKey('settle-after-deny');
    $action = app(RecordAffiliateAward::class);

    $first = $action->handle($recipient, $key, 700, 'MYR');
    expect($first->disposition)->toBe(AffiliateOutcomeDisposition::Created);

    app()->bind(AffiliateAwardPolicy::class, fn (): FixtureAffiliateAwardPolicy => FixtureAffiliateAwardPolicy::deny());

    $second = $action->handle($recipient, $key, 700, 'MYR');
    expect($second->disposition)->toBe(AffiliateOutcomeDisposition::Replayed)
        ->and($second->conversion?->id)->toBe($first->conversion?->id)
        ->and((int) cashPostBalanceFor($recipient, 'MYR')->available_minor)->toBe(700);

    $freshDenied = $action->handle($recipient, $this->awardKey('new-after-deny'), 100, 'MYR');
    expect($freshDenied->disposition)->toBe(AffiliateOutcomeDisposition::Rejected)
        ->and($freshDenied->conversion)->toBeNull();

    app()->offsetUnset(AffiliateAwardPolicy::class);
    expect(app()->bound(AffiliateAwardPolicy::class))->toBeFalse();

    $third = $action->handle($recipient, $key, 700, 'MYR');
    expect($third->disposition)->toBe(AffiliateOutcomeDisposition::Replayed)
        ->and($third->conversion?->id)->toBe($first->conversion?->id);

    $freshUnbound = $action->handle($recipient, $this->awardKey('new-after-unbind'), 100, 'MYR');
    expect($freshUnbound->disposition)->toBe(AffiliateOutcomeDisposition::Rejected)
        ->and($freshUnbound->conversion)->toBeNull()
        ->and((int) cashPostBalanceFor($recipient, 'MYR')->available_minor)->toBe(700);
});
