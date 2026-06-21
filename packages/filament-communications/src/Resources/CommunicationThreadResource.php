<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Communications\Enums\ThreadStatus;
use AIArmada\Communications\Models\CommunicationThread;
use AIArmada\Filament\Communications\Resources\CommunicationThreadResource\Pages;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CommunicationThreadResource extends Resource
{
    protected static ?string $model = CommunicationThread::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-communications.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-communications.navigation.sort');
    }

    /**
     * @return Builder<CommunicationThread>
     */
    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('channel'),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('last_communication_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(ThreadStatus::cases())->pluck('value', 'value')),
                Tables\Filters\SelectFilter::make('channel')
                    ->options([
                        'email' => 'Email',
                        'sms' => 'SMS',
                        'push' => 'Push',
                        'in_app' => 'In-App',
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
                        TextEntry::make('title'),
                        TextEntry::make('channel'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('external_thread_id'),
                    ])->columns(2),
                Section::make('Timeline')
                    ->schema([
                        TextEntry::make('opened_at')->dateTime(),
                        TextEntry::make('last_communication_at')->dateTime(),
                        TextEntry::make('closed_at')->dateTime(),
                        TextEntry::make('archived_at')->dateTime(),
                        TextEntry::make('created_at')->dateTime(),
                    ])->columns(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommunicationThreads::route('/'),
            'view' => Pages\ViewCommunicationThread::route('/{record}'),
        ];
    }
}
