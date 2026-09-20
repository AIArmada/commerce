<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages;

use AIArmada\Affiliates\Settings\AffiliateBonusSettings;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;
use UnitEnum;

class ManageAffiliateBonusSettings extends Page
{
    public ?array $data = [];

    private bool $settingsFallbackWarned = false;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Bonus Settings';

    protected static ?string $slug = 'affiliate-bonus-settings';

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.bonus-settings';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-affiliates.pages.navigation_sort.bonus_settings');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function canAccess(): bool
    {
        return FilamentPermission::hasAnyAbility(['affiliates.commission.update', 'affiliate.update']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string | Htmlable
    {
        return __('Bonus Settings');
    }

    public function mount(): void
    {
        $settings = $this->resolveSettings();

        $this->data = [
            'top_performer_enabled' => $settings->topPerformerEnabled,
            'top_performer_positions' => $settings->topPerformerPositions,
            'top_performer_min_revenue' => $settings->topPerformerMinRevenue,
            'top_performer_min_revenue_currency' => $settings->topPerformerMinRevenueCurrency,
            'recruitment_enabled' => $settings->recruitmentEnabled,
            'recruitment_bonus_per_recruit' => $settings->recruitmentBonusPerRecruit,
            'recruitment_min_recruits' => $settings->recruitmentMinRecruits,
            'recruitment_max_bonus' => $settings->recruitmentMaxBonus,
            'consistency_enabled' => $settings->consistencyEnabled,
            'consistency_bonus_amount' => $settings->consistencyBonusAmount,
            'consistency_min_weeks' => $settings->consistencyMinWeeks,
            'consistency_min_conversions_per_week' => $settings->consistencyMinConversionsPerWeek,
            'growth_enabled' => $settings->growthEnabled,
            'growth_bonus_amount' => $settings->growthBonusAmount,
            'growth_min_growth_percent' => $settings->growthMinGrowthPercent,
            'growth_min_previous_revenue' => $settings->growthMinPreviousRevenue,
            'growth_min_previous_revenue_currency' => $settings->growthMinPreviousRevenueCurrency,
        ];

        $this->getSchema('form')?->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        $currencies = [
            'USD' => 'USD',
            'MYR' => 'MYR',
            'SGD' => 'SGD',
            'IDR' => 'IDR',
        ];

        return $schema
            ->schema([
                Section::make('Top Performer')
                    ->description('Monthly leaderboard top 3. Amounts pay in each affiliate\'s own currency; the revenue floor is measured in the threshold currency.')
                    ->schema([
                        Toggle::make('top_performer_enabled')
                            ->label('Enabled'),

                        KeyValue::make('top_performer_positions')
                            ->label('Payouts by position (minor units)')
                            ->keyLabel('Position')
                            ->valueLabel('Amount (minor units)'),

                        TextInput::make('top_performer_min_revenue')
                            ->label('Minimum revenue (minor units)')
                            ->numeric()
                            ->minValue(0)
                            ->step(1),

                        Select::make('top_performer_min_revenue_currency')
                            ->label('Threshold currency')
                            ->options($currencies),
                    ])
                    ->columns(2),

                Section::make('Recruitment')
                    ->description('Per-recruit payout for active recruits created in the month, capped per recruiter.')
                    ->schema([
                        Toggle::make('recruitment_enabled')
                            ->label('Enabled'),

                        TextInput::make('recruitment_bonus_per_recruit')
                            ->label('Bonus per recruit (minor units)')
                            ->numeric()
                            ->minValue(0)
                            ->step(1),

                        TextInput::make('recruitment_min_recruits')
                            ->label('Minimum recruits')
                            ->numeric()
                            ->minValue(1)
                            ->step(1),

                        TextInput::make('recruitment_max_bonus')
                            ->label('Maximum bonus (minor units)')
                            ->numeric()
                            ->minValue(0)
                            ->step(1),
                    ])
                    ->columns(2),

                Section::make('Consistency')
                    ->description('Flat payout for hitting weekly conversion counts across enough weeks in the month.')
                    ->schema([
                        Toggle::make('consistency_enabled')
                            ->label('Enabled'),

                        TextInput::make('consistency_bonus_amount')
                            ->label('Bonus amount (minor units)')
                            ->numeric()
                            ->minValue(0)
                            ->step(1),

                        TextInput::make('consistency_min_weeks')
                            ->label('Minimum weeks')
                            ->numeric()
                            ->minValue(1)
                            ->step(1),

                        TextInput::make('consistency_min_conversions_per_week')
                            ->label('Minimum conversions per week')
                            ->numeric()
                            ->minValue(1)
                            ->step(1),
                    ])
                    ->columns(2),

                Section::make('Growth')
                    ->description('Flat payout for month-over-month revenue growth above the floor, gated on previous-month revenue.')
                    ->schema([
                        Toggle::make('growth_enabled')
                            ->label('Enabled'),

                        TextInput::make('growth_bonus_amount')
                            ->label('Bonus amount (minor units)')
                            ->numeric()
                            ->minValue(0)
                            ->step(1),

                        TextInput::make('growth_min_growth_percent')
                            ->label('Minimum growth (%)')
                            ->numeric()
                            ->minValue(0),

                        TextInput::make('growth_min_previous_revenue')
                            ->label('Minimum previous revenue (minor units)')
                            ->numeric()
                            ->minValue(0)
                            ->step(1),

                        Select::make('growth_min_previous_revenue_currency')
                            ->label('Threshold currency')
                            ->options($currencies),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        /** @var array<string, mixed> $state */
        $state = $this->getSchema('form')?->getState() ?? $this->data ?? [];

        [$positions, $dropped] = $this->normalizePositions($state['top_performer_positions'] ?? []);

        $settings = $this->resolveSettings();
        $settings->topPerformerEnabled = (bool) ($state['top_performer_enabled'] ?? false);
        $settings->topPerformerPositions = $positions;
        $settings->topPerformerMinRevenue = max(0, (int) ($state['top_performer_min_revenue'] ?? 0));
        $settings->topPerformerMinRevenueCurrency = $this->normalizeCurrency($state['top_performer_min_revenue_currency'] ?? null);
        $settings->recruitmentEnabled = (bool) ($state['recruitment_enabled'] ?? false);
        $settings->recruitmentBonusPerRecruit = max(0, (int) ($state['recruitment_bonus_per_recruit'] ?? 0));
        $settings->recruitmentMinRecruits = max(1, (int) ($state['recruitment_min_recruits'] ?? 1));
        $settings->recruitmentMaxBonus = max(0, (int) ($state['recruitment_max_bonus'] ?? 0));
        $settings->consistencyEnabled = (bool) ($state['consistency_enabled'] ?? false);
        $settings->consistencyBonusAmount = max(0, (int) ($state['consistency_bonus_amount'] ?? 0));
        $settings->consistencyMinWeeks = max(1, (int) ($state['consistency_min_weeks'] ?? 1));
        $settings->consistencyMinConversionsPerWeek = max(1, (int) ($state['consistency_min_conversions_per_week'] ?? 1));
        $settings->growthEnabled = (bool) ($state['growth_enabled'] ?? false);
        $settings->growthBonusAmount = max(0, (int) ($state['growth_bonus_amount'] ?? 0));
        $settings->growthMinGrowthPercent = max(0.0, (float) ($state['growth_min_growth_percent'] ?? 0));
        $settings->growthMinPreviousRevenue = max(0, (int) ($state['growth_min_previous_revenue'] ?? 0));
        $settings->growthMinPreviousRevenueCurrency = $this->normalizeCurrency($state['growth_min_previous_revenue_currency'] ?? null);
        $settings->save();

        if ($dropped !== []) {
            Notification::make()
                ->title(__('Some positions were skipped (need a positive number and a non-negative amount).'))
                ->body(implode(', ', $dropped))
                ->warning()
                ->send();
        }

        Notification::make()
            ->title(__('Bonus settings saved'))
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

    protected function resolveSettings(): AffiliateBonusSettings
    {
        try {
            return app(AffiliateBonusSettings::class);
        } catch (Throwable) {
            // Settings storage is unavailable (missing table or rows): degrade to
            // config-backed in-memory values instead of 500ing, and never write
            // during reads. A later save() upserts the rows when the table exists.
            if (! $this->settingsFallbackWarned) {
                $this->settingsFallbackWarned = true;

                Notification::make()
                    ->title(__('Bonus settings storage is unavailable; showing config values.'))
                    ->warning()
                    ->send();
            }

            $defaultCurrency = (string) config('affiliates.currency.default', 'MYR');

            return new AffiliateBonusSettings([
                'topPerformerEnabled' => (bool) config('affiliates.bonuses.top_performer.enabled', true),
                'topPerformerPositions' => (array) config('affiliates.bonuses.top_performer.positions', []),
                'topPerformerMinRevenue' => (int) config('affiliates.bonuses.top_performer.min_revenue', 100000),
                'topPerformerMinRevenueCurrency' => (string) config('affiliates.bonuses.top_performer.min_revenue_currency', $defaultCurrency),
                'recruitmentEnabled' => (bool) config('affiliates.bonuses.recruitment.enabled', true),
                'recruitmentBonusPerRecruit' => (int) config('affiliates.bonuses.recruitment.bonus_per_recruit', 2500),
                'recruitmentMinRecruits' => (int) config('affiliates.bonuses.recruitment.min_recruits', 3),
                'recruitmentMaxBonus' => (int) config('affiliates.bonuses.recruitment.max_bonus', 25000),
                'consistencyEnabled' => (bool) config('affiliates.bonuses.consistency.enabled', true),
                'consistencyBonusAmount' => (int) config('affiliates.bonuses.consistency.bonus_amount', 5000),
                'consistencyMinWeeks' => (int) config('affiliates.bonuses.consistency.min_weeks', 4),
                'consistencyMinConversionsPerWeek' => (int) config('affiliates.bonuses.consistency.min_conversions_per_week', 1),
                'growthEnabled' => (bool) config('affiliates.bonuses.growth.enabled', true),
                'growthBonusAmount' => (int) config('affiliates.bonuses.growth.bonus_amount', 10000),
                'growthMinGrowthPercent' => (float) config('affiliates.bonuses.growth.min_growth_percent', 25),
                'growthMinPreviousRevenue' => (int) config('affiliates.bonuses.growth.min_previous_revenue', 50000),
                'growthMinPreviousRevenueCurrency' => (string) config('affiliates.bonuses.growth.min_previous_revenue_currency', $defaultCurrency),
            ]);
        }
    }

    /**
     * @return array{0: array<int, int>, 1: list<string>}
     */
    private function normalizePositions(mixed $positions): array
    {
        $clean = [];
        $dropped = [];

        foreach (is_array($positions) ? $positions : [] as $position => $value) {
            $position = (int) $position;

            if ($position < 1 || ! is_numeric($value) || (int) $value < 0) {
                $dropped[] = (string) $position;

                continue;
            }

            $clean[$position] = (int) $value;
        }

        ksort($clean);

        return [$clean, $dropped];
    }

    private function normalizeCurrency(mixed $currency): string
    {
        $currency = mb_strtoupper(mb_trim((string) $currency));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1
            ? $currency
            : mb_strtoupper((string) config('affiliates.currency.default', 'MYR'));
    }
}
