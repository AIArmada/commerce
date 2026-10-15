<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Actions\SeedAddressCitiesAction;
use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedAddressCountryReferencesAction;
use AIArmada\Addressing\Actions\SeedAddressStatesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Actions\SeedPostalCodesAction;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Geography\SaintBarthelemy\SaintBarthelemyGeographyProvider;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
use AIArmada\Addressing\Support\ConsoleSeedProgress;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

beforeEach(function (): void {
    $this->seedCountry('MY');
});

it('reports import progress per row with totals', function (): void {
    $events = [];

    app(ImportAddressAreasAction::class)->execute(
        new ArrayAddressAreaSource('areas', [
            new AddressAreaData(source: 'areas', sourceId: 'a-1', countryCode: 'MY', type: 'locality', name: 'One'),
            new AddressAreaData(source: 'areas', sourceId: 'a-2', countryCode: 'MY', type: 'locality', name: 'Two'),
        ]),
        progress: function (string $phase, int $done, int $total) use (&$events): void {
            $events[] = [$phase, $done, $total];
        },
    );

    expect($events)->toBe([['areas', 1, 2], ['areas', 2, 2]]);
});

it('renders one console progress bar per labelled phase', function (): void {
    $output = new BufferedOutput;
    $report = ConsoleSeedProgress::for(new SymfonyStyle(new ArrayInput([]), $output));

    expect($report)->not->toBeNull();

    $report('areas', 1, 2, 'MY areas');
    $report('areas', 2, 2, 'MY areas');
    $report('roles', 1, 1, 'MY roles');

    $text = $output->fetch();

    expect($text)->toContain('MY areas', 'MY roles');
});

it('returns no callback without console output', function (): void {
    expect(ConsoleSeedProgress::for(null))->toBeNull();
});

it('renders a zero-total bar without error', function (): void {
    $output = new BufferedOutput;
    $report = ConsoleSeedProgress::for(new SymfonyStyle(new ArrayInput([]), $output));

    expect($report)->not->toBeNull();

    $report('codes', 0, 0, 'BL codes');

    expect($output->fetch())->toContain('BL codes');
});

it('reports country seeding per row with totals', function (): void {
    $events = [];

    app(SeedAddressCountriesAction::class)->execute(
        function (string $phase, int $done, int $total) use (&$events): void {
            $events[] = [$phase, $done, $total];
        },
    );

    $rows = json_decode(
        file_get_contents(__DIR__ . '/../../../../packages/addressing/resources/data/countries.json'),
        true,
    );

    expect($events)->toHaveCount(count($rows))
        ->and(array_column($events, 0))->each->toBe('countries')
        ->and(array_column($events, 2))->each->toBe(count($rows))
        ->and(end($events))->toBe(['countries', count($rows), count($rows)]);
});

it('reports reference syncing as one phase across both loops', function (): void {
    $events = [];

    app(SeedAddressCountryReferencesAction::class)->execute(
        function (string $phase, int $done, int $total) use (&$events): void {
            $events[] = [$phase, $done, $total];
        },
    );

    $rows = json_decode(
        file_get_contents(__DIR__ . '/../../../../packages/addressing/resources/data/countries.json'),
        true,
    );
    $total = count($rows) * 2;

    expect($events)->toHaveCount($total)
        ->and(array_column($events, 0))->each->toBe('references')
        ->and(end($events))->toBe(['references', $total, $total]);
});

it('reports state seeding per row with totals', function (): void {
    $this->seedCountry('US');

    $events = [];

    app(SeedAddressStatesAction::class)->execute(
        [
            ['name' => 'Alpha', 'state_code' => 'AA', 'country_code' => 'US'],
            ['name' => 'Beta', 'state_code' => 'BB', 'country_code' => 'US'],
        ],
        function (string $phase, int $done, int $total) use (&$events): void {
            $events[] = [$phase, $done, $total];
        },
    );

    expect($events)->toBe([['states', 1, 2], ['states', 2, 2]]);
});

it('reports city seeding per chunk with totals', function (): void {
    $this->seedCountry('US');
    app(SeedAddressStatesAction::class)->execute([
        ['name' => 'California', 'country_code' => 'US', 'state_code' => 'CA'],
    ]);

    $events = [];

    app(SeedAddressCitiesAction::class)->execute(
        [
            ['name' => 'Springfield', 'country_code' => 'US', 'state_code' => 'CA'],
            ['name' => 'Shelbyville', 'country_code' => 'US', 'state_code' => 'CA'],
        ],
        function (string $phase, int $done, int $total) use (&$events): void {
            $events[] = [$phase, $done, $total];
        },
    );

    expect($events)->toBe([['cities', 2, 2]]);
});

it('emits terminal codes and links on a no-op postcode reseed', function (): void {
    config(['addressing.geography.providers' => [SaintBarthelemyGeographyProvider::class]]);

    $this->seedCountry('BL');
    app(SeedCountryGeographiesAction::class)->execute('BL');
    app(SeedPostalCodesAction::class)->execute('BL');

    $events = [];

    app(SeedPostalCodesAction::class)->execute(
        'BL',
        function (string $phase, int $done, int $total, ?string $label = null) use (&$events): void {
            $events[] = [$phase, $done, $total, $label];
        },
    );

    expect($events)->toContain(['codes', 0, 0, 'BL codes'], ['links', 0, 0, 'BL links']);
});
