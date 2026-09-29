<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Affiliates\CreateAffiliate;
use AIArmada\Affiliates\Actions\Affiliates\TrackAffiliateVisit;
use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Enums\FraudSeverity;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\Pending;
use AIArmada\Affiliates\States\PendingConversion;
use AIArmada\Affiliates\States\RejectedConversion;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource\Tables\AffiliatesTable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    Gate::before(fn (?object $user): bool => true);
});

function createOpenTestAffiliate(string $code, string $mode, string $status = Pending::class): Affiliate
{
    return Affiliate::create([
        'code' => $code,
        'name' => "Open Test {$code}",
        'status' => $status,
        'registration_approval_mode' => $mode,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);
}

function createOpenTestAttribution(Affiliate $affiliate, string $cookie): AffiliateAttribution
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

function createOpenTestConversion(Affiliate $affiliate, ?AffiliateAttribution $attribution, int $commissionMinor = 5000, string $status = PendingConversion::class): AffiliateConversion
{
    return AffiliateConversion::create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'affiliate_attribution_id' => $attribution?->getKey(),
        'subtotal_minor' => 50000,
        'value_minor' => 50000,
        'commission_minor' => $commissionMinor,
        'commission_currency' => 'USD',
        'status' => $status,
        'occurred_at' => now()->subMinutes(30),
    ]);
}

function dispatchRecorded(AffiliateConversion $conversion): void
{
    Event::dispatch(new AffiliateConversionRecorded(AffiliateConversionData::fromModel($conversion)));
}

test('open-pending affiliate activates on first qualifying conversion', function (): void {
    $affiliate = createOpenTestAffiliate('OPEN001', 'open');
    $attribution = createOpenTestAttribution($affiliate, 'cookie-open-001');
    $conversion = createOpenTestConversion($affiliate, $attribution);

    dispatchRecorded($conversion);

    $fresh = $affiliate->fresh();

    expect($fresh->status)->toBeInstanceOf(Active::class)
        ->and($fresh->activated_at)->not->toBeNull()
        ->and($fresh->metadata['open_approved_by_conversion'] ?? null)->toBe((string) $conversion->getKey());
});

test('admin-pending affiliate never auto-activates', function (): void {
    $affiliate = createOpenTestAffiliate('OPEN002', 'admin');
    $attribution = createOpenTestAttribution($affiliate, 'cookie-open-002');
    $conversion = createOpenTestConversion($affiliate, $attribution);

    dispatchRecorded($conversion);

    expect($affiliate->fresh()->status)->toBeInstanceOf(Pending::class);
});

test('back-door conversion without attribution never auto-activates', function (): void {
    $affiliate = createOpenTestAffiliate('OPEN004', 'open');
    $conversion = createOpenTestConversion($affiliate, null);

    dispatchRecorded($conversion);

    expect($affiliate->fresh()->status)->toBeInstanceOf(Pending::class);
});

test('conversion attributed to another affiliate never auto-activates', function (): void {
    $affiliate = createOpenTestAffiliate('OPEN005', 'open');
    $other = createOpenTestAffiliate('OPEN005B', 'open');
    $attribution = createOpenTestAttribution($other, 'cookie-open-005');
    $conversion = createOpenTestConversion($affiliate, $attribution);

    dispatchRecorded($conversion);

    expect($affiliate->fresh()->status)->toBeInstanceOf(Pending::class);
});

test('unresolved fraud signal blocks auto-activation', function (): void {
    $affiliate = createOpenTestAffiliate('OPEN006', 'open');
    $attribution = createOpenTestAttribution($affiliate, 'cookie-open-006');
    $conversion = createOpenTestConversion($affiliate, $attribution);

    AffiliateFraudSignal::create([
        'affiliate_id' => $affiliate->id,
        'conversion_id' => $conversion->id,
        'rule_code' => 'FAST_CONVERSION',
        'risk_points' => 45,
        'description' => 'Test signal',
        'severity' => FraudSeverity::High,
        'status' => FraudSignalStatus::Detected,
        'detected_at' => now(),
    ]);

    dispatchRecorded($conversion);

    expect($affiliate->fresh()->status)->toBeInstanceOf(Pending::class);
});

test('stale unresolved fraud signal still blocks auto-activation', function (): void {
    $affiliate = createOpenTestAffiliate('OPEN006B', 'open');
    $attribution = createOpenTestAttribution($affiliate, 'cookie-open-006b');
    $conversion = createOpenTestConversion($affiliate, $attribution);

    AffiliateFraudSignal::create([
        'affiliate_id' => $affiliate->id,
        'conversion_id' => $conversion->id,
        'rule_code' => 'FAST_CONVERSION',
        'risk_points' => 45,
        'description' => 'Test signal',
        'severity' => FraudSeverity::High,
        'status' => FraudSignalStatus::Detected,
        'detected_at' => now()->subDays(31),
    ]);

    dispatchRecorded($conversion);

    expect($affiliate->fresh()->status)->toBeInstanceOf(Pending::class);
});

test('dismissed fraud signal no longer blocks auto-activation', function (): void {
    $affiliate = createOpenTestAffiliate('OPEN007', 'open');
    $attribution = createOpenTestAttribution($affiliate, 'cookie-open-007');
    $conversion = createOpenTestConversion($affiliate, $attribution);

    AffiliateFraudSignal::create([
        'affiliate_id' => $affiliate->id,
        'conversion_id' => $conversion->id,
        'rule_code' => 'FAST_CONVERSION',
        'risk_points' => 45,
        'description' => 'Test signal',
        'severity' => FraudSeverity::High,
        'status' => FraudSignalStatus::Dismissed,
        'detected_at' => now(),
    ]);

    dispatchRecorded($conversion);

    expect($affiliate->fresh()->status)->toBeInstanceOf(Active::class);
});

test('zero-commission conversion never auto-activates', function (): void {
    $affiliate = createOpenTestAffiliate('OPEN008', 'open');
    $attribution = createOpenTestAttribution($affiliate, 'cookie-open-008');
    $conversion = createOpenTestConversion($affiliate, $attribution, 0);

    dispatchRecorded($conversion);

    expect($affiliate->fresh()->status)->toBeInstanceOf(Pending::class);
});

test('rejected conversion never auto-activates', function (): void {
    $affiliate = createOpenTestAffiliate('OPEN009', 'open');
    $attribution = createOpenTestAttribution($affiliate, 'cookie-open-009');
    $conversion = createOpenTestConversion($affiliate, $attribution, 5000, RejectedConversion::class);

    dispatchRecorded($conversion);

    expect($affiliate->fresh()->status)->toBeInstanceOf(Pending::class);
});

test('repeat conversions are idempotent no-ops', function (): void {
    $affiliate = createOpenTestAffiliate('OPEN010', 'open');
    $attribution = createOpenTestAttribution($affiliate, 'cookie-open-010');
    $conversion = createOpenTestConversion($affiliate, $attribution);

    dispatchRecorded($conversion);
    dispatchRecorded($conversion);

    $fresh = $affiliate->fresh();

    expect($fresh->status)->toBeInstanceOf(Active::class)
        ->and($fresh->metadata['open_approved_by_conversion'] ?? null)->toBe((string) $conversion->getKey());
});

test('registration snapshots the approval mode', function (): void {
    config()->set('affiliates.registration.approval_mode', 'open');

    $affiliate = app(CreateAffiliate::class)->handle(['name' => 'Snapshot Mode', 'code' => 'OPEN011']);

    expect($affiliate->registration_approval_mode)->toBe('open')
        ->and($affiliate->status)->toBeInstanceOf(Pending::class);
});

test('unknown approval mode fails fast instead of silently falling back', function (): void {
    config()->set('affiliates.registration.approval_mode', 'manual');

    expect(fn () => app(CreateAffiliate::class)->handle(['name' => 'Bad Mode', 'code' => 'OPEN099']))
        ->toThrow(InvalidArgumentException::class);
});

test('stored approval mode is immutable', function (): void {
    $affiliate = createOpenTestAffiliate('OPEN012', 'open');

    expect(fn () => $affiliate->update(['registration_approval_mode' => 'admin']))
        ->toThrow(LogicException::class);
});

test('raw status writes stamp activated_at', function (): void {
    $affiliate = createOpenTestAffiliate('OPEN013', 'admin');

    $affiliate->update(['status' => Active::class]);

    expect($affiliate->fresh()->activated_at)->not->toBeNull();
});

test('canBeAttributed matrix', function (): void {
    $active = createOpenTestAffiliate('OPEN014A', 'admin', Active::class);
    $openPending = createOpenTestAffiliate('OPEN014B', 'open');
    $adminPending = createOpenTestAffiliate('OPEN014C', 'admin');

    expect($active->canBeAttributed())->toBeTrue()
        ->and($openPending->canBeAttributed())->toBeTrue()
        ->and($adminPending->canBeAttributed())->toBeFalse();
});

test('table approve action activates a pending affiliate', function (): void {
    $affiliate = createOpenTestAffiliate('OPEN020', 'admin');

    expect(AffiliatesTable::approveAffiliate($affiliate))->toBeTrue()
        ->and($affiliate->fresh()->status)->toBeInstanceOf(Active::class)
        ->and($affiliate->fresh()->activated_at)->not->toBeNull();
});

test('table approve action is a no-op for non-pending affiliates', function (): void {
    $affiliate = createOpenTestAffiliate('OPEN021', 'admin', Active::class);

    expect(AffiliatesTable::approveAffiliate($affiliate))->toBeFalse();
});

test('visit tracking admits open-pending and blocks admin-pending', function (): void {
    $open = createOpenTestAffiliate('OPEN015A', 'open');
    $admin = createOpenTestAffiliate('OPEN015B', 'admin');

    $tracker = app(TrackAffiliateVisit::class);

    expect($tracker->handle($open->code, [], 'cookie-open-015a'))->not->toBeNull()
        ->and($tracker->handle($admin->code, [], 'cookie-open-015b'))->toBeNull();
});
