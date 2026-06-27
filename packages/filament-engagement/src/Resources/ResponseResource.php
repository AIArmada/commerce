<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\JsonDisplay;
use AIArmada\Engagement\Contracts\EngagementManager;
use AIArmada\Engagement\Models\Response;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class ResponseResource extends Resource
{
    protected static ?string $model = Response::class;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-engagement.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-engagement.resources.navigation_sort.response');
    }

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-hand-thumb-up';

    public static function getEloquentQuery(): Builder
    {
        return OwnerUiScope::apply(parent::getEloquentQuery(), includeGlobal: false);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('responder_type')->badge(),
                Tables\Columns\TextColumn::make('responder_id'),
                Tables\Columns\TextColumn::make('respondable_type')->badge(),
                Tables\Columns\TextColumn::make('respondable_id'),
                Tables\Columns\TextColumn::make('response_type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('visibility')->badge(),
                Tables\Columns\TextColumn::make('responded_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('changed_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('response_type')
                    ->options([
                        'interested' => 'Interested',
                        'going' => 'Going',
                        'maybe' => 'Maybe',
                        'not_going' => 'Not Going',
                        'attending_online' => 'Attending Online',
                        'attending_physical' => 'Attending Physical',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'cancelled' => 'Cancelled',
                        'superseded' => 'Superseded',
                    ]),
                Tables\Filters\SelectFilter::make('visibility')
                    ->options([
                        'private' => 'Private',
                        'public' => 'Public',
                        'followers_only' => 'Followers Only',
                    ]),
            ])
            ->actions([
                Action::make('cancel')
                    ->visible(fn (Response $record): bool => $record->isActive())
                    ->action(fn (Response $record) => app(EngagementManager::class)
                        ->cancelResponse($record->responder, $record->respondable))
                    ->requiresConfirmation(),
                Action::make('restore')
                    ->visible(fn (Response $record): bool => $record->isCancelled())
                    ->action(fn (Response $record): Response => app(EngagementManager::class)
                        ->respond($record->responder, $record->respondable, $record->response_type, [
                            'visibility' => $record->visibility,
                            'source' => $record->source,
                            'metadata' => $record->metadata,
                        ]))
                    ->requiresConfirmation(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()->columns(2)->schema([
                    Infolists\Components\TextEntry::make('responder_type'),
                    Infolists\Components\TextEntry::make('responder_id'),
                    Infolists\Components\TextEntry::make('respondable_type'),
                    Infolists\Components\TextEntry::make('respondable_id'),
                    Infolists\Components\TextEntry::make('response_type'),
                    Infolists\Components\TextEntry::make('status'),
                    Infolists\Components\TextEntry::make('visibility'),
                    Infolists\Components\TextEntry::make('responded_at')->dateTime(),
                    Infolists\Components\TextEntry::make('changed_at')->dateTime(),
                    Infolists\Components\TextEntry::make('cancelled_at')->dateTime(),
                    Infolists\Components\TextEntry::make('expires_at')->dateTime(),
                    Infolists\Components\TextEntry::make('source'),
                    Infolists\Components\TextEntry::make('metadata')
                        ->formatStateUsing(fn (mixed $state): string => JsonDisplay::format($state))
                        ->html(),
                ]),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()->columns(2)->schema([
                    Forms\Components\TextInput::make('responder_type'),
                    Forms\Components\TextInput::make('responder_id'),
                    Forms\Components\TextInput::make('respondable_type'),
                    Forms\Components\TextInput::make('respondable_id'),
                    Forms\Components\TextInput::make('response_type'),
                    Forms\Components\TextInput::make('status'),
                    Forms\Components\TextInput::make('visibility'),
                    Forms\Components\DateTimePicker::make('responded_at'),
                    Forms\Components\DateTimePicker::make('changed_at'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ResponseResource\Pages\ListResponses::route('/'),
            'view' => ResponseResource\Pages\ViewResponse::route('/{record}'),
        ];
    }
}
