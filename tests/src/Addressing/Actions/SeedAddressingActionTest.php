<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressingAction;
use AIArmada\Addressing\Geography\SaintBarthelemy\SaintBarthelemyGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\City;
use AIArmada\Addressing\Models\PostalCode;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\ConsoleSeedProgress;
use AIArmada\Addressing\Support\SeedAddressingSummary;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

beforeEach(function (): void {
    config(['addressing.geography.providers' => [SaintBarthelemyGeographyProvider::class]]);
});

it('seeds every tier for the configured providers', function (): void {
    config(['addressing.seed.full_city_countries' => ['MY']]);

    $result = app(SeedAddressingAction::class)->execute();

    $country = AddressCountry::query()->where('iso2', 'BL')->firstOrFail();

    expect($result)->toHaveKeys(['countries', 'references', 'states', 'cities', 'geographies', 'postal_codes'])
        ->and($result['geographies']['seeded'])->toBe(['BL'])
        ->and($result['postal_codes']['seeded'])->toBe(['BL'])
        ->and(AddressCountry::count())->toBeGreaterThan(200)
        ->and(State::count())->toBeGreaterThan(0)
        ->and(City::count())->toBeGreaterThan(0)
        ->and(City::query()->where('country_code', '!=', 'MY')->count())->toBe(0)
        ->and(State::query()->where('country_id', $country->getKey())->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->getKey())->where('is_active', true)->count())->toBeGreaterThan(0)
        ->and(PostalCode::query()->where('country_code', 'BL')->count())->toBe(1);
});

it('reports every step phase in order with terminals', function (): void {
    config(['addressing.seed.full_city_countries' => ['MY']]);

    $events = [];

    app(SeedAddressingAction::class)->execute(
        function (string $phase, int $done, int $total, ?string $label = null) use (&$events): void {
            $events[] = [$phase, $done, $total, $label];
        },
    );

    $order = [];

    foreach ($events as [$phase, , , $label]) {
        if ($label === null && in_array($phase, ['countries', 'references', 'states', 'cities'], true) && ! in_array($phase, $order, true)) {
            $order[] = $phase;
        }
    }

    $terminals = [];

    foreach (['countries', 'references', 'states', 'cities'] as $phase) {
        $phaseEvents = array_values(array_filter(
            $events,
            fn (array $event): bool => $event[0] === $phase && $event[3] === null,
        ));
        $terminals[$phase] = end($phaseEvents);
    }

    expect($order)->toBe(['countries', 'references', 'states', 'cities'])
        ->and($terminals['countries'][1])->toBe($terminals['countries'][2])
        ->and($terminals['references'][1])->toBe($terminals['references'][2])
        ->and($terminals['states'][1])->toBe($terminals['states'][2])
        ->and($terminals['cities'][1])->toBe($terminals['cities'][2]);
});

it('renders one console bar per step on a full run', function (): void {
    config(['addressing.seed.full_city_countries' => ['MY']]);

    $output = new BufferedOutput;
    $progress = ConsoleSeedProgress::for(new SymfonyStyle(new ArrayInput([]), $output));

    expect($progress)->not->toBeNull();

    app(SeedAddressingAction::class)->execute($progress);

    $text = $output->fetch();

    expect($text)->toContain('countries', 'references', 'states', 'cities', 'BL codes', 'BL links');
});

it('skips postcodes when the seed toggle is off', function (): void {
    config(['addressing.seed.full_city_countries' => ['MY'], 'addressing.seed.postal_codes' => false]);

    $result = app(SeedAddressingAction::class)->execute();

    expect($result['postal_codes']['seeded'])->toBe([])
        ->and(PostalCode::query()->count())->toBe(0);
});

it('summarizes every tier on one line', function (): void {
    $line = SeedAddressingSummary::line([
        'countries' => ['created' => 1, 'updated' => 2, 'skipped' => 3],
        'references' => ['currency_links' => 4, 'timezone_links' => 5],
        'states' => ['created' => 6, 'updated' => 7, 'skipped' => 8],
        'cities' => ['created' => 9, 'updated' => 10, 'skipped' => 11],
        'geographies' => [
            'seeded' => ['MY', 'SG'],
            'skipped' => [],
            'areas' => [
                'MY' => ['created' => 12, 'updated' => 13, 'skipped' => 14],
                'SG' => ['created' => 1, 'updated' => 1, 'skipped' => 1],
            ],
        ],
        'postal_codes' => [
            'seeded' => ['MY'],
            'skipped' => [],
            'codes' => [
                'MY' => ['created' => 12, 'updated' => 3, 'skipped' => 4, 'links' => 5],
            ],
        ],
    ]);

    expect($line)->toBe('Addressing seeded: countries 1 created / 2 updated / 3 skipped; country references 4 currency links / 5 timezone links; states 6 created / 7 updated / 8 skipped; cities 9 created / 10 updated / 11 skipped; country geographies seeded for MY, SG; areas 13 created / 14 updated / 15 skipped; postcodes 12 created / 3 updated / 4 skipped / 5 links.');
});
