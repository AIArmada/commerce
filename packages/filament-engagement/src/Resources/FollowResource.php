<?php

declare(strict_types=1);

namespace AIArmada\FilamentEngagement\Resources;

use AIArmada\CommerceSupport\Support\JsonDisplay;
use AIArmada\Engagement\Models\Follow;
use Filament\Forms;
use Filament\Infolists;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class FollowResource extends Resource
{
    protected static ?string $model = Follow::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static string|\UnitEnum|null $navigationGroup = 'Engagement';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->latest('followed_at');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('follower_type')->label('Follower Type')->badge(),
                Tables\Columns\TextColumn::make('follower_id')->label('Follower ID'),
                Tables\Columns\TextColumn::make('followable_type')->label('Followable Type')->badge(),
                Tables\Columns\TextColumn::make('followable_id')->label('Followable ID'),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'success' => 'active',
                    'warning' => 'muted',
                    'danger' => 'unfollowed',
                    'gray' => 'blocked',
                ]),
                Tables\Columns\TextColumn::make('notification_level')->badge(),
                Tables\Columns\TextColumn::make('followed_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('unfollowed_at')->dateTime()->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'muted' => 'Muted',
                    'unfollowed' => 'Unfollowed',
                    'blocked' => 'Blocked',
                ]),
                Tables\Filters\SelectFilter::make('follower_type')
                    ->options(fn (): array => Follow::query()
                        ->select('follower_type')
                        ->distinct()
                        ->orderBy('follower_type')
                        ->pluck('follower_type', 'follower_type')
                        ->all())
                    ->label('Follower Type'),
                Tables\Filters\SelectFilter::make('followable_type')
                    ->options(fn (): array => Follow::query()
                        ->select('followable_type')
                        ->distinct()
                        ->orderBy('followable_type')
                        ->pluck('followable_type', 'followable_type')
                        ->all())
                    ->label('Followable Type'),
            ])
            ->actions([
                \Filament\Actions\Action::make('mute')
                    ->action(fn (Follow $record) => $record->update(['status' => 'muted']))
                    ->requiresConfirmation()
                    ->visible(fn (Follow $record) => $record->isActive()),
                \Filament\Actions\Action::make('unmute')
                    ->action(fn (Follow $record) => $record->update(['status' => 'active']))
                    ->requiresConfirmation()
                    ->visible(fn (Follow $record) => $record->isMuted()),
                \Filament\Actions\Action::make('unfollow')
                    ->action(fn (Follow $record) => $record->update(['status' => 'unfollowed']))
                    ->requiresConfirmation()
                    ->visible(fn (Follow $record) => $record->isActive()),
            ])
            ->bulkActions([
                \Filament\Actions\BulkAction::make('mute')
                    ->action(fn ($records) => $records->each->update(['status' => 'muted'])),
                \Filament\Actions\BulkAction::make('unfollow')
                    ->action(fn ($records) => $records->each->update(['status' => 'unfollowed'])),
            ])
            ->defaultSort('followed_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()->schema([
                Infolists\Components\TextEntry::make('follower_type'),
                Infolists\Components\TextEntry::make('follower_id'),
                Infolists\Components\TextEntry::make('followable_type'),
                Infolists\Components\TextEntry::make('followable_id'),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('notification_level'),
                Infolists\Components\TextEntry::make('followed_at')->dateTime(),
                Infolists\Components\TextEntry::make('muted_at')->dateTime(),
                Infolists\Components\TextEntry::make('unfollowed_at')->dateTime(),
                Infolists\Components\TextEntry::make('metadata')
                    ->formatStateUsing(fn (mixed $state): string => JsonDisplay::format($state))
                    ->html()
                    ->visible(fn (?array $state): bool => ! empty($state)),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => FollowResource\Pages\ListFollows::route('/'),
        ];
    }
}
