<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\CreatorPostbackResource\Tables;

use AIArmada\AffiliateNetwork\Data\PostbackEventType;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Postbacks\Actions\DeleteCreatorPostback;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;
use AIArmada\AffiliateNetwork\Platform\ScopesByBelongsToOwner;
use Filament\Actions;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class CreatorPostbacksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('affiliate_id')
                    ->label('Affiliate ID')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('offer_id')
                    ->label('Offer')
                    ->state(fn (CreatorPostbackEndpoint $record): string => self::offerName($record->offer_id)),

                TextColumn::make('http_method')
                    ->label('Method')
                    ->badge(),

                TextColumn::make('url_template')
                    ->label('URL template')
                    ->limit(60)
                    ->copyable(),

                TextColumn::make('events')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => PostbackEventType::tryFrom((string) $state)?->label() ?? (string) $state),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('deliveries_count')
                    ->label('Deliveries')
                    ->counts('deliveries')
                    ->numeric(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),

                SelectFilter::make('http_method')
                    ->label('Method')
                    ->options([
                        'GET' => 'GET',
                        'POST' => 'POST',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->action(function (CreatorPostbackEndpoint $record): void {
                        app(DeleteCreatorPostback::class)->execute((string) $record->getKey());
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                app(DeleteCreatorPostback::class)->execute((string) $record->getKey());
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function offerName(?string $offerId): string
    {
        if ($offerId === null || $offerId === '') {
            return 'All offers';
        }

        $name = AffiliateOffer::withoutGlobalScope(ScopesByBelongsToOwner::class)
            ->whereKey($offerId)
            ->value('name');

        return is_string($name) && $name !== '' ? $name : 'All offers';
    }
}
