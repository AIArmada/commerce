<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\ResolutionGapCuration;
use AIArmada\Addressing\Contracts\ResolutionGapReporter;
use AIArmada\Addressing\Models\ResolutionGap;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->log = app(ResolutionGapReporter::class);
});

it('logs a new gap with hits at one', function (): void {
    $gap = ResolutionGap::query()->findOrFail($this->log->report('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur', 'unmatched', ['place_id' => 'abc']));

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
    $first = ResolutionGap::query()->findOrFail($this->log->report('google-picker', 'my', 'postal_locality', '  Kuala   Lumpur '));
    $second = ResolutionGap::query()->findOrFail($this->log->report('google-picker', 'MY', 'postal_locality', 'kuala lumpur', context: ['place_id' => 'xyz']));

    expect(ResolutionGap::query()->count())->toBe(1)
        ->and($second->getKey())->toBe($first->getKey())
        ->and((int) $second->fresh()?->hits)->toBe(2)
        ->and($second->fresh()?->context)->toBe(['place_id' => 'xyz'])
        ->and($second->fresh()?->first_seen_at?->toDateTimeString())->toBe($first->first_seen_at?->toDateTimeString());
});

it('keeps the previous context sample when recurrence passes none', function (): void {
    $this->log->report('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur', context: ['place_id' => 'abc']);
    $gap = ResolutionGap::query()->findOrFail($this->log->report('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur'));

    expect($gap->fresh()?->context)->toBe(['place_id' => 'abc']);
});

it('reopens a matched gap on recurrence', function (): void {
    $gap = ResolutionGap::query()->findOrFail($this->log->report('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur'));
    $gap->status = 'matched';
    $gap->matched_area_id = Str::uuid()->toString();
    $gap->matched_by = 'admin@example.com';
    $gap->matched_at = now();
    $gap->save();

    $reopened = ResolutionGap::query()->findOrFail($this->log->report('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur'));

    expect($reopened->fresh()?->status)->toBe('open')
        ->and($reopened->fresh()?->matched_area_id)->toBeNull()
        ->and($reopened->fresh()?->matched_by)->toBeNull()
        ->and($reopened->fresh()?->matched_at)->toBeNull()
        ->and((int) $reopened->fresh()?->hits)->toBe(2);
});

it('keeps ignored gaps terminal on recurrence', function (): void {
    $gap = ResolutionGap::query()->findOrFail($this->log->report('google-picker', 'MY', 'postal_locality', 'asdf'));
    app(ResolutionGapCuration::class)->ignore((string) $gap->getKey());

    $recurrence = ResolutionGap::query()->findOrFail($this->log->report('google-picker', 'MY', 'postal_locality', 'asdf'));

    expect($recurrence->fresh()?->status)->toBe('ignored')
        ->and((int) $recurrence->fresh()?->hits)->toBe(2);
});

it('treats source, role, and country as distinct dedupe scopes', function (): void {
    $this->log->report('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur');
    $this->log->report('onemap', 'MY', 'postal_locality', 'Kuala Lumpur');
    $this->log->report('google-picker', 'MY', 'administrative_district', 'Kuala Lumpur');
    $this->log->report('google-picker', 'ID', 'postal_locality', 'Kuala Lumpur');

    expect(ResolutionGap::query()->count())->toBe(4);
});

it('rejects blank producer input', function (): void {
    expect(fn (): string => $this->log->report('', 'MY', 'postal_locality', 'Kuala Lumpur'))
        ->toThrow(ValidationException::class)
        ->and(fn (): string => $this->log->report('google-picker', 'MYS', 'postal_locality', 'Kuala Lumpur'))
        ->toThrow(ValidationException::class)
        ->and(fn (): string => $this->log->report('google-picker', 'MY', '', 'Kuala Lumpur'))
        ->toThrow(ValidationException::class)
        ->and(fn (): string => $this->log->report('google-picker', 'MY', 'postal_locality', '   '))
        ->toThrow(ValidationException::class);
});

it('normalizes the reason so match guards see a canonical value', function (): void {
    $gap = ResolutionGap::query()->findOrFail($this->log->report('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur', 'UNMATCHED'));

    expect($gap->reason)->toBe('unmatched');
});

it('rejects values exceeding column lengths', function (): void {
    expect(fn (): string => $this->log->report(str_repeat('s', 51), 'MY', 'postal_locality', 'Kuala Lumpur'))
        ->toThrow(ValidationException::class, 'must not exceed 50 characters')
        ->and(fn (): string => $this->log->report('google-picker', 'MY', str_repeat('r', 51), 'Kuala Lumpur'))
        ->toThrow(ValidationException::class, 'must not exceed 50 characters')
        ->and(fn (): string => $this->log->report('google-picker', 'MY', 'postal_locality', str_repeat('v', 256)))
        ->toThrow(ValidationException::class, 'must not exceed 255 characters')
        ->and(fn (): string => $this->log->report('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur', str_repeat('x', 21)))
        ->toThrow(ValidationException::class, 'must not exceed 20 characters');
});

it('ignores open and matched gaps idempotently', function (): void {
    $ignore = app(ResolutionGapCuration::class);

    $open = ResolutionGap::query()->findOrFail($this->log->report('google-picker', 'MY', 'postal_locality', 'junk value'));
    $ignore->ignore((string) $open->getKey());
    expect($open->refresh()->status)->toBe('ignored');

    $matched = ResolutionGap::query()->findOrFail($this->log->report('google-picker', 'MY', 'postal_locality', 'other junk'));
    $matched->status = 'matched';
    $matched->save();
    $ignore->ignore((string) $matched->getKey());
    expect($matched->refresh()->status)->toBe('ignored');

    $ignore->ignore((string) $open->getKey());
    $again = $open->refresh();
    expect($again->status)->toBe('ignored')
        ->and(ResolutionGap::query()->where('status', 'ignored')->count())->toBe(2);
});
