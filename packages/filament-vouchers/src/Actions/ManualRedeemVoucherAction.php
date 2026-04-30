<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\FilamentVouchers\Support\OwnerScopedQueries;
use AIArmada\Vouchers\Models\Voucher;
use AIArmada\Vouchers\Services\VoucherService;
use Akaunting\Money\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

final class ManualRedeemVoucherAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Manual Redeem');
        $this->icon(Heroicon::OutlinedReceiptPercent);
        $this->color('warning');
        $this->modalHeading('Manually Redeem Voucher');
        $this->modalDescription('Record a manual redemption for POS or offline usage.');

        $this->visible(fn (Voucher $record): bool => $record->allows_manual_redemption && $record->hasUsageLimitRemaining());

        $this->form(fn (Voucher $record): array => [
            TextInput::make('discount_amount')
                ->label('Discount Amount')
                ->numeric()
                ->required()
                ->suffix($record->currency)
                ->helperText('The discount amount applied'),

            TextInput::make('reference')
                ->label('Reference')
                ->maxLength(100)
                ->helperText('Order ID, receipt number, etc.'),

            Textarea::make('notes')
                ->label('Notes')
                ->rows(2)
                ->helperText('Additional notes about this redemption'),
        ]);

        $this->action(function (Voucher $record, array $data): void {
            if (OwnerScopedQueries::isEnabled()) {
                /** @var Voucher $record */
                $record = OwnerWriteGuard::findOrFailForOwner(Voucher::class, $record->getKey());
            }

            /** @var VoucherService $service */
            $service = app(VoucherService::class);

            $discountCents = (int) round((float) $data['discount_amount'] * 100);
            $discount = Money::{$record->currency}($discountCents);

            $user = Auth::user();

            $service->redeemManually(
                code: $record->code,
                discountAmount: $discount,
                reference: $data['reference'] ?? null,
                metadata: ['manual_by' => $user?->id],
                redeemedBy: $user,
                notes: $data['notes'] ?? null
            );

            Notification::make()
                ->title('Voucher redeemed')
                ->body('Manual redemption recorded successfully.')
                ->success()
                ->send();
        });
    }

    public static function getDefaultName(): ?string
    {
        return 'manual_redeem';
    }
}
