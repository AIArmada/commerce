<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;

beforeEach(function (): void {
    $this->seedCountry('MY');
    $this->csvPath = tempnam(sys_get_temp_dir(), 'areas') . '.csv';
    file_put_contents($this->csvPath, implode("\n", [
        'source_id,country_code,type,name',
        '1,MY,state,Selangor',
        '2,MY,city,Kuala Lumpur',
    ]));
});

afterEach(function (): void {
    @unlink($this->csvPath);
});

it('imports areas from a csv file via the csv option', function (): void {
    $this->artisan('address:import-areas', [
        '--csv' => $this->csvPath,
        '--source-key' => 'my-csv',
    ])->assertSuccessful();

    expect(AddressArea::where('source', 'my-csv')->count())->toBe(2);
});

it('defaults the csv source key to the filename', function (): void {
    $this->artisan('address:import-areas', ['--csv' => $this->csvPath])
        ->assertSuccessful();

    $expected = pathinfo($this->csvPath, PATHINFO_FILENAME);

    expect(AddressArea::where('source', $expected)->count())->toBe(2);
});

it('creates nothing from a csv file on dry run', function (): void {
    $this->artisan('address:import-areas', [
        '--csv' => $this->csvPath,
        '--source-key' => 'my-csv',
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(AddressArea::where('source', 'my-csv')->count())->toBe(0);
});

it('resolves a registered area source by key', function (): void {
    config()->set('addressing.area_sources', [ArrayAddressAreaSource::class]);
    app()->bind(ArrayAddressAreaSource::class, fn (): ArrayAddressAreaSource => new ArrayAddressAreaSource('registered', []));

    $this->artisan('address:import-areas', ['source' => 'registered'])->assertSuccessful();
});

it('fails when neither a source key nor a csv path is given', function (): void {
    $this->artisan('address:import-areas')->assertFailed();
});

it('fails when the requested source key is not registered', function (): void {
    config()->set('addressing.area_sources', []);

    $this->artisan('address:import-areas', ['source' => 'nope'])->assertFailed();
});

it('fails when the csv file is unreadable', function (): void {
    $this->artisan('address:import-areas', [
        '--csv' => '/nonexistent/areas.csv',
    ])->assertFailed();
});
