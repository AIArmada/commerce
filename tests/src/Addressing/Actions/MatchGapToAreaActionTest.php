<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\AddressAreaImporter;
use AIArmada\Addressing\Contracts\ResolutionGapCuration;
use AIArmada\Addressing\Contracts\ResolutionGapReporter;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Features\ResolutionGaps\Models\ResolutionGap;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seedCountry('MY');
    $this->seedCountry('ID');

    app(AddressAreaImporter::class)->execute(new ArrayAddressAreaSource('areas', [
        new AddressAreaData(source: 'areas', sourceId: 'kl', countryCode: 'MY', type: 'locality', level: 2, name: 'Wilayah Persekutuan Kuala Lumpur'),
        new AddressAreaData(source: 'areas', sourceId: 'pj', countryCode: 'MY', type: 'locality', level: 2, name: 'Petaling Jaya'),
        new AddressAreaData(source: 'areas', sourceId: 'district', countryCode: 'MY', type: 'district', level: 2, name: 'Petaling'),
        new AddressAreaData(source: 'areas', sourceId: 'town', countryCode: 'MY', type: 'locality', level: 3, name: 'District Town'),
        new AddressAreaData(source: 'areas', sourceId: 'deep', countryCode: 'MY', type: 'locality', level: 5, name: 'Deep Locality'),
        new AddressAreaData(source: 'areas', sourceId: 'jkt', countryCode: 'ID', type: 'locality', level: 2, name: 'Jakarta'),
    ]));

    $this->areas = AddressArea::query()->where('source', 'areas')->get()->keyBy('source_id');
});

it('matches a gap by creating a manual alias', function (): void {
    $gap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur'));

    app(ResolutionGapCuration::class)->match((string) $gap->getKey(), (string) $this->areas['kl']->getKey(), 'admin@example.com');
    $result = $gap->refresh();

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
    $gap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur'));

    expect(fn () => app(ResolutionGapCuration::class)->match((string) $gap->getKey(), (string) $this->areas['kl']->getKey(), str_repeat('a', 256)))
        ->toThrow(ValidationException::class, 'must not exceed 255 characters');
});

it('refuses to match non-open gaps', function (): void {
    $match = app(ResolutionGapCuration::class);

    $matched = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', 'Kuala Lumpur'));
    $matched->status = 'matched';
    $matched->save();

    $ignored = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', 'junk'));
    $ignored->status = 'ignored';
    $ignored->save();

    expect(fn () => $match->match((string) $matched->getKey(), (string) $this->areas['kl']->getKey()))
        ->toThrow(ValidationException::class, 'Only open resolution gaps can be matched.')
        ->and(fn () => $match->match((string) $ignored->getKey(), (string) $this->areas['kl']->getKey()))
        ->toThrow(ValidationException::class, 'Only open resolution gaps can be matched.');
});

it('refuses to match ambiguous gaps', function (): void {
    $gap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', 'Springfield', 'ambiguous'));

    expect(fn () => app(ResolutionGapCuration::class)->match((string) $gap->getKey(), (string) $this->areas['kl']->getKey()))
        ->toThrow(ValidationException::class, 'Ambiguous gaps need data cleanup');
});

it('refuses to match custom non-unmatched reasons with an accurate message', function (): void {
    $gap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', 'Springfield', 'timeout'));

    expect(fn () => app(ResolutionGapCuration::class)->match((string) $gap->getKey(), (string) $this->areas['kl']->getKey()))
        ->toThrow(ValidationException::class, 'Only gaps with an unmatched reason can be matched');
});

it('refuses to match state-role gaps', function (): void {
    $gap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'state', 'Johor'));

    expect(fn () => app(ResolutionGapCuration::class)->match((string) $gap->getKey(), (string) $this->areas['kl']->getKey()))
        ->toThrow(ValidationException::class, 'states have no alias table');
});

it('refuses areas from another country', function (): void {
    $gap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', 'Jakarta'));

    expect(fn () => app(ResolutionGapCuration::class)->match((string) $gap->getKey(), (string) $this->areas['jkt']->getKey()))
        ->toThrow(ValidationException::class, 'must belong to the gap country');
});

it('refuses roles the country profile does not define', function (): void {
    $gap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'not_a_role', 'Kuala Lumpur'));

    expect(fn () => app(ResolutionGapCuration::class)->match((string) $gap->getKey(), (string) $this->areas['kl']->getKey()))
        ->toThrow(ValidationException::class, 'not defined by the country address profile');
});

it('refuses areas that miss the role type or level', function (): void {
    $match = app(ResolutionGapCuration::class);

    $typeGap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', 'Petaling'));
    $levelGap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', 'Deep Locality'));

    expect(fn () => $match->match((string) $typeGap->getKey(), (string) $this->areas['district']->getKey()))
        ->toThrow(ValidationException::class, 'does not match the required hierarchy level')
        ->and(fn () => $match->match((string) $levelGap->getKey(), (string) $this->areas['deep']->getKey()))
        ->toThrow(ValidationException::class, 'does not match the required hierarchy level');
});

it('matches gaps to district-parented level-3 localities', function (): void {
    $gap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', 'District Town'));

    app(ResolutionGapCuration::class)->match((string) $gap->getKey(), (string) $this->areas['town']->getKey());
    $result = $gap->refresh();

    expect($result->status)->toBe('matched')
        ->and($result->matched_area_id)->toBe($this->areas['town']->getKey());
});

it('refuses when the value already resolves to a different area primary name', function (): void {
    $gap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', 'PETALING JAYA'));

    expect(fn () => app(ResolutionGapCuration::class)->match((string) $gap->getKey(), (string) $this->areas['kl']->getKey()))
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

    $gap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', 'pj city'));

    expect(fn () => app(ResolutionGapCuration::class)->match((string) $gap->getKey(), (string) $this->areas['kl']->getKey()))
        ->toThrow(ValidationException::class, 'already resolves to a different area');
});

it('does not create a duplicate alias when matching to the already-resolving area', function (): void {
    $gap = ResolutionGap::query()->findOrFail(app(ResolutionGapReporter::class)->report('google-picker', 'MY', 'postal_locality', 'PETALING JAYA'));

    app(ResolutionGapCuration::class)->match((string) $gap->getKey(), (string) $this->areas['pj']->getKey());
    $result = $gap->refresh();

    expect($result->status)->toBe('matched')
        ->and(AddressAreaName::query()->where('address_area_id', $this->areas['pj']->getKey())->count())->toBe(0);
});
