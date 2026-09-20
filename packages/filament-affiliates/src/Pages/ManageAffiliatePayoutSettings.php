<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages;

use AIArmada\Affiliates\Settings\AffiliatePayoutSettings;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;
use UnitEnum;

class ManageAffiliatePayoutSettings extends Page
{
    public ?array $data = [];

    private bool $settingsFallbackWarned = false;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Payout Settings';

    protected static ?string $slug = 'affiliate-payout-settings';

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.payout-settings';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-affiliates.pages.navigation_sort.payout_settings');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function canAccess(): bool
    {
        return FilamentPermission::hasAnyAbility(['affiliate.payout', 'affiliates.payout.update']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string | Htmlable
    {
        return __('Payout Settings');
    }

    public function mount(): void
    {
        $settings = $this->resolveSettings();

        $this->data = [
            'minimum_amount' => $settings->minimumAmount,
            'minimum_amounts_by_currency' => $settings->minimumAmountsByCurrency,
        ];

        $this->getSchema('form')?->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('minimum_amount')
                    ->label(__('Global minimum (minor units)'))
                    ->helperText(__('Applied in each balance currency when no per-currency minimum is set.'))
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(1),

                KeyValue::make('minimum_amounts_by_currency')
                    ->label(__('Per-currency minimums (minor units)'))
                    ->helperText(__('A single global minimum cannot serve every currency: 5,000 minor units means something different in MYR and USD.'))
                    ->keyLabel(__('Currency'))
                    ->valueLabel(__('Minimum (minor units)'))
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var array<string, mixed> $state */
        $state = $this->getSchema('form')?->getState() ?? $this->data ?? [];

        [$map, $dropped] = $this->normalizeMap($state['minimum_amounts_by_currency'] ?? []);

        $settings = $this->resolveSettings();
        $settings->minimumAmount = max(0, (int) ($state['minimum_amount'] ?? 0));
        $settings->minimumAmountsByCurrency = $map;
        $settings->save();

        if ($dropped !== []) {
            Notification::make()
                ->title(__('Some entries were skipped (need a 3-letter code and a non-negative integer).'))
                ->body(implode(', ', $dropped))
                ->warning()
                ->send();
        }

        Notification::make()
            ->title(__('Payout settings saved'))
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

    protected function resolveSettings(): AffiliatePayoutSettings
    {
        try {
            return app(AffiliatePayoutSettings::class);
        } catch (Throwable) {
            // Settings storage is unavailable (missing table or rows): degrade to
            // config-backed in-memory values instead of 500ing, and never write
            // during reads. A later save() upserts the rows when the table exists.
            if (! $this->settingsFallbackWarned) {
                $this->settingsFallbackWarned = true;

                Notification::make()
                    ->title(__('Payout settings storage is unavailable; showing config values.'))
                    ->warning()
                    ->send();
            }

            /** @var array<string, mixed> $map */
            $map = config('affiliates.payouts.minimum_amounts_by_currency', []);

            return new AffiliatePayoutSettings([
                'minimumAmount' => (int) config('affiliates.payouts.minimum_amount', 5000),
                'minimumAmountsByCurrency' => $map,
            ]);
        }
    }

    /**
     * @return array{0: array<string, int>, 1: list<string>}
     */
    private function normalizeMap(mixed $map): array
    {
        $clean = [];
        $dropped = [];

        foreach (is_array($map) ? $map : [] as $code => $value) {
            $code = mb_strtoupper(mb_trim((string) $code));

            if (preg_match('/^[A-Z]{3}$/', $code) !== 1 || ! is_numeric($value) || (int) $value < 0) {
                $dropped[] = $code !== '' ? $code : '(blank)';

                continue;
            }

            $clean[$code] = (int) $value;
        }

        return [$clean, $dropped];
    }
}
