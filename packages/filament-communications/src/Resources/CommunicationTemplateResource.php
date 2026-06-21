<?php

declare(strict_types=1);

namespace AIArmada\Filament\Communications\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Communications\Enums\CommunicationCategory;
use AIArmada\Communications\Enums\TemplateStatus;
use AIArmada\Communications\Models\CommunicationTemplate;
use AIArmada\Filament\Communications\Resources\CommunicationTemplateResource\Pages;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CommunicationTemplateResource extends Resource
{
    protected static ?string $model = CommunicationTemplate::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-communications.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-communications.navigation.sort');
    }

    /**
     * @return Builder<CommunicationTemplate>
     */
    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('key')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('category'),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(collect(TemplateStatus::cases())->pluck('value', 'value')),
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
                        TextEntry::make('name'),
                        TextEntry::make('key'),
                        TextEntry::make('description'),
                        TextEntry::make('category'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('default_locale'),
                    ])->columns(2),
                Section::make('Publishing')
                    ->schema([
                        TextEntry::make('published_at')->dateTime(),
                        TextEntry::make('disabled_at')->dateTime(),
                        TextEntry::make('created_at')->dateTime(),
                    ])->columns(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommunicationTemplates::route('/'),
            'view' => Pages\ViewCommunicationTemplate::route('/{record}'),
        ];
    }
}
