<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\RelationManagers;

use AIArmada\CommerceSupport\Filament\Concerns\VerifiesRelationManagerOwnerContext;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

final class SubscriptionsRelationManager extends RelationManager
{
    use VerifiesRelationManagerOwnerContext;

    protected static string $relationship = 'subscriptions';

    protected static ?string $title = 'Subscriptions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subscriber_type')->badge(),
                Tables\Columns\TextColumn::make('subscriber_id'),
                Tables\Columns\TextColumn::make('subscription_type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('subscribed_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(['active' => 'Active', 'muted' => 'Muted', 'unsubscribed' => 'Unsubscribed']),
            ])
            ->headerActions([])
            ->actions([]);
    }
}
