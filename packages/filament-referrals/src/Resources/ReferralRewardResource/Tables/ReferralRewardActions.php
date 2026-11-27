<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralRewardResource\Tables;

use AIArmada\FilamentReferrals\Resources\ReferralRewardResource\ReferralRewardResource;
use AIArmada\Referrals\Actions\FulfillReferralReward;
use AIArmada\Referrals\Actions\ReverseReferralReward;
use AIArmada\Referrals\Enums\ReferralRewardStatus;
use AIArmada\Referrals\Models\ReferralReward;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Throwable;

class ReferralRewardActions
{
    /**
     * @return array<int, Action>
     */
    public static function make(): array
    {
        return [
            Action::make('fulfill')
                ->schema(fn (ReferralReward $record): array => $record->driver === 'manual' ? [
                    TextInput::make('manual_reference')->label('Verified external reference')->required()->maxLength(255),
                ] : [])
                ->visible(fn (ReferralReward $record): bool => in_array(
                    $record->status,
                    [ReferralRewardStatus::Pending, ReferralRewardStatus::Failed],
                    true,
                ))
                ->authorize(fn (ReferralReward $record): bool => ReferralRewardResource::canEdit($record))
                ->action(function (ReferralReward $record, array $data): void {
                    try {
                        $result = FulfillReferralReward::run((string) $record->getKey(), is_string($data['manual_reference'] ?? null) ? $data['manual_reference'] : null);
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Referral reward fulfilment failed')
                            ->body('The operation could not be completed. Check the application logs.')
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($result->status === ReferralRewardStatus::Fulfilled) {
                        Notification::make()
                            ->title('Referral reward fulfilled')
                            ->success()
                            ->send();

                        return;
                    }

                    $failure = is_array($result->metadata) ? ($result->metadata['fulfillment_failure'] ?? null) : null;

                    Notification::make()
                        ->title('Referral reward fulfilment failed')
                        ->body(is_string($failure) ? $failure : 'The fulfilment adapter refused this reward.')
                        ->danger()
                        ->send();
                }),
            Action::make('reverse')
                ->modalHeading('Reverse referral reward')
                ->schema([
                    Textarea::make('reason')
                        ->required()
                        ->minLength(3)
                        ->maxLength(1000),
                ])
                ->visible(fn (ReferralReward $record): bool => $record->status === ReferralRewardStatus::Fulfilled)
                ->authorize(fn (ReferralReward $record): bool => ReferralRewardResource::canEdit($record))
                ->action(function (ReferralReward $record, array $data): void {
                    try {
                        ReverseReferralReward::run((string) $record->getKey(), (string) ($data['reason'] ?? ''));
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Referral reward reversal failed')
                            ->body('The operation could not be completed. Check the application logs.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Referral reward reversed')
                        ->success()
                        ->send();
                }),
        ];
    }
}
