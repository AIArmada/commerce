<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Actions;

use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use AIArmada\Vouchers\GiftCards\Services\GiftCardService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

final class ActivateGiftCardAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'activate';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Activate');
        $this->icon(Heroicon::OutlinedPlay);
        $this->color('success');
        $this->requiresConfirmation();
        $this->modalHeading('Activate Gift Card');
        $this->modalDescription('Are you sure you want to activate this gift card? It will become available for use.');

        $this->visible(fn (GiftCard $record): bool => $record->status->canTransitionTo(\AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus::Active));

        $this->action(function (GiftCard $record): void {
            /** @var GiftCardService $service */
            $service = app(GiftCardService::class);
            $service->activate($record->code);

            Notification::make()
                ->title('Gift card activated')
                ->success()
                ->send();
        });
    }
}
