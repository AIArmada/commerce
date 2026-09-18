<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Actions\LogAddressResolutionGapAction;
use AIArmada\Addressing\Actions\MatchGapToAreaAction;
use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\ResolutionGap;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();

    app(ImportAddressAreasAction::class)->execute(new ArrayAddressAreaSource('areas', [
        new AddressAreaData(source: 'areas', sourceId: 'kl', countryCode: 'MY', type: 'locality', level: 2, name: 'Wilayah Persekutuan Kuala Lumpur'),
        new AddressAreaData(source: 'areas', sourceId: 'pj', countryCode: 'MY', type: 'locality', level: 2, name: 'Petaling Jaya'),
        new AddressAreaData(source: 'areas', sourceId: 'district', countryCode: 'MY', type: 'district', level: 2, name: 'Petaling'),
        new AddressAreaData(source: 'areas', sourceId: 'deep', countryCode: 'MY', type: 'locality', level: 3, name: 'Deep Locality'),
        new AddressAreaData(source: 'areas', sourceId: 'jkt', countryCode: 'ID', type: 'locality', level: 2, name: 'Jakarta'),
    ]));

    $this->areas = AddressArea::query()->where('source', 'areas')->get()->keyBy('source_id');
});

it('matches a gap by creating a manual alias', function (): void {
    $gap = app(LogAddressResolutionGapAction::class)->execute('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur');

    $result = app(MatchGapToAreaAction::class)->execute($gap, $this->areas['kl'], 'admin@example.com');

    $alias = AddressAreaName::query()->where('address_area_id', $this->areas['kl']->getKey())->firstOrFail();

    expect($result->status)->toBe('matched')
        ->and($result->matched_area_id)->toBe($this->areas['kl']->getKey())
        ->and($result->matched_by)->toBe('admin@example.com')
        ->and($result->matched_at)->not->toBeNull()
        ->and($alias->name)->toBe('Kuala Lumpur')
        ->and($alias->source)->toBe('manual')
        ->and($alias->name_type)->toBe('common')
        ->and($alias->is_preferred)->toBeFalse();
});

it('rejects overlong actor identifiers', function (): void {
    $gap = app(LogAddressResolutionGapAction::class)->execute('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur');

    expect(fn (): ResolutionGap => app(MatchGapToAreaAction::class)->execute($gap, $this->areas['kl'], str_repeat('a', 256)))
        ->toThrow(ValidationException::class, 'must not exceed 255 characters');
});

it('refuses to match non-open gaps', function (): void {
    $match = app(MatchGapToAreaAction::class);

    $matched = app(LogAddressResolutionGapAction::class)->execute('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur');
    $matched->status = 'matched';
    $matched->save();

    $ignored = app(LogAddressResolutionGapAction::class)->execute('google-picker', 'MY', 'postal_locality', 'junk');
    $ignored->status = 'ignored';
    $ignored->save();

    expect(fn (): ResolutionGap => $match->execute($matched, $this->areas['kl']))
        ->toThrow(ValidationException::class, 'Only open resolution gaps can be matched.')
        ->and(fn (): ResolutionGap => $match->execute($ignored, $this->areas['kl']))
        ->toThrow(ValidationException::class, 'Only open resolution gaps can be matched.');
});

it('refuses to match ambiguous gaps', function (): void {
    $gap = app(LogAddressResolutionGapAction::class)->execute('google-picker', 'MY', 'postal_locality', 'Springfield', 'ambiguous');

    expect(fn (): ResolutionGap => app(MatchGapToAreaAction::class)->execute($gap, $this->areas['kl']))
        ->toThrow(ValidationException::class, 'Ambiguous gaps need data cleanup');
});

it('refuses to match custom non-unmatched reasons with an accurate message', function (): void {
    $gap = app(LogAddressResolutionGapAction::class)->execute('google-picker', 'MY', 'postal_locality', 'Springfield', 'timeout');

    expect(fn (): ResolutionGap => app(MatchGapToAreaAction::class)->execute($gap, $this->areas['kl']))
        ->toThrow(ValidationException::class, 'Only gaps with an unmatched reason can be matched');
});

it('refuses to match state-role gaps', function (): void {
    $gap = app(LogAddressResolutionGapAction::class)->execute('google-picker', 'MY', 'state', 'Johor');

    expect(fn (): ResolutionGap => app(MatchGapToAreaAction::class)->execute($gap, $this->areas['kl']))
        ->toThrow(ValidationException::class, 'states have no alias table');
});

it('refuses areas from another country', function (): void {
    $gap = app(LogAddressResolutionGapAction::class)->execute('google-picker', 'MY', 'postal_locality', 'Jakarta');

    expect(fn (): ResolutionGap => app(MatchGapToAreaAction::class)->execute($gap, $this->areas['jkt']))
        ->toThrow(ValidationException::class, 'must belong to the gap country');
});

it('refuses roles the country profile does not define', function (): void {
    $gap = app(LogAddressResolutionGapAction::class)->execute('google-picker', 'MY', 'not_a_role', 'Kuala Lumpur');

    expect(fn (): ResolutionGap => app(MatchGapToAreaAction::class)->execute($gap, $this->areas['kl']))
        ->toThrow(ValidationException::class, 'not defined by the country address profile');
});

it('refuses areas that miss the role type or level', function (): void {
    $match = app(MatchGapToAreaAction::class);

    $typeGap = app(LogAddressResolutionGapAction::class)->execute('google-picker', 'MY', 'postal_locality', 'Petaling');
    $levelGap = app(LogAddressResolutionGapAction::class)->execute('google-picker', 'MY', 'postal_locality', 'Deep Locality');

    expect(fn (): ResolutionGap => $match->execute($typeGap, $this->areas['district']))
        ->toThrow(ValidationException::class, 'does not match the required hierarchy level')
        ->and(fn (): ResolutionGap => $match->execute($levelGap, $this->areas['deep']))
        ->toThrow(ValidationException::class, 'does not match the required hierarchy level');
});

it('refuses when the value already resolves to a different area primary name', function (): void {
    $gap = app(LogAddressResolutionGapAction::class)->execute('google-picker', 'MY', 'postal_locality', 'PETALING JAYA');

    expect(fn (): ResolutionGap => app(MatchGapToAreaAction::class)->execute($gap, $this->areas['kl']))
        ->toThrow(ValidationException::class, 'already resolves to a different area');
});

it('refuses when the value already resolves to a different area alias', function (): void {
    AddressAreaName::query()->create([
        'address_area_id' => $this->areas['pj']->getKey(),
        'name' => 'PJ City',
        'source' => 'manual',
        'name_type' => 'common',
        'is_preferred' => false,
    ]);

    $gap = app(LogAddressResolutionGapAction::class)->execute('google-picker', 'MY', 'postal_locality', 'pj city');

    expect(fn (): ResolutionGap => app(MatchGapToAreaAction::class)->execute($gap, $this->areas['kl']))
        ->toThrow(ValidationException::class, 'already resolves to a different area');
});

it('does not create a duplicate alias when matching to the already-resolving area', function (): void {
    $gap = app(LogAddressResolutionGapAction::class)->execute('google-picker', 'MY', 'postal_locality', 'PETALING JAYA');

    $result = app(MatchGapToAreaAction::class)->execute($gap, $this->areas['pj']);

    expect($result->status)->toBe('matched')
        ->and(AddressAreaName::query()->where('address_area_id', $this->areas['pj']->getKey())->count())->toBe(0);
});
