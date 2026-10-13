<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralProgramResource\Tables;

use AIArmada\FilamentReferrals\Resources\ReferralProgramResource\ReferralProgramResource;
use AIArmada\FilamentReferrals\Support\ReferralAdminPresenter;
use AIArmada\Referrals\Enums\ReferralProgramStatus;
use AIArmada\Referrals\Models\ReferralProgram;
use AIArmada\Referrals\Support\ReferralProgramGuard;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReferralProgramsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->url(fn (ReferralProgram $record): string => ReferralProgramResource::getUrl('view', ['record' => $record])),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => ReferralAdminPresenter::statusLabel($state))
                    ->color(fn (mixed $state): string => ReferralAdminPresenter::statusColor($state)),
                TextColumn::make('currency'),
                TextColumn::make('invites_count')
                    ->label('Invites')
                    ->numeric(),
                TextColumn::make('rewards_count')
                    ->label('Rewards')
                    ->numeric(),
                TextColumn::make('starts_at')->dateTime()->sortable(),
                TextColumn::make('ends_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(self::programStatusOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
                self::statusAction('activate', ReferralProgramStatus::Active, [
                    ReferralProgramStatus::Draft,
                    ReferralProgramStatus::Paused,
                ]),
                self::statusAction('pause', ReferralProgramStatus::Paused, [
                    ReferralProgramStatus::Active,
                ]),
                self::statusAction('archive', ReferralProgramStatus::Archived, [
                    ReferralProgramStatus::Draft,
                    ReferralProgramStatus::Active,
                    ReferralProgramStatus::Paused,
                ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function programStatusOptions(): array
    {
        $statuses = ReferralProgramStatus::cases();

        return array_combine(
            array_map(static fn (ReferralProgramStatus $status): string => $status->value, $statuses),
            array_map(static fn (ReferralProgramStatus $status): string => ReferralAdminPresenter::statusLabel($status), $statuses),
        );
    }

    /**
     * @param  array<int, ReferralProgramStatus>  $from
     */
    private static function statusAction(string $name, ReferralProgramStatus $to, array $from): Action
    {
        return Action::make($name)
            ->requiresConfirmation()
            ->visible(fn (ReferralProgram $record): bool => in_array($record->status, $from, true))
            ->action(function (ReferralProgram $record) use ($to, $from): void {
                $program = app(ReferralProgramGuard::class)->program($record);

                if (! in_array($program->status, $from, true)) {
                    Notification::make()
                        ->title('Referral program status could not be updated')
                        ->body('The program status changed. Refresh before trying again.')
                        ->danger()
                        ->send();

                    return;
                }

                $program->status = $to;
                $program->save();

                Notification::make()
                    ->title('Referral program status updated')
                    ->success()
                    ->send();
            });
    }
}
