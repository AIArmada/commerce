<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Resources;

use AIArmada\CommerceSupport\Support\JsonDisplay;
use AIArmada\Engagement\Models\Reaction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

final class ReactionResource extends Resource
{
    protected static ?string $model = Reaction::class;

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-engagement.navigation.group');
    }

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-star';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reactor_type')->badge(),
                Tables\Columns\TextColumn::make('reactor_id'),
                Tables\Columns\TextColumn::make('reactable_type')->badge(),
                Tables\Columns\TextColumn::make('reactable_id'),
                Tables\Columns\TextColumn::make('reaction_type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('reacted_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('removed_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('reaction_type')
                    ->options([
                        'like' => 'Like',
                        'love' => 'Love',
                        'useful' => 'Useful',
                        'support' => 'Support',
                        'amin' => 'Amin',
                        'insightful' => 'Insightful',
                        'funny' => 'Funny',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'removed' => 'Removed',
                    ]),
            ])
            ->actions([
                Action::make('remove')
                    ->visible(fn (Reaction $record): bool => $record->isActive())
                    ->action(fn (Reaction $record) => $record->update(['status' => Reaction::STATUS_REMOVED]))
                    ->requiresConfirmation(),
                Action::make('restore')
                    ->visible(fn (Reaction $record): bool => $record->isRemoved())
                    ->action(fn (Reaction $record) => $record->update(['status' => Reaction::STATUS_ACTIVE]))
                    ->requiresConfirmation(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()->schema([
                    Infolists\Components\TextEntry::make('reactor_type'),
                    Infolists\Components\TextEntry::make('reactor_id'),
                    Infolists\Components\TextEntry::make('reactable_type'),
                    Infolists\Components\TextEntry::make('reactable_id'),
                    Infolists\Components\TextEntry::make('reaction_type'),
                    Infolists\Components\TextEntry::make('status'),
                    Infolists\Components\TextEntry::make('reacted_at')->dateTime(),
                    Infolists\Components\TextEntry::make('removed_at')->dateTime(),
                    Infolists\Components\TextEntry::make('source'),
                    Infolists\Components\TextEntry::make('metadata')
                        ->formatStateUsing(fn (mixed $state): string => JsonDisplay::format($state))
                        ->html(),
                ]),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('reactor_type'),
            Forms\Components\TextInput::make('reactor_id'),
            Forms\Components\TextInput::make('reactable_type'),
            Forms\Components\TextInput::make('reactable_id'),
            Forms\Components\TextInput::make('reaction_type'),
            Forms\Components\TextInput::make('status'),
            Forms\Components\DateTimePicker::make('reacted_at'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ReactionResource\Pages\ListReactions::route('/'),
            'view' => ReactionResource\Pages\ViewReaction::route('/{record}'),
        ];
    }
}
