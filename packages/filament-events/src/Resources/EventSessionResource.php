<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Contracts\EventCloneService;
use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Enums\RegistrationMode;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\States\OccurrenceStatus\OccurrenceStatus as OccurrenceStatusState;
use AIArmada\FilamentEvents\Actions\Exporter\EventSessionExporter;
use AIArmada\FilamentEvents\Actions\Importer\EventSessionImporter;
use AIArmada\Ticketing\Enums\PricingMode;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\HasColor;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class EventSessionResource extends Resource
{
    protected static ?string $model = EventSession::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-list-bullet';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-events.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-events.resources.navigation_sort.session');

        return is_numeric($sort) ? (int) $sort : null;
    }

    /* @phpstan-ignore return.type */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<EventSession> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->whereHas('event', fn (Builder $eventQuery): Builder => OwnerUiScope::apply($eventQuery, includeGlobal: false))
            ->with(['event', 'occurrence']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.title')
                    ->label('Event'),
                Tables\Columns\TextColumn::make('occurrence.title')
                    ->label('Occurrence'),
                Tables\Columns\TextColumn::make('title'),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (mixed $state): string | array | null => $state instanceof HasColor ? $state->getColor() : 'gray'),
                Tables\Columns\TextColumn::make('visibility')
                    ->badge(),
                Tables\Columns\TextColumn::make('capacity')
                    ->numeric(),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('cancelled_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(OccurrenceStatusState::options()),
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
                Tables\Filters\SelectFilter::make('event_occurrence_id')
                    ->label('Occurrence')
                    ->relationship(
                        'occurrence',
                        'title',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->whereHas(
                            'event',
                            fn (Builder $eventQuery): Builder => OwnerUiScope::apply($eventQuery, includeGlobal: false),
                        ),
                    ),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(EventSessionImporter::class)
                    ->label('Import Sessions'),
                ExportAction::make()
                    ->exporter(EventSessionExporter::class)
                    ->label('Export Sessions'),
                Action::make('delay')
                    ->label('Delay')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->form([
                        Textarea::make('reason'),
                        DateTimePicker::make('expected_starts_at')->label('Expected New Start'),
                    ])
                    ->action(function (array $data, EventSession $record): void {
                        app(EventLifecycleWorkflow::class)->delay($record, $data['reason'] ?? null, $data['expected_starts_at'] ?? null);
                    })
                    ->visible(fn (?EventSession $record) => in_array((string) ($record?->status ?? ''), ['published', 'scheduled'], true))
                    ->requiresConfirmation(),
                Action::make('postpone')
                    ->label('Postpone')
                    ->icon('heroicon-o-calendar-x')
                    ->color('warning')
                    ->form([
                        Textarea::make('reason')->required(),
                    ])
                    ->action(function (array $data, EventSession $record): void {
                        app(EventLifecycleWorkflow::class)->postpone($record, $data['reason']);
                    })
                    ->visible(fn (?EventSession $record) => in_array((string) ($record?->status ?? ''), ['published', 'scheduled'], true))
                    ->requiresConfirmation(),
                Action::make('cancel')
                    ->label('Cancel Session')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Textarea::make('reason')->required(),
                    ])
                    ->action(function (array $data, EventSession $record): void {
                        app(EventLifecycleWorkflow::class)->cancel($record, $data['reason']);
                    })
                    ->visible(fn (?EventSession $record) => ! in_array((string) ($record?->status ?? ''), ['cancelled', 'completed', 'archived'], true))
                    ->requiresConfirmation(),
                Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function (EventSession $record): void {
                        app(EventLifecycleWorkflow::class)->complete($record);
                    })
                    ->visible(fn (?EventSession $record) => (string) ($record?->status ?? '') === 'published')
                    ->requiresConfirmation(),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('clone')
                    ->label('Clone')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->action(function (EventSession $record): void {
                        $clone = app(EventCloneService::class)->cloneSession($record);
                        redirect(EventSessionResource::getUrl('edit', ['record' => $clone]));
                    })
                    ->requiresConfirmation(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Session Details')
                    ->schema([
                        TextEntry::make('event.title'),
                        TextEntry::make('occurrence.title'),
                        TextEntry::make('title'),
                        TextEntry::make('slug'),
                        TextEntry::make('summary'),
                        TextEntry::make('description'),
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
                        TextEntry::make('sort_order')->numeric(),
                    ])->columns(2),
                Section::make('Lifecycle Timestamps')
                    ->schema([
                        TextEntry::make('published_at')->dateTime(),
                        TextEntry::make('delayed_at')->dateTime(),
                        TextEntry::make('rescheduled_at')->dateTime(),
                        TextEntry::make('postponed_at')->dateTime(),
                        TextEntry::make('cancelled_at')->dateTime(),
                        TextEntry::make('completed_at')->dateTime(),
                        TextEntry::make('archived_at')->dateTime(),
                    ])->columns(2),
            ]);
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Session Details')
                    ->schema([
                        Select::make('event_occurrence_id')
                            ->label('Occurrence')
                            ->relationship(
                                'occurrence',
                                'title',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->whereHas(
                                    'event',
                                    fn (Builder $eventQuery): Builder => OwnerUiScope::apply($eventQuery, includeGlobal: false),
                                ),
                            )
                            ->searchable()
                            ->preload()
                            ->hiddenOn('edit')
                            ->required(),
                        TextInput::make('title')->required()->maxLength(255),
                        TextInput::make('slug')->maxLength(255)->unique(ignoreRecord: true),
                        Textarea::make('summary')->rows(3),
                        Textarea::make('description')->rows(5),
                        DateTimePicker::make('starts_at')
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->required(),
                    ])->columns(2),
                Section::make('Lifecycle')
                    ->schema([
                        Select::make('status')
                            ->options(OccurrenceStatusState::options())
                            ->default('scheduled')
                            ->hiddenOn('edit')
                            ->required(),
                        Select::make('visibility')
                            ->options([
                                'public' => 'Public',
                                'unlisted' => 'Unlisted',
                                'private' => 'Private',
                            ])
                            ->placeholder('Inherit from occurrence')
                            ->helperText('Leave blank to use the occurrence visibility.'),
                    ])->columns(2),
                Section::make('Pricing & Registration')
                    ->schema([
                        Select::make('pricing_mode')
                            ->options(PricingMode::options())
                            ->placeholder('Inherit from parent'),
                        Select::make('registration_mode')
                            ->options(RegistrationMode::options())
                            ->placeholder('Inherit from parent'),
                        Select::make('issue_passes_for_free')
                            ->label('Issue passes for free registrations')
                            ->nullable()
                            ->options([
                                1 => 'Issue passes',
                                0 => 'Do not issue passes',
                            ])
                            ->placeholder('Inherit from parent')
                            ->helperText('Leave blank to use the inherited or configured default.'),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => EventSessionResource\Pages\ListEventSessions::route('/'),
            'create' => EventSessionResource\Pages\CreateEventSession::route('/create'),
            'view' => EventSessionResource\Pages\ViewEventSession::route('/{record}'),
            'edit' => EventSessionResource\Pages\EditEventSession::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            EventSessionResource\RelationManagers\SessionInvolvementsRelationManager::class,
            EventSessionResource\RelationManagers\SessionLocationsRelationManager::class,
            EventSessionResource\RelationManagers\SessionRegistrationsRelationManager::class,
            EventSessionResource\RelationManagers\SessionAttendancesRelationManager::class,
            EventSessionResource\RelationManagers\SessionMaterialsRelationManager::class,
        ];
    }
}
