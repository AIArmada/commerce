<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Actions;

use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\States\Active;
use AIArmada\Vouchers\States\Paused;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

final class PauseVoucherAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Pause');
        $this->icon(Heroicon::OutlinedPause);
        $this->color('warning');
        $this->requiresConfirmation();
        $this->modalHeading('Pause Voucher');
        $this->modalDescription('This will temporarily disable the voucher.');

        $this->visible(fn (Voucher $record): bool => $record->status instanceof Active);

        $this->action(function (Voucher $record): void {
            $record->update(['status' => Paused::class]);

            Notification::make()
                ->title('Voucher paused')
                ->warning()
                ->send();
        });
    }

    public static function getDefaultName(): ?string
    {
        return 'pause';
    }
}
