<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Resources;

use AIArmada\CommerceSupport\Support\JsonDisplay;
use AIArmada\Engagement\Models\Subscription;
use Filament\Forms;
use Filament\Infolists;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

final class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Engagement';

    protected static ?int $navigationSort = 6;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('subscriber_type')->badge(),
                Tables\Columns\TextColumn::make('subscriber_id'),
                Tables\Columns\TextColumn::make('subscribable_type')->badge()->placeholder('-'),
                Tables\Columns\TextColumn::make('subscribable_id'),
                Tables\Columns\TextColumn::make('subscription_type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('notification_level')->badge(),
                Tables\Columns\TextColumn::make('subscribed_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('expires_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subscription_type')
                    ->options([
                        'updates' => 'Updates',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'muted' => 'Muted',
                        'unsubscribed' => 'Unsubscribed',
                        'expired' => 'Expired',
                    ]),
                Tables\Filters\SelectFilter::make('notification_level')
                    ->options([
                        'all' => 'All',
                        'none' => 'None',
                        'digest' => 'Digest',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('mute')
                    ->action(fn (Subscription $record) => $record->update(['status' => Subscription::STATUS_MUTED]))
                    ->requiresConfirmation(),
                \Filament\Actions\Action::make('unmute')
                    ->action(fn (Subscription $record) => $record->update(['status' => Subscription::STATUS_ACTIVE]))
                    ->requiresConfirmation(),
                \Filament\Actions\Action::make('unsubscribe')
                    ->action(fn (Subscription $record) => $record->update(['status' => Subscription::STATUS_UNSUBSCRIBED]))
                    ->requiresConfirmation(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Subscriber Information')->columns(2)->schema([
                    Infolists\Components\TextEntry::make('subscriber_type'),
                    Infolists\Components\TextEntry::make('subscriber_id'),
                ]),
                Section::make('Subscription Details')->columns(2)->schema([
                    Infolists\Components\TextEntry::make('subscribable_type'),
                    Infolists\Components\TextEntry::make('subscribable_id'),
                    Infolists\Components\TextEntry::make('subscription_type'),
                    Infolists\Components\TextEntry::make('status'),
                    Infolists\Components\TextEntry::make('subscribed_at')->dateTime(),
                    Infolists\Components\TextEntry::make('expires_at')->dateTime(),
                    Infolists\Components\TextEntry::make('muted_at')->dateTime(),
                    Infolists\Components\TextEntry::make('unsubscribed_at')->dateTime(),
                ]),
                Section::make('Criteria')
                    ->visible(fn (Subscription $record): bool => ! empty($record->criteria))
                    ->schema([
                        Infolists\Components\TextEntry::make('criteria')
                            ->formatStateUsing(fn (mixed $state): string => JsonDisplay::format($state))
                            ->html(),
                    ]),
                Section::make('Notification Preferences')->columns(2)->schema([
                    Infolists\Components\TextEntry::make('notification_level'),
                    Infolists\Components\TextEntry::make('notification_preferences')
                        ->formatStateUsing(fn (mixed $state): string => JsonDisplay::format($state))
                        ->html(),
                ]),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('subscriber_type'),
                Forms\Components\TextInput::make('subscriber_id'),
                Forms\Components\TextInput::make('subscribable_type'),
                Forms\Components\TextInput::make('subscribable_id'),
                Forms\Components\TextInput::make('subscription_type'),
                Forms\Components\TextInput::make('status'),
                Forms\Components\TextInput::make('notification_level'),
                Forms\Components\DateTimePicker::make('subscribed_at'),
                Forms\Components\DateTimePicker::make('expires_at'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => SubscriptionResource\Pages\ListSubscriptions::route('/'),
            'view' => SubscriptionResource\Pages\ViewSubscription::route('/{record}'),
        ];
    }
}
