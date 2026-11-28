<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Jnt\Features\Orders\Models\JntOrder;
use AIArmada\Jnt\Features\Waybills\Api\PrintableWaybill;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Throwable;

final class PrintAwbTableAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('printAwb')
            ->label('Print AWB')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->requiresConfirmation()
            ->authorize(fn (JntOrder $record): bool => Gate::allows('view', $record))
            ->modalHeading('Print Air Waybill')
            ->modalDescription('Generate and print the shipping label for this order.')
            ->modalSubmitActionLabel('Print')
            ->action(function (JntOrder $record, Component $livewire): void {
                if (Filament::auth()?->user() === null) {
                    Notification::make()
                        ->title('Authentication Required')
                        ->body('Please sign in to print AWB.')
                        ->danger()
                        ->send();

                    return;
                }

                if (! self::recordIsAccessible($record)) {
                    Notification::make()
                        ->title('Not Authorized')
                        ->body('You do not have access to this shipping order.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $url = app(PrintableWaybill::class)->url((string) $record->getKey(), Filament::auth()?->id());

                    if ($url !== null) {
                        $livewire->js('window.open(' . json_encode($url) . ', "_blank", "noopener,noreferrer")');

                        Notification::make()
                            ->title('AWB Ready')
                            ->body('Opening waybill in new tab.')
                            ->success()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('AWB Not Available')
                        ->body('Unable to retrieve waybill content from J&T Express.')
                        ->warning()
                        ->send();
                } catch (Throwable $e) {
                    report($e);

                    Notification::make()
                        ->title('Print Failed')
                        ->body('Unable to generate AWB. Please try again or check logs.')
                        ->danger()
                        ->send();
                }
            })
            ->visible(fn (JntOrder $record): bool => $record->order_id !== '' && $record->order_id !== null);
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'printAwb');
    }

    private static function recordIsAccessible(JntOrder $record): bool
    {
        if (! config('jnt.owner.enabled', false)) {
            return true;
        }

        try {
            OwnerWriteGuard::findOrFailForOwner(JntOrder::class, $record->getKey());

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
