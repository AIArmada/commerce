<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Features\Catalog\EntryPoints\Filament\Resources\AffiliateOfferResource\RelationManagers;

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\AdjustConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\LockMaturedLegs;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\ReverseConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only money legs with reversal and adjustment.
 *
 * Legs are posted by conversion flows, so operators cannot create or
 * edit them here — but they can reverse a posted leg (refund,
 * chargeback) with a reason, or adjust a pending/posted leg's revenue.
 */
final class LegsRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'legs';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('external_reference')
            ->columns([
                TextColumn::make('affiliate_id')
                    ->label('Affiliate ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('external_reference')
                    ->label('Reference')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('event_type')
                    ->label('Event')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('coupon_code')
                    ->label('Coupon')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sub_id')
                    ->label('Sub ID')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('revenue_minor')
                    ->label('Revenue')
                    ->formatStateUsing(fn (NetworkConversionLeg $record): string => MoneyFormatter::formatMinor($record->revenue_minor, $record->revenue_currency ?? $record->commission_currency))
                    ->sortable(),

                TextColumn::make('commission_minor')
                    ->label('Commission')
                    ->formatStateUsing(fn (NetworkConversionLeg $record): string => MoneyFormatter::formatMinor($record->commission_minor, $record->commission_currency))
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('fee_minor')
                    ->label('Fee')
                    ->formatStateUsing(fn (NetworkConversionLeg $record): string => MoneyFormatter::formatMinor($record->fee_minor, $record->commission_currency))
                    ->toggleable()
                    ->sortable(),

                TextColumn::make('payout_minor')
                    ->label('Payout')
                    ->formatStateUsing(fn (NetworkConversionLeg $record): string => MoneyFormatter::formatMinor($record->payout_minor, $record->commission_currency))
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (LegStatus | string $state): string => $state instanceof LegStatus ? $state->color() : (LegStatus::tryFrom((string) $state)?->color() ?? 'gray'))
                    ->formatStateUsing(fn (LegStatus | string $state): string => $state instanceof LegStatus ? $state->label() : (LegStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                    ->sortable(),

                TextColumn::make('locks_at')
                    ->label('Locks at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('occurred_at')
                    ->label('Occurred')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('lock_matured')
                    ->label('Lock matured')
                    ->icon('heroicon-o-lock-closed')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (): void {
                        app(LockMaturedLegs::class)->execute();
                    }),
            ])
            ->actions([
                Action::make('reverse')
                    ->label('Reverse')
                    ->color('danger')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (NetworkConversionLeg $record): bool => $record->status === LegStatus::Posted)
                    ->form([
                        TextInput::make('reason')
                            ->label('Reason')
                            ->required()
                            ->maxLength(120)
                            ->placeholder('refund, chargeback, clawback…'),
                    ])
                    ->action(function (NetworkConversionLeg $record, array $data): void {
                        app(ReverseConversionLeg::class)->execute((string) $record->getKey(), (string) $data['reason']);
                    }),

                Action::make('adjust')
                    ->label('Adjust')
                    ->color('warning')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->visible(fn (NetworkConversionLeg $record): bool => in_array($record->status, [LegStatus::Pending, LegStatus::Posted], true) && $record->parent_leg_id === null)
                    ->form([
                        TextInput::make('adjustment_reference')
                            ->label('Adjustment reference')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('delta_revenue_minor')
                            ->label('Revenue delta (minor)')
                            ->rule('integer')
                            ->required(),
                        TextInput::make('reason')
                            ->label('Reason')
                            ->required()
                            ->maxLength(120),
                    ])
                    ->action(function (NetworkConversionLeg $record, array $data): void {
                        app(AdjustConversionLeg::class)->execute(
                            (string) $record->getKey(),
                            (string) $data['adjustment_reference'],
                            (int) $data['delta_revenue_minor'],
                            (string) $data['reason'],
                        );
                    }),
            ])
            ->bulkActions([]);
    }
}
