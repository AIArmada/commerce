<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Pages;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
final class TargetingConfigurationPage extends Page implements HasForms
{
    use InteractsWithForms;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedFunnel;

    /** @var view-string */
    protected string $view = 'filament-vouchers::pages.targeting-configuration';

    protected static ?string $navigationLabel = 'Targeting Rules';

    protected static ?string $title = 'Voucher Targeting Configuration';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-vouchers.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-vouchers.pages.navigation_sort.targeting_configuration');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function canAccess(): bool
    {
        return FilamentPermission::hasAbility('voucher.update');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill([
            'check_targeting' => config('vouchers.validation.check_targeting', true),
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Toggle::make('check_targeting')
                    ->label('Targeting validation enabled'),
            ])
            ->statePath('data')
            ->disabled();
    }
}
