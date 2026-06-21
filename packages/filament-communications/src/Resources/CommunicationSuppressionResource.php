<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\SuppressionReason;
use AIArmada\Communications\Models\CommunicationSuppression;
use AIArmada\Filament\Communications\Resources\CommunicationSuppressionResource\Pages;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CommunicationSuppressionResource extends Resource
{
    protected static ?string $model = CommunicationSuppression::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-no-symbol';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-communications.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-communications.navigation.sort');
    }

    /**
     * @return Builder<CommunicationSuppression>
     */
    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reason')
                    ->badge(),
                Tables\Columns\TextColumn::make('channel'),
                Tables\Columns\TextColumn::make('category'),
                Tables\Columns\TextColumn::make('destination_hash')
                    ->label('Destination')
                    ->limit(12),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lifted_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('reason')
                    ->options(collect(SuppressionReason::cases())->pluck('value', 'value')),
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
                Section::make('Details')
                    ->schema([
                        TextEntry::make('reason')->badge(),
                        TextEntry::make('channel'),
                        TextEntry::make('category'),
                        TextEntry::make('source'),
                    ])->columns(2),
                Section::make('Validity')
                    ->schema([
                        TextEntry::make('starts_at')->dateTime(),
                        TextEntry::make('expires_at')->dateTime(),
                        TextEntry::make('lifted_at')->dateTime(),
                    ])->columns(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommunicationSuppressions::route('/'),
            'view' => Pages\ViewCommunicationSuppression::route('/{record}'),
        ];
    }
}
