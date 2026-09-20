<?php

declare(strict_types=1);

use AIArmada\FilamentCommerceSupport\Pages\ManageExchangeRates;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

class ExchangeRatesHostComponent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public ?array $data = [];

    public function render()
    {
        return view('livewire.placeholder');
    }
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
