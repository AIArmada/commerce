<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers;

use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Services\NetworkBooks;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only money legs with reversal.
 *
 * Legs are posted by conversion flows, so operators cannot create or
 * edit them here — but they can reverse a posted leg (refund,
 * chargeback) with a reason, which marks the leg and posts the negated
 * companion.
 */
final class LegsRelationManager extends RelationManager
{
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
                    ->color(fn (LegStatus | string $state): string => $state instanceof LegStatus ? $state->color() : LegStatus::from((string) $state)->color())
                    ->formatStateUsing(fn (LegStatus | string $state): string => $state instanceof LegStatus ? $state->label() : LegStatus::from((string) $state)->label())
                    ->sortable(),

                TextColumn::make('occurred_at')
                    ->label('Occurred')
                    ->dateTime()
                    ->sortable(),
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
                        app(NetworkBooks::class)->reverse($record, (string) $data['reason']);
                    }),
            ])
            ->bulkActions([]);
    }
}
