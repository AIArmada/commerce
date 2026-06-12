<?php
declare(strict_types=1);
namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\JsonDisplay;
use BackedEnum;
use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Models\EventChangeLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Builder;

final class EventChangeLogResource extends Resource
{
    protected static ?string $model = EventChangeLog::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 99;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-events.navigation.group');
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<EventChangeLog> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->whereHas('event', fn (Builder $eventQuery): Builder => OwnerUiScope::apply($eventQuery, includeGlobal: false))
            ->with(['event', 'occurrence', 'session']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.title')->label('Event'),
                Tables\Columns\TextColumn::make('change_type')->badge(),
                Tables\Columns\TextColumn::make('change_category')->badge(),
                Tables\Columns\TextColumn::make('impact_level')->badge()->color(fn (string $s) => match($s) {
                    'critical' => 'danger', 'high' => 'warning', 'medium' => 'info', default => 'gray',
                }),
                Tables\Columns\TextColumn::make('visibility')->badge(),
                Tables\Columns\IconColumn::make('requires_notification')->boolean(),
                Tables\Columns\TextColumn::make('changed_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('change_type'),
                Tables\Filters\SelectFilter::make('impact_level')->options(['critical' => 'Critical', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low']),
            ])
            ->defaultSort('changed_at', 'desc')
            ->actions([\Filament\Actions\ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make()->schema([
                \Filament\Infolists\Components\TextEntry::make('event.title'),
                \Filament\Infolists\Components\TextEntry::make('change_type')->badge(),
                \Filament\Infolists\Components\TextEntry::make('change_category')->badge(),
                \Filament\Infolists\Components\TextEntry::make('impact_level')->badge(),
                \Filament\Infolists\Components\TextEntry::make('old_value')
                    ->formatStateUsing(fn (mixed $state): string => JsonDisplay::format($state))
                    ->html()
                    ->visible(fn ($s) => !empty($s)),
                \Filament\Infolists\Components\TextEntry::make('new_value')
                    ->formatStateUsing(fn (mixed $state): string => JsonDisplay::format($state))
                    ->html()
                    ->visible(fn ($s) => !empty($s)),
                \Filament\Infolists\Components\TextEntry::make('reason'),
                \Filament\Infolists\Components\TextEntry::make('changed_at')->dateTime(),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \AIArmada\FilamentEvents\Resources\EventChangeLogResource\Pages\ListEventChangeLogs::route('/'),
            'view' => \AIArmada\FilamentEvents\Resources\EventChangeLogResource\Pages\ViewEventChangeLog::route('/{record}'),
        ];
    }
}
