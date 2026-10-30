<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\RecordAffiliateOutcome;
use AIArmada\Affiliates\Enums\AffiliateOutcomeDisposition;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;

function attributionFreeAffiliate(string $code, ?string $purpose = null): Affiliate
{
    return createTestAffiliate(array_filter([
        'code' => $code,
        'purpose' => $purpose,
    ], static fn ($value): bool => $value !== null));
}

function validOutcomeProvenance(string $subjectKey): array
{
    return [
        'subject_key' => $subjectKey,
        'origin' => 'host_signal',
        'actor_user_id' => (string) Str::uuid(),
    ];
}

test('attribution-free outcome with explicit affiliate and provenance records zero-value', function (): void {
    Event::fake([AffiliateConversionRecorded::class]);

    $affiliate = attributionFreeAffiliate('AFO-OK', 'host_internal_only');
    $businessKey = 'afo-test:signal:' . Str::uuid();

    $result = app(RecordAffiliateOutcome::class)->handle(
        null,
        'host_signal',
        $businessKey,
        ['dispatch_event' => true],
        $affiliate,
        validOutcomeProvenance('subject:' . Str::uuid()),
    );

    expect($result->disposition)->toBe(AffiliateOutcomeDisposition::Created)
        ->and($result->conversion)->not->toBeNull()
        ->and((int) $result->conversion->commissionMinor)->toBe(0)
        ->and($result->conversion->affiliateId)->toBe((string) $affiliate->getKey());
});

test('attribution-free outcome rejects missing provenance identity', function (): void {
    $affiliate = attributionFreeAffiliate('AFO-PROV');

    expect(fn () => app(RecordAffiliateOutcome::class)->handle(
        null,
        'host_signal',
        'afo-test:signal:' . Str::uuid(),
        [],
        $affiliate,
        ['origin' => 'host_signal'],
    ))->toThrow(InvalidArgumentException::class, 'subject_key');

    expect(fn () => app(RecordAffiliateOutcome::class)->handle(
        null,
        'host_signal',
        'afo-test:signal:' . Str::uuid(),
        [],
        $affiliate,
        ['subject_key' => 'subject:' . Str::uuid()],
    ))->toThrow(InvalidArgumentException::class, 'origin');
});

test('attribution-free outcome rejects unknown affiliate', function (): void {
    $ghost = new Affiliate;
    $ghost->forceFill(['id' => (string) Str::uuid()]);

    $result = app(RecordAffiliateOutcome::class)->handle(
        null,
        'host_signal',
        'afo-test:signal:' . Str::uuid(),
        ['dispatch_event' => false],
        $ghost,
        validOutcomeProvenance('subject:' . Str::uuid()),
    );

    expect($result->disposition)->toBe(AffiliateOutcomeDisposition::NoAffiliate)
        ->and($result->conversion)->toBeNull();
});

test('provenance wins over payload and persists actor identity', function (): void {
    Event::fake([AffiliateConversionRecorded::class]);

    $affiliate = attributionFreeAffiliate('AFO-PREC', 'host_internal_only');
    $actorId = (string) Str::uuid();
    $subjectKey = 'subject:' . Str::uuid();
    $businessKey = 'afo-test:signal:' . Str::uuid();

    $result = app(RecordAffiliateOutcome::class)->handle(
        null,
        'host_signal',
        $businessKey,
        [
            'dispatch_event' => false,
            'subject_key' => 'payload-loses',
            'origin' => 'payload-origin-loses',
        ],
        $affiliate,
        [
            'subject_key' => $subjectKey,
            'origin' => 'host_signal',
            'actor_user_id' => $actorId,
        ],
    );

    $stored = AffiliateConversion::query()->where('idempotency_key', hash('sha256', $businessKey))->firstOrFail();

    expect($stored->subject_key)->toBe($subjectKey)
        ->and($stored->origin)->toBe('host_signal')
        ->and((string) $stored->actor_user_id)->toBe($actorId);
});

test('attribution-free outcome replays without a second promise', function (): void {
    Event::fake([AffiliateConversionRecorded::class]);

    $affiliate = attributionFreeAffiliate('AFO-REPLAY');
    $businessKey = 'afo-test:signal:' . Str::uuid();
    $provenance = validOutcomeProvenance('subject:' . Str::uuid());

    $action = app(RecordAffiliateOutcome::class);
    $first = $action->handle(null, 'host_signal', $businessKey, ['dispatch_event' => false], $affiliate, $provenance);
    $second = $action->handle(null, 'host_signal', $businessKey, ['dispatch_event' => false], $affiliate, $provenance);

    expect($first->disposition)->toBe(AffiliateOutcomeDisposition::Created)
        ->and($second->disposition)->toBe(AffiliateOutcomeDisposition::Replayed)
        ->and($second->conversion?->id)->toBe($first->conversion?->id)
        ->and(AffiliateConversion::query()->where('idempotency_key', hash('sha256', $businessKey))->count())->toBe(1);
});

test('commercial attribution-free outcome still records commission', function (): void {
    Event::fake([AffiliateConversionRecorded::class]);

    $affiliate = createTestAffiliate(['code' => 'AFO-COMM']);
    $businessKey = 'afo-test:signal:' . Str::uuid();

    $result = app(RecordAffiliateOutcome::class)->handle(
        null,
        'host_signal',
        $businessKey,
        ['dispatch_event' => false, 'subtotal_minor' => 10000, 'value_minor' => 10000, 'commission_minor' => 500],
        $affiliate,
        validOutcomeProvenance('subject:' . Str::uuid()),
    );

    expect($result->disposition)->toBe(AffiliateOutcomeDisposition::Created)
        ->and($result->conversion)->not->toBeNull()
        ->and((int) $result->conversion->commissionMinor)->toBe(500);
});
