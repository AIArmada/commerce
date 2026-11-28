<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Contracts\EventCloneService;
use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Data\EventVisibility;
use AIArmada\Events\Data\RegistrationMode;
use AIArmada\Events\Features\Catalog\Models\EventSession;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\Cancelled as CancelledState;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\Completed as CompletedState;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\Delayed as DelayedState;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\Postponed as PostponedState;
use AIArmada\Events\Features\Catalog\States\OccurrenceStatus\OccurrenceStatus as OccurrenceStatusState;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\FilamentEvents\Actions\Exporter\EventSessionExporter;
use AIArmada\FilamentEvents\Actions\Importer\EventSessionImporter;
use AIArmada\Ticketing\Enums\PricingMode;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\HasColor;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

final class EventSessionResource extends Resource
{
    public static function getModel(): string
    {
        return ModelResolver::sessionClass();
    }

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

    public static function getEloquentQuery(): Builder
    {
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
                    ->badge()
                    ->color(fn (mixed $state): string => $state === 'hidden' ? 'danger' : 'gray'),
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
                    ->options(EventVisibility::options()),
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
                    ->label('Import Sessions')
                    ->visible(fn (): bool => static::canCreate())
                    ->authorize(fn (): bool => static::canCreate()),
                ExportAction::make()
                    ->exporter(EventSessionExporter::class)
                    ->label('Export Sessions'),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('delay')
                    ->label('Delay')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->form([
                        Textarea::make('reason'),
                        DateTimePicker::make('expected_starts_at')->label('Expected New Start')->rules(['nullable', 'date']),
                    ])
                    ->authorize(fn (EventSession $record): bool => Gate::forUser(Filament::auth()->user())->allows('update', $record))
                    ->action(function (array $data, EventSession $record): void {
                        Gate::forUser(Filament::auth()->user())->authorize('update', $record);

                        $expectedStartsAt = filled($data['expected_starts_at'] ?? null)
                            ? CarbonImmutable::parse($data['expected_starts_at'])
                            : null;

                        app(EventLifecycleWorkflow::class)->delay('session', (string) $record->getKey(), $data['reason'] ?? null, $expectedStartsAt);
                    })
                    ->visible(static fn (?EventSession $record): bool => $record?->status->canTransitionTo(DelayedState::class) ?? false)
                    ->requiresConfirmation(),
                Action::make('postpone')
                    ->label('Postpone')
                    ->icon('heroicon-o-calendar-days')
                    ->color('warning')
                    ->form([
                        Textarea::make('reason')->required(),
                    ])
                    ->authorize(fn (EventSession $record): bool => Gate::forUser(Filament::auth()->user())->allows('update', $record))
                    ->action(function (array $data, EventSession $record): void {
                        Gate::forUser(Filament::auth()->user())->authorize('update', $record);

                        app(EventLifecycleWorkflow::class)->postpone('session', (string) $record->getKey(), $data['reason']);
                    })
                    ->visible(static fn (?EventSession $record): bool => $record?->status->canTransitionTo(PostponedState::class) ?? false)
                    ->requiresConfirmation(),
                Action::make('cancel')
                    ->label('Cancel Session')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Textarea::make('reason')->required(),
                    ])
                    ->authorize(fn (EventSession $record): bool => Gate::forUser(Filament::auth()->user())->allows('update', $record))
                    ->action(function (array $data, EventSession $record): void {
                        Gate::forUser(Filament::auth()->user())->authorize('update', $record);

                        app(EventLifecycleWorkflow::class)->cancel('session', (string) $record->getKey(), $data['reason']);
                    })
                    ->visible(static fn (?EventSession $record): bool => $record?->status->canTransitionTo(CancelledState::class) ?? false)
                    ->requiresConfirmation(),
                Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->authorize(fn (EventSession $record): bool => Gate::forUser(Filament::auth()->user())->allows('update', $record))
                    ->action(function (EventSession $record): void {
                        Gate::forUser(Filament::auth()->user())->authorize('update', $record);

                        app(EventLifecycleWorkflow::class)->complete('session', (string) $record->getKey());
                    })
                    ->visible(static fn (?EventSession $record): bool => $record?->status->canTransitionTo(CompletedState::class) ?? false)
                    ->requiresConfirmation(),
                Action::make('clone')
                    ->label('Clone')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->authorize(fn (EventSession $record): bool => Gate::forUser(Filament::auth()->user())->allows('update', $record) && static::canCreate())
                    ->action(function (EventSession $record): void {
                        Gate::forUser(Filament::auth()->user())->authorize('update', $record);
                        Gate::forUser(Filament::auth()->user())->authorize('create', static::getModel());

                        $cloneId = app(EventCloneService::class)->cloneSession((string) $record->getKey());
                        redirect(EventSessionResource::getUrl('edit', ['record' => $cloneId]));
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
                        TextInput::make('timezone')
                            ->required()
                            ->maxLength(64),
                        Select::make('delivery_mode')
                            ->options([
                                'physical' => 'Physical',
                                'in_person' => 'In Person',
                                'online' => 'Online',
                                'hybrid' => 'Hybrid',
                            ])
                            ->required(),
                        TextInput::make('capacity')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->nullable(),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->integer()
                            ->default(0),
                    ])->columns(2),
                Section::make('Media')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('cover')
                            ->label('Cover image')
                            ->collection('cover')
                            ->image()
                            ->acceptedFileTypes(config('events.media.profiles.session.collections.cover.mimes', []))
                            ->imageEditor()
                            ->imageAspectRatio('16:9')
                            ->automaticallyOpenImageEditorForAspectRatio()
                            ->imageEditorAspectRatioOptions(['16:9', null])
                            ->automaticallyCropImagesToAspectRatio()
                            ->conversion('thumb')
                            ->responsiveImages(),
                    ]),
                Section::make('Lifecycle')
                    ->schema([
                        Select::make('status')
                            ->options(OccurrenceStatusState::options())
                            ->default('scheduled')
                            ->hiddenOn('edit')
                            ->required(),
                        Select::make('visibility')
                            ->options(EventVisibility::options())
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
        $relations = [
            EventSessionResource\RelationManagers\SessionInvolvementsRelationManager::class,
            EventSessionResource\RelationManagers\SessionLocationsRelationManager::class,
            EventSessionResource\RelationManagers\SessionRegistrationsRelationManager::class,
            EventSessionResource\RelationManagers\SessionAttendancesRelationManager::class,
            EventSessionResource\RelationManagers\SessionMaterialsRelationManager::class,
        ];

        $configuredRelations = config('filament-events.resources.session_relation_managers', []);

        if (! is_array($configuredRelations)) {
            return $relations;
        }

        foreach ($configuredRelations as $relationClass) {
            if (is_string($relationClass) && is_a($relationClass, RelationManager::class, true)) {
                $relations[] = $relationClass;
            }
        }

        return array_values(array_unique($relations));
    }
}
