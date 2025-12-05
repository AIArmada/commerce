<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Actions;

use AIArmada\Vouchers\Enums\VoucherStatus;
use AIArmada\Vouchers\Models\Voucher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

final class ActivateVoucherAction extends Action
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
        $this->modalHeading('Activate Voucher');
        $this->modalDescription('This will make the voucher available for use.');

        $this->visible(fn (Voucher $record): bool => $record->status !== VoucherStatus::Active);

        $this->action(function (Voucher $record): void {
            $record->update(['status' => VoucherStatus::Active]);

            Notification::make()
                ->title('Voucher activated')
                ->success()
                ->send();
        });
    }
}
