<?php

declare(strict_types=1);

use AIArmada\Addressing\Geography\SaintKittsAndNevis\SaintKittsAndNevisGeographyProvider;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Support\CsvPostalCodeSource;

it('ships 14 parishes under the two island states with parent links', function (): void {
    $areas = app(SaintKittsAndNevisGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'parish'))->toHaveCount(14)
        ->and($byId->get('kn:parish:christ-church-nichola-town')->parentSourceId)->toBe('kn:state:saint-kitts');
});

it('ships 92 villages under their parishes with village roles', function (): void {
    $provider = app(SaintKittsAndNevisGeographyProvider::class);
    $areas = $provider->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    expect($areas->where('type', 'village'))->toHaveCount(92)
        ->and($byId->get('kn:village:sandy-point-town')->parentSourceId)->toBe('kn:parish:saint-anne-sandy-point')
        ->and($byId->get('kn:village:saint-james-windward:camps')->parentSourceId)->toBe('kn:parish:saint-james-windward');

    $roles = $provider->areaRoles(new AddressCountry);

    expect($roles['kn:village:sandy-point-town'][0]['role'])->toBe('village')
        ->and($roles['kn:parish:saint-anne-sandy-point'][0]['role'])->toBe('parish');
});

it('files disputed villages per the parish articles', function (): void {
    $areas = app(SaintKittsAndNevisGeographyProvider::class)->addressAreaSource()->areas()->collect();
    $byId = $areas->keyBy->sourceId;

    // Lodge is claimed by both neighbours; Christ Church lists Lodge Village.
    expect($byId->get('kn:village:lodge-village')->parentSourceId)->toBe('kn:parish:christ-church-nichola-town')
        ->and($byId->get('kn:village:new-road')->parentSourceId)->toBe('kn:parish:saint-peter-basseterre')
        ->and($byId->get('kn:village:keys')->parentSourceId)->toBe('kn:parish:saint-mary-cayon');
});

it('links KN0111 Cayon-primary over the Keys/Canada straddle', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('KN', $dir . '/saint-kitts-and-nevis-postal-codes.csv', $dir . '/saint-kitts-and-nevis-postal-code-areas.csv', 'aiarmada.addressing.saint-kitts-and-nevis');

    $legs = $source->postalCodes()->collect()->where('code', 'KN0111');

    // post.kn district: Keys cluster (5 Cayon mentions + Keys village anchor)
    // outweighs Canada Estate (St Peter, 1 mention).
    expect($legs)->toHaveCount(2)
        ->and($legs->where('isPrimary', true)->pluck('areaSourceId')->all())->toBe(['kn:parish:saint-mary-cayon'])
        ->and($legs->where('isPrimary', false)->pluck('areaSourceId')->all())->toBe(['kn:parish:saint-peter-basseterre']);
});

it('pins the remaining six dual-leg codes', function (): void {
    $dir = __DIR__ . '/../../../../packages/addressing/resources/geography';
    $source = new CsvPostalCodeSource('KN', $dir . '/saint-kitts-and-nevis-postal-codes.csv', $dir . '/saint-kitts-and-nevis-postal-code-areas.csv', 'aiarmada.addressing.saint-kitts-and-nevis');

    $postcodes = $source->postalCodes()->collect();
    $byCode = $postcodes->groupBy->code;
    $legs = static fn (string $code): array => [
        $byCode->get($code)->firstWhere('isPrimary', true)->areaSourceId,
        $byCode->get($code)->firstWhere('isPrimary', false)->areaSourceId,
    ];

    expect($legs('KN0108'))->toBe(['kn:parish:saint-peter-basseterre', 'kn:parish:saint-george-basseterre']) // Lime Kiln/Buckley's boundary
        ->and($legs('KN0202'))->toBe(['kn:parish:trinity-palmetto-point', 'kn:parish:saint-thomas-middle-island']) // Old Road East
        ->and($legs('KN0403'))->toBe(['kn:parish:saint-john-capisterre', 'kn:parish:saint-paul-capisterre']) // Newton Ground
        ->and($legs('KN0501'))->toBe(['kn:parish:saint-john-capisterre', 'kn:parish:christ-church-nichola-town']) // Mansion/Christ Church
        ->and($legs('KN0802'))->toBe(['kn:parish:saint-paul-charlestown', 'kn:parish:saint-john-figtree']) // Bath
        ->and($legs('KN1201'))->toBe(['kn:parish:saint-thomas-lowland', 'kn:parish:saint-paul-charlestown']); // Craddocks straddle
});
