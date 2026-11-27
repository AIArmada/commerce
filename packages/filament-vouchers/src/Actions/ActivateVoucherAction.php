<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Actions;

use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Runtime\VoucherAvailability;
use AIArmada\Vouchers\States\Paused;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

final class ActivateVoucherAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(static fn (): bool => FilamentPermission::hasAbility('voucher.update'));

        $this->label('Activate');
        $this->icon(Heroicon::OutlinedPlay);
        $this->color('success');
        $this->requiresConfirmation();
        $this->modalHeading('Activate Voucher');
        $this->modalDescription('This will make the voucher available for use.');

        $this->visible(fn (Voucher $record): bool => $record->status instanceof Paused && ! $record->isExpired());

        $this->action(function (Voucher $record): void {
            app(VoucherAvailability::class)->handle($record, true);

            Notification::make()
                ->title('Voucher activated')
                ->success()
                ->send();
        });
    }

    public static function getDefaultName(): ?string
    {
        return 'activate';
    }
}
