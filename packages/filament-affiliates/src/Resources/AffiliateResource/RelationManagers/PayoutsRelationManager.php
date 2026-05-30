<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers;

use AIArmada\Affiliates\Models\AffiliatePayout;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class PayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'payouts';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('total_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn (AffiliatePayout $record): string => MoneyFormatter::formatMinor($record->total_minor, $record->currency))
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('conversion_count')
                    ->label('Conversions')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('scheduled_at')
                    ->label('Scheduled')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('paid_at')
                    ->label('Paid')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (AffiliatePayout $record): string => AffiliatePayoutResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No payouts yet');
    }
}
