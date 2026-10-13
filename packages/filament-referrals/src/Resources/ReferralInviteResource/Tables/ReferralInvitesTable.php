<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralInviteResource\Tables;

use AIArmada\FilamentReferrals\Support\ReferralAdminPresenter;
use AIArmada\Referrals\Actions\RevokeReferralInvite;
use AIArmada\Referrals\Enums\ReferralInviteStatus;
use AIArmada\Referrals\Models\ReferralInvite;
use AIArmada\Referrals\Models\ReferralProgram;
use AIArmada\Referrals\Support\ReferralProgramGuard;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class ReferralInvitesTable
{
    public static function configure(
        Table $table,
        bool $showActions = true,
        bool $showFilters = true,
    ): Table {
        $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('program.name')
                    ->label('Program')
                    ->searchable(),
                TextColumn::make('referrer_label')
                    ->label('Referrer')
                    ->state(fn (ReferralInvite $record): string => ReferralAdminPresenter::morphLabel($record->referrer)),
                TextColumn::make('invitee_hash')
                    ->label('Invitee hash')
                    ->formatStateUsing(fn (mixed $state): string => ReferralAdminPresenter::shortHash(is_string($state) ? $state : null)),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => ReferralAdminPresenter::statusLabel($state))
                    ->color(fn (mixed $state): string => ReferralAdminPresenter::statusColor($state)),
                TextColumn::make('accepted_at')->dateTime()->sortable(),
                TextColumn::make('qualified_at')->dateTime()->sortable(),
                TextColumn::make('rewarded_at')->dateTime()->sortable(),
                TextColumn::make('expired_at')
                    ->label('Expiry')
                    ->dateTime()
                    ->sortable(),
            ]);

        if ($showFilters) {
            $table->filters([
                SelectFilter::make('status')
                    ->options(self::statusOptions()),
                SelectFilter::make('program_id')
                    ->label('Program')
                    ->options(fn (): array => ReferralProgram::query()->orderBy('name')->pluck('name', 'id')->all()),
            ]);
        }

        if ($showActions) {
            $table->recordActions([
                ViewAction::make(),
                Action::make('revoke')
                    ->requiresConfirmation()
                    ->modalHeading('Revoke referral invite')
                    ->schema([
                        Textarea::make('reason')
                            ->maxLength(500),
                    ])
                    ->visible(fn (ReferralInvite $record): bool => in_array(
                        $record->status,
                        [ReferralInviteStatus::Pending, ReferralInviteStatus::Accepted],
                        true,
                    ))
                    ->action(function (ReferralInvite $record, array $data): void {
                        try {
                            $invite = app(ReferralProgramGuard::class)->invite($record);
                            RevokeReferralInvite::run(
                                $invite,
                                is_string($data['reason'] ?? null) ? $data['reason'] : null,
                            );
                        } catch (Throwable $exception) {
                            Notification::make()
                                ->title('Referral invite could not be revoked')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Referral invite revoked')
                            ->success()
                            ->send();
                    }),
            ]);
        }

        return $table;
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $statuses = ReferralInviteStatus::cases();

        return array_combine(
            array_map(static fn (ReferralInviteStatus $status): string => $status->value, $statuses),
            array_map(static fn (ReferralInviteStatus $status): string => ReferralAdminPresenter::statusLabel($status), $statuses),
        );
    }
}
