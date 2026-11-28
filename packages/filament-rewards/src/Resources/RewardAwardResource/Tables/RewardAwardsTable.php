<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardAwardResource\Tables;

use AIArmada\FilamentRewards\Queries\RewardProgramScopeQuery;
use AIArmada\FilamentRewards\Support\RewardAdminPresenter;
use AIArmada\Rewards\Data\RewardComponent;
use AIArmada\Rewards\Enums\RewardAwardStatus;
use AIArmada\Rewards\Enums\RewardPayoutRequestStatus;
use AIArmada\Rewards\Features\Entitlements\Actions\DismissRewardFraudAction;
use AIArmada\Rewards\Features\Entitlements\Actions\ReverseRewardFulfillmentAction;
use AIArmada\Rewards\Features\Entitlements\Actions\TransitionRewardAwardAction;
use AIArmada\Rewards\Features\Entitlements\Models\RewardAward;
use AIArmada\Rewards\Features\Finance\Actions\RequestRewardPayoutAction;
use AIArmada\Rewards\Features\Finance\Api\RewardPayeeSelectionApi;
use AIArmada\Rewards\Features\Finance\Api\RewardPayoutQueryApi;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RewardAwardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => RewardProgramScopeQuery::withProgramTitle($query))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('program_title')
                    ->label('Program')
                    ->state(fn (RewardAward $record): string => (string) ($record->getAttribute('program_title') ?? $record->family_key)),
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
                            $dismiss->handle($record, 'user', (string) $staff->getAuthIdentifier(), RewardAdminPresenter::staffRole(), (string) $data['reason']);
                        } catch (InvalidArgumentException $exception) {
                            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
                        }

                        Notification::make()->title('Fraud dismissed; award held for review')->success()->send();
                    }),
                Action::make('request_payout')
                    ->label('Request payout')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('primary')
                    ->modalHeading('Request a payout for this award')
                    ->modalDescription('Creates the payout request against the verified destination. Approval, submission, and independent confirmation follow in the payouts queue.')
                    ->schema([
                        Select::make('payee_id')
                            ->label('Verified destination')
                            ->options(fn (RewardAward $record): array => self::verifiedPayeeOptions($record))
                            ->required()
                            ->searchable(),
                        Select::make('fee_payer')
                            ->label('Fee payer')
                            ->options(['program' => 'Program', 'recipient' => 'Recipient'])
                            ->default('program')
                            ->required(),
                    ])
                    ->visible(fn (RewardAward $record): bool => $record->status === RewardAwardStatus::Approved
                        && $record->component === RewardComponent::Cash
                        && RewardAdminPresenter::canDecideAward($record, 'requestPayout'))
                    ->action(function (RewardAward $record, array $data, RequestRewardPayoutAction $request): void {
                        $staff = self::authorize($record, 'requestPayout');

                        $payeeId = (string) ($data['payee_id'] ?? '');

                        try {
                            $method = app(RewardPayeeSelectionApi::class)->methodForAward((string) $record->getKey(), $payeeId);

                            if ($method === null) {
                                throw new InvalidArgumentException('Select a verified destination.');
                            }

                            $request->handle(
                                (string) $record->getKey(),
                                $payeeId,
                                $method,
                                (string) ($data['fee_payer'] ?? 'program'),
                                'user',
                                (string) $staff->getAuthIdentifier(),
                                RewardAdminPresenter::staffRole(),
                            );
                        } catch (InvalidArgumentException $exception) {
                            throw ValidationException::withMessages(['payee_id' => $exception->getMessage()]);
                        }

                        Notification::make()->title('Payout requested')->success()->send();
                    }),
                Action::make('fulfill')
                    ->label('Fulfill')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Fulfill this cash award')
                    ->modalDescription('Fulfillment stamps only a confirmed (paid) payout. The confirmed bank transfer must already exist.')
                    ->schema([
                        Textarea::make('reason')->label('Fulfillment note')->required()->maxLength(2000)->rows(3),
                    ])
                    ->visible(fn (RewardAward $record): bool => $record->status === RewardAwardStatus::Approved
                        && $record->component === RewardComponent::Cash
                        && RewardAdminPresenter::canDecideAward($record, 'fulfill'))
                    ->action(function (RewardAward $record, array $data, TransitionRewardAwardAction $transition): void {
                        $staff = self::authorize($record, 'fulfill');

                        $confirmed = app(RewardPayoutQueryApi::class)->forAward((string) $record->getKey());

                        if ($confirmed === null || $confirmed->status !== RewardPayoutRequestStatus::Confirmed->value) {
                            throw ValidationException::withMessages(['reason' => 'No confirmed payout exists for this award.']);
                        }

                        try {
                            $transition->handle(
                                $record,
                                RewardAwardStatus::Fulfilled,
                                'user',
                                (string) $staff->getAuthIdentifier(),
                                RewardAdminPresenter::staffRole(),
                                (string) $data['reason'],
                                ['payout_request_id' => $confirmed->id],
                            );
                        } catch (InvalidArgumentException $exception) {
                            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
                        }

                        Notification::make()->title('Award fulfilled')->success()->send();
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

    /**
     * Verified payout destinations for the award recipient, for the
     * payout-request form. Options are keyed by payee id.
     *
     * @return array<string, string>
     */
    private static function verifiedPayeeOptions(RewardAward $record): array
    {
        return app(RewardPayeeSelectionApi::class)->optionsForAward((string) $record->getKey());
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
                    (string) $staff->getAuthIdentifier(),
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
            $transition->handle($record, $to, 'user', (string) $staff->getAuthIdentifier(), RewardAdminPresenter::staffRole(), $reason);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        Notification::make()->title($successTitle)->success()->send();
    }
}
