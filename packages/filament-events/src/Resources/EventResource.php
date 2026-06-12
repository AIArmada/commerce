<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Models\Event;
use AIArmada\FilamentEvents\Actions\Exporter\EventExporter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-calendar';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-events.navigation.group');
    }

    /**
     * @return Builder<Event>
     */
    /* @phpstan-ignore return.type */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Event> $query */
        $query = parent::getEloquentQuery();

        return OwnerUiScope::apply($query, includeGlobal: false)
            ->withCount('occurrences');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending_review' => 'warning',
                        'scheduled' => 'info',
                        'published' => 'success',
                        'delayed', 'postponed' => 'warning',
                        'cancelled', 'voided', 'expired' => 'danger',
                        'completed' => 'success',
                        'archived' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('visibility')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success',
                        'unlisted' => 'warning',
                        'private', 'internal' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('delivery_mode')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'physical' => 'info',
                        'online' => 'success',
                        'hybrid' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('occurrences_count')
                    ->label('Occurrences')
                    ->counts('occurrences'),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'pending_review' => 'Pending Review',
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
                Tables\Filters\SelectFilter::make('delivery_mode')
                    ->options([
                        'physical' => 'Physical',
                        'online' => 'Online',
                        'hybrid' => 'Hybrid',
                    ]),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exporter(EventExporter::class)
                    ->label('Export Events'),
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Event $record): void {
                        app(EventLifecycleWorkflow::class)->publish($record);
                    })
                    ->visible(fn (Event $record) => $record->status === Event::DRAFT || $record->status === Event::PENDING_REVIEW)
                    ->requiresConfirmation(),
                Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->action(function (Event $record): void {
                        app(EventLifecycleWorkflow::class)->archive($record);
                    })
                    ->visible(fn (Event $record) => $record->status === Event::PUBLISHED)
                    ->requiresConfirmation(),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Textarea::make('reason')->required(),
                    ])
                    ->action(function (array $data, Event $record): void {
                        app(EventLifecycleWorkflow::class)->cancel($record, $data['reason']);
                    })
                    ->visible(fn (Event $record) => ! in_array($record->status, ['cancelled', 'completed', 'archived']))
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
                Section::make('Identity')
                    ->schema([
                        TextEntry::make('title'),
                        TextEntry::make('slug'),
                        TextEntry::make('summary'),
                        TextEntry::make('description'),
                        TextEntry::make('type'),
                    ])->columns(2),
                Section::make('Lifecycle')
                    ->schema([
                        TextEntry::make('status')->badge(),
                        TextEntry::make('visibility')->badge(),
                        TextEntry::make('delivery_mode')->badge(),
                        TextEntry::make('published_at')->dateTime(),
                        TextEntry::make('cancelled_at')->dateTime(),
                        TextEntry::make('postponed_at')->dateTime(),
                        TextEntry::make('archived_at')->dateTime(),
                    ])->columns(2),
                Section::make('Ownership')
                    ->schema([
                        TextEntry::make('owner_type'),
                        TextEntry::make('owner_id'),
                    ])->columns(2),
                Section::make('Metadata')
                    ->schema([
                        CodeEntry::make('metadata')
                            ->visible(fn (?array $state): bool => ! empty($state)),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            EventResource\RelationManagers\OccurrencesRelationManager::class,
            EventResource\RelationManagers\SessionsRelationManager::class,
            EventResource\RelationManagers\LocationsRelationManager::class,
            EventResource\RelationManagers\InvolvementsRelationManager::class,
            EventResource\RelationManagers\RegistrationsRelationManager::class,
            EventResource\RelationManagers\TicketTypesRelationManager::class,
            EventResource\RelationManagers\AttendancesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => EventResource\Pages\ListEvents::route('/'),
            'view' => EventResource\Pages\ViewEvent::route('/{record}'),
        ];
    }
}
