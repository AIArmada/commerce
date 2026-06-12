<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\FilamentEvents\Actions\Exporter\EventOccurrenceExporter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class EventOccurrenceResource extends Resource
{
    protected static ?string $model = EventOccurrence::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-events.navigation.group');
    }

    /* @phpstan-ignore return.type */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<EventOccurrence> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->whereHas('event', fn (Builder $eventQuery): Builder => OwnerUiScope::apply($eventQuery, includeGlobal: false))
            ->with('event');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event'),
                Tables\Columns\TextColumn::make('title'),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'scheduled' => 'info',
                        'published' => 'success',
                        'delayed', 'postponed' => 'warning',
                        'cancelled' => 'danger',
                        'completed' => 'success',
                        'archived' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('visibility')
                    ->badge(),
                Tables\Columns\TextColumn::make('capacity')
                    ->numeric(),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('cancelled_at')
                    ->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'published' => 'Published',
                        'delayed' => 'Delayed',
                        'postponed' => 'Postponed',
                        'cancelled' => 'Cancelled',
                        'completed' => 'Completed',
                        'archived' => 'Archived',
                    ]),
                Tables\Filters\SelectFilter::make('visibility')
                    ->options([
                        'public' => 'Public',
                        'unlisted' => 'Unlisted',
                        'private' => 'Private',
                    ]),
                Tables\Filters\SelectFilter::make('event_id')
                    ->label('Event')
                    ->relationship(
                        'event',
                        'title',
                        modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
                    ),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(EventOccurrenceExporter::class)
                    ->label('Export Occurrences'),
                Action::make('delay')
                    ->label('Delay')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->form([
                        Textarea::make('reason'),
                        DateTimePicker::make('expected_starts_at')->label('Expected New Start'),
                    ])
                    ->action(function (array $data, EventOccurrence $record): void {
                        app(EventLifecycleWorkflow::class)->delay($record, $data['reason'] ?? null, $data['expected_starts_at'] ?? null);
                    })
                    ->visible(fn (EventOccurrence $record) => $record->status === 'published' || $record->status === 'scheduled')
                    ->requiresConfirmation(),
                Action::make('postpone')
                    ->label('Postpone')
                    ->icon('heroicon-o-calendar-x')
                    ->color('warning')
                    ->form([
                        Textarea::make('reason')->required(),
                    ])
                    ->action(function (array $data, EventOccurrence $record): void {
                        app(EventLifecycleWorkflow::class)->postpone($record, $data['reason']);
                    })
                    ->visible(fn (EventOccurrence $record) => $record->status === 'published' || $record->status === 'scheduled')
                    ->requiresConfirmation(),
                Action::make('cancel')
                    ->label('Cancel Occurrence')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Textarea::make('reason')->required(),
                    ])
                    ->action(function (array $data, EventOccurrence $record): void {
                        app(EventLifecycleWorkflow::class)->cancel($record, $data['reason']);
                    })
                    ->visible(fn (EventOccurrence $record) => ! in_array($record->status, ['cancelled', 'completed', 'archived']))
                    ->requiresConfirmation(),
                Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function (EventOccurrence $record): void {
                        app(EventLifecycleWorkflow::class)->complete($record);
                    })
                    ->visible(fn (EventOccurrence $record) => $record->status === 'published')
                    ->requiresConfirmation(),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Event')
                    ->schema([
                        TextEntry::make('event.title'),
                    ]),
                Section::make('Date / Time')
                    ->schema([
                        TextEntry::make('starts_at')->dateTime(),
                        TextEntry::make('ends_at')->dateTime(),
                        TextEntry::make('timezone'),
                    ])->columns(2),
                Section::make('Status')
                    ->schema([
                        TextEntry::make('status')->badge(),
                        TextEntry::make('visibility')->badge(),
                        TextEntry::make('delivery_mode')->badge(),
                        TextEntry::make('capacity')->numeric(),
                    ])->columns(2),
                Section::make('Lifecycle Timestamps')
                    ->schema([
                        TextEntry::make('published_at')->dateTime(),
                        TextEntry::make('delayed_at')->dateTime(),
                        TextEntry::make('postponed_at')->dateTime(),
                        TextEntry::make('cancelled_at')->dateTime(),
                        TextEntry::make('completed_at')->dateTime(),
                        TextEntry::make('archived_at')->dateTime(),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => EventOccurrenceResource\Pages\ListEventOccurrences::route('/'),
            'view' => EventOccurrenceResource\Pages\ViewEventOccurrence::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            EventOccurrenceResource\RelationManagers\OccurrenceSessionsRelationManager::class,
            EventOccurrenceResource\RelationManagers\OccurrenceLocationsRelationManager::class,
            EventOccurrenceResource\RelationManagers\OccurrenceInvolvementsRelationManager::class,
            EventOccurrenceResource\RelationManagers\OccurrenceRegistrationsRelationManager::class,
            EventOccurrenceResource\RelationManagers\OccurrenceTicketTypesRelationManager::class,
            EventOccurrenceResource\RelationManagers\OccurrenceAttendancesRelationManager::class,
        ];
    }
}
