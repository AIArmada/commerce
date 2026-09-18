<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\IgnoreResolutionGapAction;
use AIArmada\Addressing\Actions\LogAddressResolutionGapAction;
use AIArmada\Addressing\Models\ResolutionGap;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->log = app(LogAddressResolutionGapAction::class);
});

it('logs a new gap with hits at one', function (): void {
    $gap = $this->log->execute('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur', 'unmatched', ['place_id' => 'abc']);

    expect($gap->getKey())->not->toBeNull()
        ->and($gap->source)->toBe('google-picker')
        ->and($gap->country_code)->toBe('MY')
        ->and($gap->role)->toBe('postal_locality')
        ->and($gap->value)->toBe('Kuala Lumpur')
        ->and($gap->normalized)->toBe('kuala lumpur')
        ->and($gap->reason)->toBe('unmatched')
        ->and((int) $gap->hits)->toBe(1)
        ->and($gap->first_seen_at)->not->toBeNull()
        ->and($gap->last_seen_at)->not->toBeNull()
        ->and($gap->context)->toBe(['place_id' => 'abc'])
        ->and($gap->status)->toBe('open');
});

it('dedupes repeats by bumping hits without duplicating rows', function (): void {
    $first = $this->log->execute('google-picker', 'my', 'postal_locality', '  Kuala   Lumpur ');
    $second = $this->log->execute('google-picker', 'MY', 'postal_locality', 'kuala lumpur', context: ['place_id' => 'xyz']);

    expect(ResolutionGap::query()->count())->toBe(1)
        ->and($second->getKey())->toBe($first->getKey())
        ->and((int) $second->fresh()?->hits)->toBe(2)
        ->and($second->fresh()?->context)->toBe(['place_id' => 'xyz'])
        ->and($second->fresh()?->first_seen_at?->toDateTimeString())->toBe($first->first_seen_at?->toDateTimeString());
});

it('keeps the previous context sample when recurrence passes none', function (): void {
    $this->log->execute('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur', context: ['place_id' => 'abc']);
    $gap = $this->log->execute('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur');

    expect($gap->fresh()?->context)->toBe(['place_id' => 'abc']);
});

it('reopens a matched gap on recurrence', function (): void {
    $gap = $this->log->execute('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur');
    $gap->status = 'matched';
    $gap->matched_area_id = Str::uuid()->toString();
    $gap->matched_by = 'admin@example.com';
    $gap->matched_at = now();
    $gap->save();

    $reopened = $this->log->execute('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur');

    expect($reopened->fresh()?->status)->toBe('open')
        ->and($reopened->fresh()?->matched_area_id)->toBeNull()
        ->and($reopened->fresh()?->matched_by)->toBeNull()
        ->and($reopened->fresh()?->matched_at)->toBeNull()
        ->and((int) $reopened->fresh()?->hits)->toBe(2);
});

it('keeps ignored gaps terminal on recurrence', function (): void {
    $gap = $this->log->execute('google-picker', 'MY', 'postal_locality', 'asdf');
    app(IgnoreResolutionGapAction::class)->execute($gap);

    $recurrence = $this->log->execute('google-picker', 'MY', 'postal_locality', 'asdf');

    expect($recurrence->fresh()?->status)->toBe('ignored')
        ->and((int) $recurrence->fresh()?->hits)->toBe(2);
});

it('treats source, role, and country as distinct dedupe scopes', function (): void {
    $this->log->execute('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur');
    $this->log->execute('onemap', 'MY', 'postal_locality', 'Kuala Lumpur');
    $this->log->execute('google-picker', 'MY', 'administrative_district', 'Kuala Lumpur');
    $this->log->execute('google-picker', 'ID', 'postal_locality', 'Kuala Lumpur');

    expect(ResolutionGap::query()->count())->toBe(4);
});

it('rejects blank producer input', function (): void {
    expect(fn (): ResolutionGap => $this->log->execute('', 'MY', 'postal_locality', 'Kuala Lumpur'))
        ->toThrow(ValidationException::class)
        ->and(fn (): ResolutionGap => $this->log->execute('google-picker', 'MYS', 'postal_locality', 'Kuala Lumpur'))
        ->toThrow(ValidationException::class)
        ->and(fn (): ResolutionGap => $this->log->execute('google-picker', 'MY', '', 'Kuala Lumpur'))
        ->toThrow(ValidationException::class)
        ->and(fn (): ResolutionGap => $this->log->execute('google-picker', 'MY', 'postal_locality', '   '))
        ->toThrow(ValidationException::class);
});

it('normalizes the reason so match guards see a canonical value', function (): void {
    $gap = $this->log->execute('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur', 'UNMATCHED');

    expect($gap->reason)->toBe('unmatched');
});

it('rejects values exceeding column lengths', function (): void {
    expect(fn (): ResolutionGap => $this->log->execute(str_repeat('s', 51), 'MY', 'postal_locality', 'Kuala Lumpur'))
        ->toThrow(ValidationException::class, 'must not exceed 50 characters')
        ->and(fn (): ResolutionGap => $this->log->execute('google-picker', 'MY', str_repeat('r', 51), 'Kuala Lumpur'))
        ->toThrow(ValidationException::class, 'must not exceed 50 characters')
        ->and(fn (): ResolutionGap => $this->log->execute('google-picker', 'MY', 'postal_locality', str_repeat('v', 256)))
        ->toThrow(ValidationException::class, 'must not exceed 255 characters')
        ->and(fn (): ResolutionGap => $this->log->execute('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur', str_repeat('x', 21)))
        ->toThrow(ValidationException::class, 'must not exceed 20 characters');
});

it('ignores open and matched gaps idempotently', function (): void {
    $ignore = app(IgnoreResolutionGapAction::class);

    $open = $this->log->execute('google-picker', 'MY', 'postal_locality', 'junk value');
    expect($ignore->execute($open)->status)->toBe('ignored');

    $matched = $this->log->execute('google-picker', 'MY', 'postal_locality', 'other junk');
    $matched->status = 'matched';
    $matched->save();
    expect($ignore->execute($matched)->status)->toBe('ignored');

    $again = $ignore->execute($open->fresh() ?? $open);
    expect($again->status)->toBe('ignored')
        ->and(ResolutionGap::query()->where('status', 'ignored')->count())->toBe(2);
});
