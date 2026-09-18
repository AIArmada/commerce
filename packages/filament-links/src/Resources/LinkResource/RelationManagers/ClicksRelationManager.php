<?php

declare(strict_types=1);

namespace AIArmada\FilamentLinks\Resources\LinkResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class ClicksRelationManager extends RelationManager
{
    protected static string $relationship = 'clicks';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('occurred_at')
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Clicked at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_bot')
                    ->label('Bot')
                    ->boolean(),

                Tables\Columns\TextColumn::make('device_type')
                    ->label('Device')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('browser')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('os')
                    ->label('OS')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('referrer')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('utm_source')
                    ->label('Source')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('utm_campaign')
                    ->label('Campaign')
                    ->toggleable(),
            ])
            ->defaultSort('occurred_at', 'desc');
    }
}
