<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralRewardResource\Tables;

use AIArmada\Referrals\Actions\FulfillReferralReward;
use AIArmada\Referrals\Actions\ReverseReferralReward;
use AIArmada\Referrals\Enums\ReferralRewardStatus;
use AIArmada\Referrals\Models\ReferralReward;
use AIArmada\Referrals\Support\ReferralProgramGuard;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
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
                ->visible(fn (ReferralReward $record): bool => in_array(
                    $record->status,
                    [ReferralRewardStatus::Pending, ReferralRewardStatus::Failed],
                    true,
                ))
                ->action(function (ReferralReward $record): void {
                    try {
                        $reward = app(ReferralProgramGuard::class)->reward($record);
                        $result = FulfillReferralReward::run($reward);
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('Referral reward fulfilment failed')
                            ->body($exception->getMessage())
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
                ->action(function (ReferralReward $record, array $data): void {
                    try {
                        $reward = app(ReferralProgramGuard::class)->reward($record);
                        ReverseReferralReward::run($reward, (string) ($data['reason'] ?? ''));
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('Referral reward reversal failed')
                            ->body($exception->getMessage())
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
