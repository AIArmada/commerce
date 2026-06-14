<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources\AffiliateResource\RelationManagers;

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\FilamentAffiliates\Resources\AffiliateResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class DownlinesRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $title = 'Downlines';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('rank.name')
                    ->label('Rank')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('direct_downline_count')
                    ->label('Direct')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('total_downline_count')
                    ->label('Total Downlines')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('conversions_count')
                    ->label('Conversions')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('network_depth')
                    ->label('Depth')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('conversions'))
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Affiliate $record): string => AffiliateResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([])
            ->emptyStateHeading('No downlines yet');
    }
}
