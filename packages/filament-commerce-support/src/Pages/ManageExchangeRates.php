<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport\Pages;

use AIArmada\CommerceSupport\Settings\ExchangeRateSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Throwable;
use UnitEnum;

class ManageExchangeRates extends Page
{
    public ?array $data = [];

    /** @var array<string, array<string, float>> */
    public array $history = [];

    private bool $settingsFallbackWarned = false;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrows-right-left';

    public static function getNavigationLabel(): string
    {
        return __('Exchange Rates');
    }

    public function getTitle(): string
    {
        return __('Exchange Rates');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-commerce-support.navigation.settings_group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-commerce-support.exchange_rates.sort');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();
        $permission = config('filament-commerce-support.exchange_rates.permission');

        if ($user === null || ! is_string($permission) || $permission === '') {
            return false;
        }

        return Gate::forUser($user)->allows($permission);
    }

    protected string $view = 'filament-commerce-support::pages.manage-exchange-rates';

    public function mount(): void
    {
        $settings = $this->resolveSettings();

        $this->history = $settings->history;
        $this->data = [
            'base' => $settings->base,
            'rates' => $settings->rates,
        ];

        $this->getSchema('form')?->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('base')
                    ->label(__('Base Currency'))
                    ->helperText(__('ISO 4217 code. Rates below are units per one base unit.'))
                    ->required()
                    ->regex('/^[a-zA-Z]{3}$/'),

                KeyValue::make('rates')
                    ->label(__('Rates'))
                    ->helperText(__('Currency code to units per one base unit, e.g. MYR => 4.7 with base USD. Used for reporting only, never for money movement.'))
                    ->keyLabel(__('Currency'))
                    ->valueLabel(__('Units per base'))
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var array<string, mixed> $state */
        $state = $this->getSchema('form')?->getState() ?? $this->data ?? [];

        [$base, $errors] = $this->normalizeBase((string) ($state['base'] ?? ''));
        [$rates, $dropped] = $this->normalizeRates($state['rates'] ?? []);

        if ($errors !== []) {
            Notification::make()
                ->title(__('Invalid base currency'))
                ->body(implode(' ', $errors))
                ->danger()
                ->send();

            return;
        }

        $settings = $this->resolveSettings();
        $settings->base = $base;
        $settings->rates = $rates;
        $settings->save();

        if ($dropped !== []) {
            Notification::make()
                ->title(__('Some rates were skipped (need a 3-letter code and a positive number).'))
                ->body(implode(', ', $dropped))
                ->warning()
                ->send();
        }

        Notification::make()
            ->title(__('Exchange rates saved'))
            ->success()
            ->send();
    }

    public function snapshotRates(): void
    {
        $settings = $this->resolveSettings();

        $history = $settings->history;
        $history[now()->format('Y-m-d')] = $settings->rates;
        ksort($history);

        $settings->history = $history;
        $settings->save();

        $this->history = $history;

        Notification::make()
            ->title(__('Current rates snapshotted for historical reporting'))
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

            Action::make('snapshot')
                ->label(__('Snapshot current rates'))
                ->icon('heroicon-o-camera')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('Stores today\'s rates as a dated snapshot so historical reports never shift with future rates.'))
                ->action('snapshotRates'),
        ];
    }

    protected function resolveSettings(): ExchangeRateSettings
    {
        try {
            return app(ExchangeRateSettings::class);
        } catch (Throwable) {
            // Settings storage is unavailable (missing table or rows): degrade to
            // config-backed in-memory values instead of 500ing, and never write
            // during reads. A later save() upserts the rows when the table exists.
            if (! $this->settingsFallbackWarned) {
                $this->settingsFallbackWarned = true;

                Notification::make()
                    ->title(__('Exchange rate settings storage is unavailable; showing config values.'))
                    ->warning()
                    ->send();
            }

            /** @var array<string, mixed> $rates */
            $rates = config('commerce-support.currency.exchange_rates.rates', []);

            /** @var array<string, mixed> $history */
            $history = config('commerce-support.currency.exchange_rates.history', []);

            return new ExchangeRateSettings([
                'base' => (string) config('commerce-support.currency.exchange_rates.base', 'USD'),
                'rates' => $rates,
                'history' => $history,
            ]);
        }
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private function normalizeBase(string $base): array
    {
        $base = mb_strtoupper(mb_trim($base));

        if (preg_match('/^[A-Z]{3}$/', $base) !== 1) {
            return ['', [__('Base must be a 3-letter currency code.')]];
        }

        return [$base, []];
    }

    /**
     * @return array{0: array<string, float>, 1: list<string>}
     */
    private function normalizeRates(mixed $rates): array
    {
        $clean = [];
        $dropped = [];

        foreach (is_array($rates) ? $rates : [] as $code => $value) {
            $code = mb_strtoupper(mb_trim((string) $code));

            if (preg_match('/^[A-Z]{3}$/', $code) !== 1 || ! is_numeric($value) || (float) $value <= 0) {
                $dropped[] = (string) $code !== '' ? (string) $code : '(blank)';

                continue;
            }

            $clean[$code] = (float) $value;
        }

        return [$clean, $dropped];
    }
}
