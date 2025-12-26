<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\Resources\SubscriptionResource\Pages;

use AIArmada\CashierChip\Subscription;
use AIArmada\FilamentCashierChip\Resources\SubscriptionResource;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Override;

final class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;

    #[Override]
    public function getTitle(): string
    {
        /** @var Subscription $record */
        $record = $this->getRecord();

        return sprintf('Subscription: %s', $record->type);
    }

    public function getHeadingIcon(): Heroicon
    {
        return Heroicon::OutlinedCreditCard;
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('cancel')
                    ->label('Cancel at Period End')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Subscription')
                    ->modalDescription('This will cancel the subscription at the end of the current billing period. The customer will retain access until then.')
                    ->visible(function (): bool {
                        /** @var Subscription $record */
                        $record = $this->getRecord();

                        return ! $record->canceled();
                    })
                    ->action(function (): void {
                        /** @var Subscription $subscription */
                        $subscription = $this->getRecord();
                        $subscription->cancel();

                        Notification::make()
                            ->title('Subscription Canceled')
                            ->body('The subscription will end at ' . $subscription->ends_at->format('Y-m-d H:i'))
                            ->success()
                            ->send();

                        $this->refreshFormData(['ends_at', 'chip_status']);
                    }),

                Action::make('cancel_now')
                    ->label('Cancel Immediately')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Subscription Immediately')
                    ->modalDescription('This will immediately cancel the subscription. The customer will lose access right away.')
                    ->visible(function (): bool {
                        /** @var Subscription $record */
                        $record = $this->getRecord();

                        return ! $record->ended();
                    })
                    ->action(function (): void {
                        /** @var Subscription $subscription */
                        $subscription = $this->getRecord();
                        $subscription->cancelNow();

                        Notification::make()
                            ->title('Subscription Canceled Immediately')
                            ->body('The subscription has been canceled.')
                            ->success()
                            ->send();

                        $this->refreshFormData(['ends_at', 'chip_status']);
                    }),

                Action::make('resume')
                    ->label('Resume Subscription')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Resume Subscription')
                    ->modalDescription('This will resume the subscription if it is within its grace period.')
                    ->visible(function (): bool {
                        /** @var Subscription $record */
                        $record = $this->getRecord();

                        return $record->onGracePeriod();
                    })
                    ->action(function (): void {
                        /** @var Subscription $subscription */
                        $subscription = $this->getRecord();
                        $subscription->resume();

                        Notification::make()
                            ->title('Subscription Resumed')
                            ->body('The subscription is now active again.')
                            ->success()
                            ->send();

                        $this->refreshFormData(['ends_at', 'chip_status']);
                    }),

                Action::make('pause')
                    ->label('Pause Subscription')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(function (): bool {
                        /** @var Subscription $record */
                        $record = $this->getRecord();

                        return ! $record->paused() && $record->active();
                    })
                    ->action(function (): void {
                        /** @var Subscription $subscription */
                        $subscription = $this->getRecord();
                        $subscription->pause();

                        Notification::make()
                            ->title('Subscription Paused')
                            ->body('The subscription has been paused.')
                            ->success()
                            ->send();

                        $this->refreshFormData(['chip_status']);
                    }),

                Action::make('unpause')
                    ->label('Unpause Subscription')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(function (): bool {
                        /** @var Subscription $record */
                        $record = $this->getRecord();

                        return $record->paused();
                    })
                    ->action(function (): void {
                        /** @var Subscription $subscription */
                        $subscription = $this->getRecord();
                        $subscription->unpause();

                        Notification::make()
                            ->title('Subscription Unpaused')
                            ->body('The subscription is now active.')
                            ->success()
                            ->send();

                        $this->refreshFormData(['chip_status']);
                    }),
            ])->label('Subscription Actions'),

            Action::make('extend_trial')
                ->label('Extend Trial')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->form([
                    DateTimePicker::make('trial_ends_at')
                        ->label('New Trial End Date')
                        ->required()
                        ->minDate(now())
                        ->default(fn (): CarbonImmutable => CarbonImmutable::now()->addDays(14)),
                ])
                ->visible(function (): bool {
                    /** @var Subscription $record */
                    $record = $this->getRecord();

                    return $record->onTrial() || $record->trial_ends_at === null;
                })
                ->action(function (array $data): void {
                    /** @var Subscription $subscription */
                    $subscription = $this->getRecord();
                    $subscription->extendTrial(CarbonImmutable::parse($data['trial_ends_at']));

                    Notification::make()
                        ->title('Trial Extended')
                        ->body('The trial has been extended to ' . $subscription->trial_ends_at->format('Y-m-d H:i'))
                        ->success()
                        ->send();

                    $this->refreshFormData(['trial_ends_at']);
                }),

            Action::make('end_trial')
                ->label('End Trial Now')
                ->icon('heroicon-o-stop')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('End Trial Period')
                ->modalDescription('This will immediately end the trial period and convert the subscription to a paid subscription.')
                ->visible(function (): bool {
                    /** @var Subscription $record */
                    $record = $this->getRecord();

                    return $record->onTrial();
                })
                ->action(function (): void {
                    /** @var Subscription $subscription */
                    $subscription = $this->getRecord();
                    $subscription->endTrial();

                    Notification::make()
                        ->title('Trial Ended')
                        ->body('The trial has been ended and the subscription is now active.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['trial_ends_at', 'chip_status']);
                }),

            Action::make('swap_plan')
                ->label('Swap Plan')
                ->icon('heroicon-o-arrows-right-left')
                ->color('info')
                ->form([
                    TextInput::make('price')
                        ->label('New Price ID')
                        ->required()
                        ->placeholder('price_...')
                        ->helperText('Enter the Chip price ID for the new plan'),
                ])
                ->visible(function (): bool {
                    /** @var Subscription $record */
                    $record = $this->getRecord();

                    return $record->active() || $record->onTrial();
                })
                ->action(function (array $data): void {
                    /** @var Subscription $subscription */
                    $subscription = $this->getRecord();
                    $subscription->swap($data['price']);

                    Notification::make()
                        ->title('Plan Swapped')
                        ->body('The subscription has been swapped to the new plan.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['chip_price', 'quantity']);
                }),

            Action::make('update_quantity')
                ->label('Update Quantity')
                ->icon('heroicon-o-calculator')
                ->form([
                    TextInput::make('quantity')
                        ->label('New Quantity')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->default(fn (): int => $this->getRecord()->quantity ?? 1),
                ])
                ->visible(function (): bool {
                    /** @var Subscription $record */
                    $record = $this->getRecord();

                    return $record->hasSinglePrice();
                })
                ->action(function (array $data): void {
                    /** @var Subscription $subscription */
                    $subscription = $this->getRecord();
                    $subscription->updateQuantity((int) $data['quantity']);

                    Notification::make()
                        ->title('Quantity Updated')
                        ->body('The subscription quantity has been updated to ' . $data['quantity'])
                        ->success()
                        ->send();

                    $this->refreshFormData(['quantity']);
                }),

            Action::make('sync_status')
                ->label('Sync Status')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    /** @var Subscription $subscription */
                    $subscription = $this->getRecord();
                    $subscription->syncChipStatus();

                    Notification::make()
                        ->title('Status Synced')
                        ->body('The subscription status has been recalculated.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['chip_status']);
                }),
        ];
    }
}
