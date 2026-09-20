<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages;

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Services\Commissions\CommissionRuleEngine;
use AIArmada\Affiliates\Services\PerformanceBonusService;
use AIArmada\Affiliates\Support\BonusMonth;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

final class PerformanceBonusesPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public ?array $data = [];

    /**
     * Preview rows keyed by bonus type: count plus minor-unit totals by currency.
     *
     * @var array<string, array{count: int, totals: array<string, int>}>
     */
    public array $preview = [];

    public ?string $previewMonth = null;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Performance Bonuses';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-affiliates.pages.navigation_sort.performance_bonuses');

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
        return __('Performance Bonuses');
    }

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.performance-bonuses';

    public function mount(): void
    {
        $this->data = ['month' => CarbonImmutable::now()->format('Y-m')];

        $this->getSchema('form')?->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('month')
                    ->label(__('Month'))
                    ->helperText(__('YYYY-MM. Bonuses calculate over the full month.'))
                    ->required()
                    ->regex('/^\d{4}-(0[1-9]|1[0-2])$/')
                    ->default(CarbonImmutable::now()->format('Y-m')),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->action(fn (): null => $this->refreshPreview()),

            Action::make('award')
                ->label('Award calculated bonuses')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Credits the calculated bonuses as approved conversions in the current owner scope. Already-awarded bonuses are skipped automatically.')
                ->action(function (): void {
                    $range = $this->monthRange();

                    if ($range === null) {
                        $this->invalidMonthNotification();

                        return;
                    }

                    [$from, $to] = $range;

                    $awarded = app(PerformanceBonusService::class)->awardBonuses(
                        $this->calculate($from, $to)
                    );

                    $this->refreshPreview();

                    Notification::make()
                        ->title('Bonuses awarded')
                        ->body("Newly awarded: {$awarded}")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AffiliateConversion::query()
                    ->whereNotNull('performance_bonus_key')
                    ->with('affiliate')
                    ->latest('occurred_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('affiliate.code')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('metadata.bonus_type')
                    ->label('Bonus')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => CommissionRuleType::tryFrom((string) $state)?->label() ?? (string) $state),

                Tables\Columns\TextColumn::make('commission_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state, $record): string => MoneyFormatter::formatMinor((int) $state, $record->commission_currency ?? 'MYR'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('metadata.metrics.period')
                    ->label('Period'),

                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Awarded')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('bonus_type')
                    ->label('Bonus')
                    ->options(fn (): array => AffiliateConversion::query()
                        ->whereNotNull('performance_bonus_key')
                        ->get()
                        ->mapWithKeys(fn (AffiliateConversion $row): array => [
                            (string) ($row->metadata['bonus_type'] ?? '') => CommissionRuleType::tryFrom((string) ($row->metadata['bonus_type'] ?? ''))?->label() ?? (string) ($row->metadata['bonus_type'] ?? ''),
                        ])
                        ->filter()
                        ->toArray())
                    ->query(function ($query, array $data): void {
                        $value = $data['value'] ?? null;

                        if (is_string($value) && $value !== '') {
                            $query->where('metadata->bonus_type', $value);
                        }
                    }),
            ]);
    }

    private function refreshPreview(): ?bool
    {
        $range = $this->monthRange();

        if ($range === null) {
            $this->invalidMonthNotification();

            return null;
        }

        [$from, $to] = $range;
        $bonuses = $this->calculate($from, $to);

        $currencies = Affiliate::query()
            ->whereIn('id', collect($bonuses)->map(fn (array $bonus): string => (string) $bonus['affiliate_id'])->unique()->values()->all())
            ->pluck('currency', 'id');

        $defaultCurrency = mb_strtoupper((string) config('affiliates.currency.default', 'MYR'));
        $preview = [];

        foreach ($bonuses as $bonus) {
            $type = (string) $bonus['bonus_type'];
            $currency = mb_strtoupper((string) ($currencies->get($bonus['affiliate_id']) ?? $defaultCurrency));

            $preview[$type] ??= ['count' => 0, 'totals' => []];
            $preview[$type]['count']++;
            $preview[$type]['totals'][$currency] = ($preview[$type]['totals'][$currency] ?? 0) + (int) $bonus['amount_minor'];
        }

        $this->preview = $preview;
        $this->previewMonth = $from->format('Y-m');

        return true;
    }

    /**
     * @return list<array{bonus_type: string, affiliate_id: string, affiliate_name: string, amount_minor: int, reason: string, metrics: array<string, mixed>}>
     */
    private function calculate(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $engine = app(CommissionRuleEngine::class);
        $bonuses = [];

        foreach (CommissionRuleType::performanceBonusCases() as $type) {
            foreach ($engine->calculatePerformanceBonuses($type, $from, $to) as $bonus) {
                $bonuses[] = $bonus;
            }
        }

        return $bonuses;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function monthRange(): ?array
    {
        /** @var array<string, mixed> $state */
        $state = $this->getSchema('form')?->getState() ?? $this->data ?? [];

        return BonusMonth::parse($state['month'] ?? null);
    }

    private function invalidMonthNotification(): void
    {
        Notification::make()
            ->title('Invalid month')
            ->body('Expected YYYY-MM, e.g. 2026-09.')
            ->danger()
            ->send();
    }
}
