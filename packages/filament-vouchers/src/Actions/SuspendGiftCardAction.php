<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Actions;

use AIArmada\Vouchers\GiftCards\Enums\GiftCardStatus;
use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use AIArmada\Vouchers\GiftCards\Services\GiftCardService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

final class SuspendGiftCardAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'suspend';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Suspend');
        $this->icon(Heroicon::OutlinedPause);
        $this->color('danger');
        $this->requiresConfirmation();
        $this->modalHeading('Suspend Gift Card');
        $this->modalDescription('Are you sure you want to suspend this gift card? It will no longer be usable until reactivated.');

        $this->visible(fn (GiftCard $record): bool => $record->status->canTransitionTo(GiftCardStatus::Suspended));

        $this->action(function (GiftCard $record): void {
            /** @var GiftCardService $service */
            $service = app(GiftCardService::class);
            $service->suspend($record->code);

            Notification::make()
                ->title('Gift card suspended')
                ->warning()
                ->send();
        });
    }
}
