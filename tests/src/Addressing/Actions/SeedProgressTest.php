<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\ImportAddressAreasAction;
use AIArmada\Addressing\Data\AddressAreaData;
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
