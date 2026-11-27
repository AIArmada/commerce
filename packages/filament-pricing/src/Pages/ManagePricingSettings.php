<?php

declare(strict_types=1);

namespace AIArmada\FilamentPricing\Pages;

use AIArmada\FilamentPricing\Support\PricingSettingsAccess;
use AIArmada\Pricing\Actions\UpdatePricingSettings;
use AIArmada\Pricing\Settings\PricingSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Filament settings page for managing pricing configuration.
 */
final class ManagePricingSettings extends Page
{
    public ?array $data = [];

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-currency-dollar';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-pricing.navigation.settings_group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-pricing.pages.navigation_sort.settings');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function canAccess(): bool
    {
        return parent::canAccess() && PricingSettingsAccess::allows();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** @var view-string */
    protected string $view = 'filament-pricing::pages.manage-pricing-settings';

    public static function getNavigationLabel(): string
    {
        return __('Pricing Settings');
    }

    public function getTitle(): string
    {
        return __('Pricing Settings');
    }

    public function mount(): void
    {
        $settings = app(PricingSettings::class);

        $this->data = [
            'defaultCurrency' => $settings->defaultCurrency,
            'decimalPlaces' => $settings->decimalPlaces,
            'roundingMode' => $settings->roundingMode,
            'pricesIncludeTax' => $settings->pricesIncludeTax,
            'minimumOrderValue' => $settings->minimumOrderValue,
            'maximumOrderValue' => $settings->maximumOrderValue,
            'promotionalPricingEnabled' => $settings->promotionalPricingEnabled,
            'tieredPricingEnabled' => $settings->tieredPricingEnabled,
            'customerGroupPricingEnabled' => $settings->customerGroupPricingEnabled,
        ];

        $this->getSchema('form')?->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema(static::getFormComponents())
            ->statePath('data');
    }

    /**
     * @return array<int, Component>
     */
    public static function getFormComponents(): array
    {
        return [
            Select::make('defaultCurrency')
                ->label(__('Default Currency'))
                ->options([
                    'MYR' => 'Malaysian Ringgit (MYR)',
                    'USD' => 'US Dollar (USD)',
                    'EUR' => 'Euro (EUR)',
                    'GBP' => 'British Pound (GBP)',
                    'SGD' => 'Singapore Dollar (SGD)',
                    'THB' => 'Thai Baht (THB)',
                    'IDR' => 'Indonesian Rupiah (IDR)',
                    'PHP' => 'Philippine Peso (PHP)',
                ])
                ->required()
                ->helperText(__('The default currency for all pricing.')),

            TextInput::make('decimalPlaces')
                ->label(__('Decimal Places'))
                ->numeric()
                ->minValue(0)
                ->maxValue(4)
                ->required()
                ->helperText(__('Number of decimal places for price display.')),

            Select::make('roundingMode')
                ->label(__('Rounding Mode'))
                ->options([
                    'up' => __('Round Up'),
                    'down' => __('Round Down'),
                    'half_up' => __('Round Half Up'),
                    'half_down' => __('Round Half Down'),
                ])
                ->required()
                ->helperText(__('How to round calculated prices.')),

            Toggle::make('pricesIncludeTax')
                ->label(__('Prices Include Tax'))
                ->helperText(__('Enable if your prices already include tax.')),

            TextInput::make('minimumOrderValue')
                ->label(__('Minimum Order Value'))
                ->numeric()
                ->minValue(0)
                ->suffix('cents')
                ->helperText(__('Minimum order value in minor units (cents).')),

            TextInput::make('maximumOrderValue')
                ->label(__('Maximum Order Value'))
                ->numeric()
                ->minValue(0)
                ->suffix('cents')
                ->helperText(__('Maximum order value in minor units (cents).')),

            Toggle::make('promotionalPricingEnabled')
                ->label(__('Promotional Pricing'))
                ->helperText(__('Enable promotional/sale pricing features.')),

            Toggle::make('tieredPricingEnabled')
                ->label(__('Tiered Pricing'))
                ->helperText(__('Enable quantity-based tiered pricing.')),

            Toggle::make('customerGroupPricingEnabled')
                ->label(__('Customer Group Pricing'))
                ->helperText(__('Enable different prices for customer groups.')),
        ];
    }

    public function save(): void
    {
        abort_unless(PricingSettingsAccess::allows(), 403);
        /** @var array<string, mixed> $state */
        $state = $this->data ?? [];

        app(UpdatePricingSettings::class)->execute($state);

        Notification::make()
            ->title(__('Saved'))
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Save'))
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action('save'),
        ];
    }
}
