<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Communications\Enums\DeliveryStatus;
use AIArmada\Communications\Models\CommunicationDelivery;
use AIArmada\Filament\Communications\Resources\CommunicationDeliveryResource\Pages;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CommunicationDeliveryResource extends Resource
{
    protected static ?string $model = CommunicationDelivery::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-truck';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-communications.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-communications.navigation.sort');
    }

    /**
     * @return Builder<CommunicationDelivery>
     */
    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('channel'),
                Tables\Columns\TextColumn::make('provider'),
                Tables\Columns\TextColumn::make('recipient_id')
                    ->label('Recipient')
                    ->searchable(),
                Tables\Columns\TextColumn::make('communication_id')
                    ->label('Communication')
                    ->searchable(),
                Tables\Columns\TextColumn::make('attempt_count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(DeliveryStatus::cases())->pluck('value', 'value')),
                Tables\Filters\SelectFilter::make('channel')
                    ->options([
                        'email' => 'Email',
                        'sms' => 'SMS',
                        'push' => 'Push',
                        'in_app' => 'In-App',
                    ]),
                Tables\Filters\SelectFilter::make('provider')
                    ->options([
                        'ses' => 'SES',
                        'sendgrid' => 'SendGrid',
                        'twilio' => 'Twilio',
                        'slack' => 'Slack',
                        'fcm' => 'FCM',
                        'apns' => 'APNS',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Details')
                    ->schema([
                        TextEntry::make('status')->badge(),
                        TextEntry::make('channel'),
                        TextEntry::make('provider'),
                        TextEntry::make('attempt_count')->numeric(),
                        TextEntry::make('max_attempts')->numeric(),
                    ])->columns(2),
                Section::make('Cost')
                    ->schema([
                        TextEntry::make('cost_minor')->numeric(),
                        TextEntry::make('cost_currency'),
                    ])->columns(2),
                Section::make('Timeline')
                    ->schema([
                        TextEntry::make('scheduled_at')->dateTime(),
                        TextEntry::make('queued_at')->dateTime(),
                        TextEntry::make('sending_at')->dateTime(),
                        TextEntry::make('accepted_at')->dateTime(),
                        TextEntry::make('sent_at')->dateTime(),
                        TextEntry::make('received_at')->dateTime(),
                        TextEntry::make('delivered_at')->dateTime(),
                        TextEntry::make('opened_at')->dateTime(),
                        TextEntry::make('read_at')->dateTime(),
                        TextEntry::make('clicked_at')->dateTime(),
                        TextEntry::make('bounced_at')->dateTime(),
                        TextEntry::make('complained_at')->dateTime(),
                        TextEntry::make('failed_at')->dateTime(),
                        TextEntry::make('cancelled_at')->dateTime(),
                        TextEntry::make('expired_at')->dateTime(),
                        TextEntry::make('suppressed_at')->dateTime(),
                        TextEntry::make('last_attempt_at')->dateTime(),
                    ])->columns(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommunicationDeliveries::route('/'),
            'view' => Pages\ViewCommunicationDelivery::route('/{record}'),
        ];
    }
}
