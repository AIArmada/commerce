<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Models\CommunicationPreference;
use AIArmada\Filament\Communications\Resources\CommunicationPreferenceResource\Pages;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CommunicationPreferenceResource extends Resource
{
    protected static ?string $model = CommunicationPreference::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cog-6-tooth';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-communications.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-communications.navigation.sort');
    }

    /**
     * @return Builder<CommunicationPreference>
     */
    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('recipient_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('recipient_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('channel'),
                Tables\Columns\TextColumn::make('category'),
                Tables\Columns\TextColumn::make('enabled_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('disabled_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->options([
                        'email' => 'Email',
                        'sms' => 'SMS',
                        'push' => 'Push',
                        'in_app' => 'In-App',
                    ]),
                Tables\Filters\SelectFilter::make('category')
                    ->options(collect(CommunicationCategory::cases())->pluck('value', 'value')),
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
                Section::make('Recipient')
                    ->schema([
                        TextEntry::make('recipient_type'),
                        TextEntry::make('recipient_id'),
                    ])->columns(2),
                Section::make('Preferences')
                    ->schema([
                        TextEntry::make('channel'),
                        TextEntry::make('category'),
                        TextEntry::make('locale'),
                        TextEntry::make('timezone'),
                    ])->columns(2),
                Section::make('Status')
                    ->schema([
                        TextEntry::make('enabled_at')->dateTime(),
                        TextEntry::make('disabled_at')->dateTime(),
                        TextEntry::make('opted_in_at')->dateTime(),
                        TextEntry::make('opted_out_at')->dateTime(),
                        TextEntry::make('verified_at')->dateTime(),
                    ])->columns(3),
                Section::make('Quiet Hours')
                    ->schema([
                        TextEntry::make('quiet_hours_start'),
                        TextEntry::make('quiet_hours_end'),
                        TextEntry::make('quiet_hours_timezone'),
                    ])->columns(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommunicationPreferences::route('/'),
            'view' => Pages\ViewCommunicationPreference::route('/{record}'),
        ];
    }
}
