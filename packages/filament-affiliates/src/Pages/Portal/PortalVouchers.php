<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Pages\Portal;

use AIArmada\FilamentAffiliates\Concerns\PortalPage;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class PortalVouchers extends PortalPage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?int $navigationSort = 3;

    /** @var view-string */
    protected string $view = 'filament-affiliates::pages.portal.vouchers';

    public static function getNavigationLabel(): string
    {
        return __('Vouchers');
    }

    public static function getNavigationParent(): ?string
    {
        return PortalLinks::class;
    }

    public function getTitle(): string | Htmlable
    {
        return __('Your Vouchers');
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return [
            'hasAffiliate' => $this->hasAffiliate(),
            'vouchers' => $this->getVouchers(100),
        ];
    }
}
