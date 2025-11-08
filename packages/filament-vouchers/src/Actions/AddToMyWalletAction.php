<?php

declare(strict_types=1);

namespace AIArmada\FilamentVouchers\Actions;

use AIArmada\FilamentVouchers\Models\Voucher;
use AIArmada\Vouchers\Exceptions\VoucherException;
use AIArmada\Vouchers\Services\VoucherService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AddToMyWalletAction
{
    public static function make(): Action
    {
        return Action::make('add_to_my_wallet')
            ->label('Add to My Wallet')
            ->icon(Heroicon::OutlinedWallet)
            ->color('success')
            ->modalHeading('Add Voucher to My Wallet')
            ->modalDescription('Save this voucher to your wallet for later use.')
            ->modalSubmitActionLabel('Add to Wallet')
            ->visible(fn (): bool => Auth::check())
            ->form([
                Textarea::make('notes')
                    ->label('Notes (Optional)')
                    ->placeholder('Add a note about this voucher...')
                    ->maxLength(500)
                    ->rows(3)
                    ->helperText('You can add optional notes to remember why you saved this voucher.'),
            ])
            ->action(function (array $data, Voucher $record): void {
                $user = Auth::user();

                if (! $user) {
                    Notification::make()
                        ->warning()
                        ->title('Authentication Required')
                        ->body('You must be logged in to save vouchers to your wallet.')
                        ->icon(Heroicon::OutlinedExclamationCircle)
                        ->send();

                    return;
                }

                try {
                    // Check if voucher is already in the user's wallet
                    if (method_exists($user, 'hasVoucherInWallet') && $user->hasVoucherInWallet($record->id)) {
                        Notification::make()
                            ->info()
                            ->title('Already in Wallet')
                            ->body('This voucher is already saved in your wallet.')
                            ->icon(Heroicon::OutlinedInformationCircle)
                            ->send();

                        return;
                    }

                    // Add voucher to wallet with optional metadata
                    $metadata = ! empty($data['notes']) ? ['notes' => $data['notes']] : null;

                    if (method_exists($user, 'addVoucherToWallet')) {
                        $user->addVoucherToWallet($record->id, metadata: $metadata);
                    } else {
                        // Fallback to service if trait not used
                        app(VoucherService::class)->addToWallet($record->id, $user, metadata: $metadata);
                    }

                    Notification::make()
                        ->success()
                        ->title('Added to Wallet')
                        ->body("The voucher '{$record->code}' has been added to your wallet.")
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->send();

                } catch (VoucherException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Failed to Add to Wallet')
                        ->body($exception->getMessage())
                        ->icon(Heroicon::OutlinedXCircle)
                        ->persistent()
                        ->send();

                    Log::warning('Failed to add voucher to wallet', [
                        'voucher_id' => $record->id,
                        'user_id' => $user->id ?? null,
                        'error' => $exception->getMessage(),
                    ]);
                } catch (Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Unexpected Error')
                        ->body('An unexpected error occurred while adding the voucher to your wallet.')
                        ->icon(Heroicon::OutlinedXCircle)
                        ->persistent()
                        ->send();

                    Log::error('Unexpected error adding voucher to wallet', [
                        'voucher_id' => $record->id,
                        'user_id' => $user->id ?? null,
                        'error' => $exception->getMessage(),
                        'trace' => $exception->getTraceAsString(),
                    ]);
                }
            });
    }
}
