<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Actions;

use AIArmada\Vouchers\GiftCards\Models\GiftCard;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class TopUpGiftCardAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Top Up');
        $this->icon(Heroicon::OutlinedPlus);
        $this->color('primary');
        $this->modalHeading('Top Up Gift Card');

        $this->visible(fn (GiftCard $record): bool => $record->canTopUp());

        $this->form([
            TextInput::make('amount')
                ->label('Amount')
                ->numeric()
                ->minValue(0.01)
                ->required()
                ->suffix(fn (GiftCard $record): string => $record->currency)
                ->helperText('Enter the amount to add to the gift card balance'),
        ]);

        $this->action(function (GiftCard $record, array $data): void {
            $amountCents = (int) round((float) $data['amount'] * 100);

            /** @var Model|null $actor */
            $actor = Auth::user();

            $record->topUp($amountCents, null, null, $actor);

            Notification::make()
                ->title('Gift card topped up')
                ->body('Added ' . number_format((float) $data['amount'], 2) . ' ' . $record->currency . ' to the balance.')
                ->success()
                ->send();
        });
    }

    public static function getDefaultName(): ?string
    {
        return 'top_up';
    }
}
