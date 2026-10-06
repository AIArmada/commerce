<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SearchAddressAreasAction;
use AIArmada\Addressing\Geography\Georgia\GeorgiaGeographyProvider;
use AIArmada\Addressing\Models\AddressAreaRole;
use AIArmada\Addressing\Support\CountryAddressProfileResolver;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships four self-governing cities as level-2 areas under regions', function (): void {
    $areas = app(GeorgiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;
    $cities = $areas->where('type', 'city');

    expect($cities->pluck('sourceId')->sort()->values()->all())->toBe([
        'ge:city:batumi',
        'ge:city:kutaisi',
        'ge:city:poti',
        'ge:city:rustavi',
        'ge:city:tbilisi',
    ])
        ->and($byId->get('ge:city:tbilisi')->level)->toBe(1)
        ->and($byId->get('ge:city:tbilisi')->parentSourceId)->toBeNull()
        ->and($cities->where('level', 2)->count())->toBe(4)
        ->and($byId->get('ge:city:batumi')->parentSourceId)->toBe('ge:autonomous_republic:adjara');
});

it('seeds consistently and resolves level-2 cities through the municipality role', function (): void {
    expect($this->seedProviderConsistently(GeorgiaGeographyProvider::class))->toBe([]);

    // 64 municipalities + 17 districts + 4 self-governing cities.
    expect(AddressAreaRole::query()->where('role', 'municipality')->count())->toBe(85);

    $resolver = app(CountryAddressProfileResolver::class);

    expect($resolver->definitionForRole('GE', 'municipality'))->not->toBeNull();

    $search = app(SearchAddressAreasAction::class);

    expect($search->execute(query: 'Batumi', countryCode: 'GE', role: 'municipality')->pluck('name')->all())
        ->toContain('Batumi')
        ->and($search->execute(query: 'Gagra', countryCode: 'GE', role: 'municipality')->pluck('name')->all())
        ->toContain('Gagra')
        ->and($search->execute(query: 'Keda', countryCode: 'GE', role: 'municipality')->pluck('name')->all())
        ->toContain('Keda');
});

it('keeps Tbilisi on the city role instead of the municipality role', function (): void {
    expect($this->seedProviderConsistently(GeorgiaGeographyProvider::class))->toBe([]);

    $search = app(SearchAddressAreasAction::class);

    expect($search->execute(query: 'Tbilisi', countryCode: 'GE', role: 'city')->pluck('name')->all())
        ->toContain('Tbilisi')
        ->and($search->execute(query: 'Tbilisi', countryCode: 'GE', role: 'municipality')->pluck('name')->all())
        ->not->toContain('Tbilisi');
});

it('types Gali as a district matching its Abkhaz siblings', function (): void {
    $areas = app(GeorgiaGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // Organic Law on Local Self-Government registers only Akhalgori, Eredvi,
    // Kurta, Tighva, Azhara in the occupied territories; the six Abkhaz
    // units stay pre-2006 districts.
    expect($byId->has('ge:municipality:gali'))->toBeFalse()
        ->and($byId->get('ge:district:gali')->type)->toBe('district')
        ->and($byId->get('ge:district:gali')->parentSourceId)->toBe('ge:autonomous_republic:abkhazia')
        ->and($areas->where('type', 'municipality')->count())->toBe(64)
        ->and($areas->where('type', 'district')->count())->toBe(17);
});

it('links Sokhumi-primary 6600 across all six Abkhaz districts', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('GE', $dir . '/georgia-postal-codes.csv', $dir . '/georgia-postal-code-areas.csv', 'aiarmada.addressing.georgia');

    $legs = $source->postalCodes()->collect()->where('code', '6600');

    // gpost.ge: Sokhumi city card + Gagra/Gudauta/Ochamchire/Gali town cards
    // + Gulripshi village cards (Estonka/Dranda/Merkheuli), all 6600.
    expect($legs)->toHaveCount(6)
        ->and($legs->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['ge:district:sokhumi'])
        ->and($legs->where('isPrimary', false)->pluck('areaSourceId')->sort()->values()->all())->toBe([
            'ge:district:gagra',
            'ge:district:gali',
            'ge:district:gudauta',
            'ge:district:gulripshi',
            'ge:district:ochamchire',
        ]);
});

it('links Akhalgori-primary 7300 across the Tskhinvali-region units', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('GE', $dir . '/georgia-postal-codes.csv', $dir . '/georgia-postal-code-areas.csv', 'aiarmada.addressing.georgia');

    $legs = $source->postalCodes()->collect()->where('code', '7300');

    // gpost.ge: Akhalgori daba dual-filed under ცხინვალი + ახალგორის რაიონი;
    // Java/Eredvi/Kurta/Tighva occupied-village cards.
    expect($legs)->toHaveCount(5)
        ->and($legs->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['ge:municipality:akhalgori'])
        ->and($legs->where('isPrimary', false)->pluck('areaSourceId')->sort()->values()->all())->toBe([
            'ge:district:java-district',
            'ge:municipality:eredvi',
            'ge:municipality:kurta',
            'ge:municipality:tighva',
        ]);
});

it('keeps Azhara linkless and Tbilisi codes on the L1 city', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('GE', $dir . '/georgia-postal-codes.csv', $dir . '/georgia-postal-code-areas.csv', 'aiarmada.addressing.georgia');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;

    // Azhara villages carry 6600 in gpost but file under Gulripshi with no
    // second signal: held.
    expect($postcodes->pluck('areaSourceId')->contains('ge:municipality:azhara-upper-abkhazia'))->toBeFalse();

    // Tbilisi's 8 codes link L1 (0190 spans Isani + Samgori districts, so no
    // district refinement).
    foreach (['0114', '0159', '0163', '0167', '0178', '0179', '0186', '0190'] as $code) {
        expect($byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId)->toBe('ge:city:tbilisi');
    }
});
