<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Settings\ExchangeRateSettings;
use AIArmada\FilamentCommerceSupport\Pages\ManageExchangeRates;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExchangeRatesHostComponent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public ?array $data = [];

    public function render()
    {
        return view('livewire.placeholder');
    }
}

function authorizeExchangeRates(): void
{
    $user = User::query()->create([
        'name' => 'Exchange Rates Manager',
        'email' => 'exchange-rates-manager@example.com',
        'password' => 'secret',
    ]);

    config()->set('filament-commerce-support.exchange_rates.permission', 'manage-exchange-rates');

    Gate::define('manage-exchange-rates', fn (): bool => true);

    Filament::auth()->login($user);
}

it('registers exchange rates navigation from config', function (): void {
    config([
        'filament-commerce-support.navigation.settings_group' => 'Settings',
        'filament-commerce-support.exchange_rates.sort' => 101,
    ]);

    expect(ManageExchangeRates::getNavigationGroup())->toBe('Settings')
        ->and(ManageExchangeRates::getNavigationSort())->toBe(101)
        ->and(ManageExchangeRates::getNavigationLabel())->toBe('Exchange Rates');
});

it('denies access without the configured permission', function (): void {
    config(['filament-commerce-support.exchange_rates.permission' => 'manage-exchange-rates']);

    expect(ManageExchangeRates::canAccess())->toBeFalse();
});

it('exposes base and rates form components', function (): void {
    $page = new ManageExchangeRates('exchange-rates-form-test');
    $schema = $page->form(Schema::make(new ExchangeRatesHostComponent)->statePath('data'));

    expect($schema->getComponent('base'))->not->toBeNull()
        ->and($schema->getComponent('rates'))->not->toBeNull();
});

it('exposes save and snapshot header actions', function (): void {
    $page = new ManageExchangeRates('exchange-rates-actions-test');
    $method = new ReflectionMethod(ManageExchangeRates::class, 'getHeaderActions');

    $names = array_map(fn ($action): string => $action->getName(), $method->invoke($page));

    expect($names)->toContain('save', 'snapshot');
});

it('denies save and snapshot without the configured permission', function (): void {
    config(['filament-commerce-support.exchange_rates.permission' => 'manage-exchange-rates']);

    $page = new ManageExchangeRates('exchange-rates-guard-test');

    expect(fn () => $page->save())->toThrow(HttpException::class)
        ->and(fn () => $page->snapshotRates())->toThrow(HttpException::class);
});

it('reports storage failures on save instead of throwing', function (): void {
    authorizeExchangeRates();

    $settings = mock(ExchangeRateSettings::class, function ($mock): void {
        $mock->base = 'USD';
        $mock->rates = [];
        $mock->history = [];
        $mock->shouldReceive('save')->once()->andThrow(new RuntimeException('storage unavailable'));
    });
    app()->instance(ExchangeRateSettings::class, $settings);

    $page = new ManageExchangeRates('exchange-rates-save-storage-test');
    $page->data = ['base' => 'USD', 'rates' => []];

    $page->save();

    $notifications = collect(sent_filament_notifications());

    expect($notifications->pluck('title')->all())
        ->toContain((string) __('Exchange rates could not be saved. The settings storage is unavailable.'));
});

it('reports storage failures on snapshot instead of throwing', function (): void {
    authorizeExchangeRates();

    $settings = mock(ExchangeRateSettings::class, function ($mock): void {
        $mock->base = 'USD';
        $mock->rates = ['MYR' => 4.7];
        $mock->history = [];
        $mock->shouldReceive('save')->once()->andThrow(new RuntimeException('storage unavailable'));
    });
    app()->instance(ExchangeRateSettings::class, $settings);

    $page = new ManageExchangeRates('exchange-rates-snapshot-storage-test');

    $page->snapshotRates();

    $notifications = collect(sent_filament_notifications());

    expect($notifications->pluck('title')->all())
        ->toContain((string) __('Exchange rate snapshot could not be saved. The settings storage is unavailable.'));
});

it('snapshots rates under an immutable dated key', function (): void {
    authorizeExchangeRates();

    $settings = mock(ExchangeRateSettings::class, function ($mock): void {
        $mock->base = 'USD';
        $mock->rates = ['MYR' => 4.7];
        $mock->history = [];
        $mock->shouldReceive('save')->once()->andReturnSelf();
    });
    app()->instance(ExchangeRateSettings::class, $settings);

    $page = new ManageExchangeRates('exchange-rates-snapshot-date-test');

    $page->snapshotRates();

    expect($settings->history)->toHaveKey(CarbonImmutable::now()->format('Y-m-d'))
        ->and($settings->history[CarbonImmutable::now()->format('Y-m-d')])->toBe(['MYR' => 4.7])
        ->and($page->history)->toBe($settings->history);
});
