<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentAffiliates\Settings\AffiliateCommissionSettings;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class ManageAffiliateCommissionSettings extends Page
{
    public bool $multi_level_enabled = false;

    /** @var array<int, array{level: int, rate: float}> */
    public array $multi_level_rates = [];

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Commission Settings';

    protected static ?string $slug = 'affiliate-commission-settings';

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.settings';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-affiliates.pages.navigation_sort.commission_settings');

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
        return __('Commission Settings');
    }

    public function mount(): void
    {
        $settings = app(AffiliateCommissionSettings::class);

        $this->multi_level_enabled = $settings->multi_level_enabled;
        $this->multi_level_rates = array_map(
            fn (int $index, float $rate): array => [
                'level' => $index + 1,
                'rate' => $rate * 100,
            ],
            array_keys($settings->multi_level_rates),
            $settings->multi_level_rates,
        );
    }

    public function save(): void
    {
        $maxLevels = max(1, (int) config('affiliates.upline.max_depth', 10));

        $validated = validator([
            'multi_level_enabled' => $this->multi_level_enabled,
            'multi_level_rates' => $this->multi_level_rates,
        ], [
            'multi_level_enabled' => ['required', 'boolean'],
            'multi_level_rates' => ['required', 'array', 'min:1', 'max:' . $maxLevels],
            'multi_level_rates.*.rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ])->validate();

        $settings = app(AffiliateCommissionSettings::class);
        $settings->multi_level_enabled = (bool) $validated['multi_level_enabled'];
        $settings->multi_level_rates = array_map(
            fn (array $row): float => (float) $row['rate'] / 100,
            array_values($validated['multi_level_rates']),
        );
        $settings->save();

        Notification::make()
            ->title(__('Commission settings saved'))
            ->success()
            ->send();
    }

    public function addLevel(): void
    {
        $maxLevels = max(1, (int) config('affiliates.upline.max_depth', 10));

        if (count($this->multi_level_rates) >= $maxLevels) {
            return;
        }

        $nextLevel = count($this->multi_level_rates) + 1;

        $this->multi_level_rates[] = [
            'level' => $nextLevel,
            'rate' => 0,
        ];
    }

    public function removeLevel(int $index): void
    {
        if (count($this->multi_level_rates) <= 1) {
            return;
        }

        unset($this->multi_level_rates[$index]);
        $this->multi_level_rates = array_values($this->multi_level_rates);

        foreach ($this->multi_level_rates as $i => &$rate) {
            $rate['level'] = $i + 1;
        }
        unset($rate);
    }
}
