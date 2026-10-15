<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardAwardResource\Tables;

use AIArmada\FilamentRewards\Support\RewardAdminPresenter;
use AIArmada\Rewards\Actions\DismissRewardFraudAction;
use AIArmada\Rewards\Actions\ReverseRewardFulfillmentAction;
use AIArmada\Rewards\Actions\TransitionRewardAwardAction;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Models\RewardProgram;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RewardAwardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('program_title')
                    ->label('Program')
                    ->state(fn (RewardAward $record): string => (string) (RewardProgram::query()->whereKey((string) $record->program_id)->value('title') ?? $record->family_key)),
                TextColumn::make('recipient')
                    ->label('Recipient')
                    ->state(fn (RewardAward $record): string => RewardAdminPresenter::recipientLabel((string) $record->recipient_type, (string) $record->recipient_id))
                    ->searchable(query: fn ($query, string $search) => $query->where('recipient_id', $search)),
                TextColumn::make('component')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => RewardAdminPresenter::componentOptions()[(string) $state->value] ?? (string) $state->value),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => RewardAdminPresenter::statusLabel($state))
                    ->color(fn (mixed $state): string => RewardAdminPresenter::statusColor($state)),
                TextColumn::make('beneficiary_role')
                    ->label('Role')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(RewardAdminPresenter::awardStatusOptions()),
                SelectFilter::make('component')->options(RewardAdminPresenter::componentOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve award')
                    ->schema([
                        Textarea::make('reason')->label('Decision reason')->required()->maxLength(2000)->rows(3),
                    ])
                    ->visible(fn (RewardAward $record): bool => in_array($record->status, [RewardAwardStatus::Held, RewardAwardStatus::PendingReview], true)
                        && RewardAdminPresenter::canDecideAward($record, 'approve'))
                    ->action(function (RewardAward $record, array $data, TransitionRewardAwardAction $transition): void {
                        self::transition($record, RewardAwardStatus::Approved, (string) $data['reason'], $transition, 'Award approved', 'approve');
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->modalHeading('Reject award')
                    ->schema([
                        Textarea::make('reason')->label('Decision reason')->required()->maxLength(2000)->rows(3),
                    ])
                    ->visible(fn (RewardAward $record): bool => in_array($record->status, [RewardAwardStatus::Held, RewardAwardStatus::PendingReview], true)
                        && RewardAdminPresenter::canDecideAward($record, 'review'))
                    ->action(function (RewardAward $record, array $data, TransitionRewardAwardAction $transition): void {
                        self::transition($record, RewardAwardStatus::Rejected, (string) $data['reason'], $transition, 'Award rejected', 'review');
                    }),
                Action::make('reverse')
                    ->label('Reverse')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reverse award')
                    ->modalDescription('Reversal preserves history and appends correction evidence; it never rewrites the original fact or policy. Fulfilled awards reverse through recovery: confirmed payouts open a receivable, consumed grants open a dispute, and open operations must be driven terminal first.')
                    ->schema([
                        Textarea::make('reason')->label('Reversal reason')->required()->maxLength(2000)->rows(3),
                    ])
                    ->visible(fn (RewardAward $record): bool => in_array($record->status, [RewardAwardStatus::Approved, RewardAwardStatus::Fulfilled], true)
                        && RewardAdminPresenter::canDecideAward($record, 'reverse'))
                    ->action(function (RewardAward $record, array $data, TransitionRewardAwardAction $transition): void {
                        self::reverse($record, (string) $data['reason'], $transition);
                    }),
                Action::make('dismiss_fraud')
                    ->label('Dismiss fraud')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->color('gray')
                    ->modalHeading('Dismiss fraud signal')
                    ->modalDescription('Dismissal returns the award to held for ordinary review. It is never approval.')
                    ->schema([
                        Textarea::make('reason')->label('Dismissal reason')->required()->maxLength(2000)->rows(3),
                    ])
                    ->visible(fn (RewardAward $record): bool => in_array($record->status, [RewardAwardStatus::Held, RewardAwardStatus::PendingReview], true)
                        && RewardAdminPresenter::canDecideAward($record, 'dismissFraud'))
                    ->action(function (RewardAward $record, array $data, DismissRewardFraudAction $dismiss): void {
                        $staff = self::authorize($record, 'dismissFraud');

                        try {
                            $dismiss->handle($record, 'user', (string) $staff->getKey(), RewardAdminPresenter::staffRole(), (string) $data['reason']);
                        } catch (InvalidArgumentException $exception) {
                            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
                        }

                        Notification::make()->title('Fraud dismissed; award held for review')->success()->send();
                    }),
            ]);
    }

    /**
     * Fail-closed handler boundary for every award decision. `visible()` only
     * decides whether the button renders; a crafted Livewire request can still
     * resolve and call a hidden record action, so the staff role and the Gate
     * ability are re-checked here before any state change.
     */
    private static function authorize(RewardAward $record, string $ability): Authenticatable
    {
        $staff = RewardAdminPresenter::staffActor();
        abort_unless($staff !== null, 403);
        abort_unless(RewardAdminPresenter::canDecideAward($record, $ability), 403);

        return $staff;
    }

    private static function reverse(
        RewardAward $record,
        string $reason,
        TransitionRewardAwardAction $transition,
    ): void {
        // Fulfilled awards reverse through the recovery-aware connector;
        // anything else reverses through the plain transition.
        if ($record->status === RewardAwardStatus::Fulfilled) {
            $staff = self::authorize($record, 'reverse');

            try {
                $outcome = ReverseRewardFulfillmentAction::run(
                    $record,
                    'user',
                    (string) $staff->getKey(),
                    RewardAdminPresenter::staffRole(),
                    $reason,
                );
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
            }

            $recoveries = count($outcome['recovery_ids']);

            Notification::make()
                ->title($recoveries > 0 ? "Award reversed ({$recoveries} recoveries opened)" : 'Award reversed')
                ->success()
                ->send();

            return;
        }

        self::transition($record, RewardAwardStatus::Reversed, $reason, $transition, 'Award reversed', 'reverse');
    }

    private static function transition(
        RewardAward $record,
        RewardAwardStatus $to,
        string $reason,
        TransitionRewardAwardAction $transition,
        string $successTitle,
        string $ability,
    ): void {
        $staff = self::authorize($record, $ability);

        try {
            $transition->handle($record, $to, 'user', (string) $staff->getKey(), RewardAdminPresenter::staffRole(), $reason);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        Notification::make()->title($successTitle)->success()->send();
    }
}
