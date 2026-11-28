<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Resources\RewardAppealResource\Tables;

use AIArmada\FilamentRewards\Support\RewardAdminPresenter;
use AIArmada\Rewards\Features\Entitlements\Actions\AdvanceRewardAppealAction;
use AIArmada\Rewards\Features\Entitlements\Api\RewardAwardLockApi;
use AIArmada\Rewards\Features\Entitlements\Models\RewardAppeal;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RewardAppealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('opened_at', 'desc')
            ->columns([
                TextColumn::make('award_recipient')
                    ->label('Member')
                    ->state(fn (RewardAppeal $record): string => self::recipientLabel($record)),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => RewardAdminPresenter::statusLabel($state))
                    ->color(fn (mixed $state): string => RewardAdminPresenter::statusColor($state)),
                TextColumn::make('reason')
                    ->limit(60),
                TextColumn::make('opened_at')
                    ->since()
                    ->sortable(),
                TextColumn::make('window_ends_at')
                    ->label('Window ends')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(RewardAdminPresenter::appealStatusOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('decide')
                    ->label('Decide')
                    ->icon(Heroicon::OutlinedScale)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Decide appeal')
                    ->modalDescription('Overturning appends a merit override on the award; it never rewrites the original fact or policy.')
                    ->schema(fn (RewardAppeal $record): array => [
                        Select::make('to')
                            ->label('Decision')
                            ->options(self::transitionOptions($record))
                            ->required(),
                        Textarea::make('reason')->label('Decision reason')->required()->maxLength(2000)->rows(3),
                    ])
                    ->visible(fn (RewardAppeal $record): bool => $record->status->allowedTransitions() !== []
                        && RewardAdminPresenter::canDecideAward($record, 'decide'))
                    ->action(function (RewardAppeal $record, array $data, AdvanceRewardAppealAction $advance): void {
                        // Handler boundary: `visible()` is presentation only, so
                        // re-check the staff role and the `decide` ability here.
                        $staff = RewardAdminPresenter::staffActor();
                        abort_unless($staff !== null, 403);
                        abort_unless(RewardAdminPresenter::canDecideAward($record, 'decide'), 403);

                        try {
                            $advance->handle(
                                $record,
                                (string) $data['to'],
                                'user',
                                (string) $staff->getAuthIdentifier(),
                                RewardAdminPresenter::staffRole(),
                                (string) $data['reason'],
                            );
                        } catch (InvalidArgumentException $exception) {
                            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
                        }

                        Notification::make()->title('Appeal decided')->success()->send();
                    }),
            ]);
    }

    private static function recipientLabel(RewardAppeal $record): string
    {
        $award = app(RewardAwardLockApi::class)->findAwardDecision((string) $record->award_id);

        if ($award === null) {
            return '-';
        }

        return RewardAdminPresenter::recipientLabel($award->recipientType, $award->recipientId);
    }

    /**
     * @return array<string, string>
     */
    private static function transitionOptions(RewardAppeal $record): array
    {
        $options = [];

        foreach ($record->status->allowedTransitions() as $to) {
            $options[$to->value] = RewardAdminPresenter::statusLabel($to);
        }

        return $options;
    }
}
