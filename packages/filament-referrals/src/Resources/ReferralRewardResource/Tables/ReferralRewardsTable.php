<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Resources\ReferralRewardResource\Tables;

use AIArmada\FilamentReferrals\Support\ReferralAdminPresenter;
use AIArmada\Referrals\Enums\ReferralRewardSide;
use AIArmada\Referrals\Enums\ReferralRewardStatus;
use AIArmada\Referrals\Models\ReferralProgram;
use AIArmada\Referrals\Models\ReferralReward;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReferralRewardsTable
{
    public static function configure(
        Table $table,
        bool $showProgramFilter = true,
        bool $showViewAction = true,
    ): Table {
        $filters = [
            SelectFilter::make('status')
                ->options(self::statusOptions()),
            SelectFilter::make('driver')
                ->options(ReferralAdminPresenter::driverOptions()),
            SelectFilter::make('side')
                ->options(self::sideOptions()),
        ];

        if ($showProgramFilter) {
            $filters[] = SelectFilter::make('program_id')
                ->label('Program')
                ->options(fn (): array => ReferralProgram::query()->orderBy('name')->pluck('name', 'id')->all());
        }

        $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('program.name')
                    ->label('Program')
                    ->searchable(),
                TextColumn::make('invite_id')
                    ->label('Invite')
                    ->formatStateUsing(fn (mixed $state): string => ReferralAdminPresenter::shortId(is_string($state) ? $state : null)),
                TextColumn::make('side')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => ReferralAdminPresenter::statusLabel($state)),
                TextColumn::make('beneficiary_label')
                    ->label('Beneficiary')
                    ->state(fn (ReferralReward $record): string => ReferralAdminPresenter::morphLabel($record->beneficiary)),
                TextColumn::make('driver')
                    ->formatStateUsing(fn (mixed $state): string => ReferralAdminPresenter::statusLabel(is_string($state) ? $state : null)),
                TextColumn::make('value')
                    ->label('Amount / points')
                    ->state(fn (ReferralReward $record): string => ReferralAdminPresenter::rewardValue($record)),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => ReferralAdminPresenter::statusLabel($state))
                    ->color(fn (mixed $state): string => ReferralAdminPresenter::statusColor($state)),
                TextColumn::make('external_reference'),
                TextColumn::make('fulfilled_at')->dateTime()->sortable(),
                TextColumn::make('reversed_at')->dateTime()->sortable(),
            ])
            ->filters($filters)
            ->recordActions([
                ...($showViewAction ? [ViewAction::make()] : []),
                ...ReferralRewardActions::make(),
            ]);

        return $table;
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $statuses = ReferralRewardStatus::cases();

        return array_combine(
            array_map(static fn (ReferralRewardStatus $status): string => $status->value, $statuses),
            array_map(static fn (ReferralRewardStatus $status): string => ReferralAdminPresenter::statusLabel($status), $statuses),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function sideOptions(): array
    {
        $sides = ReferralRewardSide::cases();

        return array_combine(
            array_map(static fn (ReferralRewardSide $side): string => $side->value, $sides),
            array_map(static fn (ReferralRewardSide $side): string => ReferralAdminPresenter::statusLabel($side), $sides),
        );
    }
}
