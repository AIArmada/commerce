<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Contracts\EventModerationWorkflow;
use AIArmada\Events\Data\EventDetailData;
use AIArmada\Events\Data\EventReviewSchemaData;
use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\EventVisibility;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventSeries;
use AIArmada\Events\Services\EventQueryService;
use AIArmada\Events\Support\EventModerationPolicy;
use AIArmada\FilamentEvents\Resources\EventResource\Pages;
use AIArmada\FilamentEvents\Resources\EventResource\RelationManagers;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return (string) config('filament-events.navigation.group', 'Events');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-events.navigation.resources.events', 2);
    }

    /**
     * @return Builder<Event>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Event> $query */
        $query = parent::getEloquentQuery();

        return OwnerUiScope::apply($query, includeGlobal: false)
            ->with(['series', 'product'])
            ->withCount('occurrences');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', EventStatus::Active)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        Section::make('Event')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, ?string $state): mixed => $set('slug', Str::slug($state ?? ''))),

                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),

                                Textarea::make('summary')
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Textarea::make('description')
                                    ->rows(8)
                                    ->columnSpanFull(),

                                Textarea::make('search_keywords')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->columnSpan(['lg' => 2]),

                        Section::make('Settings')
                            ->schema([
                                Select::make('event_series_id')
                                    ->label('Series')
                                    ->relationship(
                                        name: 'series',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
                                    )
                                    ->searchable()
                                    ->preload(),

                                Select::make('status')
                                    ->options(static::statusOptions())
                                    ->required()
                                    ->default(EventStatus::Draft->value),

                                Select::make('moderation_status')
                                    ->label('Moderation')
                                    ->options(static::moderationStatusOptions())
                                    ->required()
                                    ->default(EventModerationStatus::Approved->value),

                                Select::make('visibility')
                                    ->options(static::visibilityOptions())
                                    ->required()
                                    ->default(EventVisibility::Public->value),

                                TextInput::make('default_timezone')
                                    ->maxLength(64)
                                    ->default('Asia/Kuala_Lumpur'),

                                TextInput::make('default_duration_minutes')
                                    ->numeric()
                                    ->minValue(1)
                                    ->suffix('minutes'),

                                Select::make('product_id')
                                    ->label('Product')
                                    ->relationship(
                                        name: 'product',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false),
                                    )
                                    ->searchable()
                                    ->preload(),

                                DateTimePicker::make('published_at'),

                                DateTimePicker::make('public_starts_at'),

                                DateTimePicker::make('public_ends_at'),

                                static::jsonTextarea('media_references', 'Media references'),

                                static::jsonTextarea('taxonomy', 'Taxonomy'),

                                KeyValue::make('metadata'),
                            ])
                            ->columnSpan(['lg' => 1]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Event $record): string => $record->slug),

                Tables\Columns\TextColumn::make('series.name')
                    ->label('Series')
                    ->placeholder('No series')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (EventStatus $state): string => $state->label())
                    ->color(fn (EventStatus $state): string => $state->color()),

                Tables\Columns\TextColumn::make('moderation_status')
                    ->label('Moderation')
                    ->badge()
                    ->formatStateUsing(fn (EventModerationStatus $state): string => $state->label())
                    ->color(fn (EventModerationStatus $state): string => $state->color()),

                Tables\Columns\TextColumn::make('visibility')
                    ->badge()
                    ->formatStateUsing(fn (EventVisibility $state): string => $state->label())
                    ->color(fn (EventVisibility $state): string => $state->color()),

                Tables\Columns\TextColumn::make('occurrences_count')
                    ->label('Runs')
                    ->counts('occurrences')
                    ->sortable(),

                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->placeholder('Not linked')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(static::statusOptions()),

                Tables\Filters\SelectFilter::make('moderation_status')
                    ->label('Moderation')
                    ->options(static::moderationStatusOptions()),

                Tables\Filters\SelectFilter::make('visibility')
                    ->options(static::visibilityOptions()),

                Tables\Filters\SelectFilter::make('event_series_id')
                    ->label('Series')
                    ->options(static fn (): array => OwnerUiScope::apply(EventSeries::query(), includeGlobal: false)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Event')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug')
                            ->copyable(),
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (EventStatus $state): string => $state->label())
                            ->color(fn (EventStatus $state): string => $state->color()),
                        TextEntry::make('moderation_status')
                            ->label('Moderation')
                            ->badge()
                            ->formatStateUsing(fn (EventModerationStatus $state): string => $state->label())
                            ->color(fn (EventModerationStatus $state): string => $state->color()),
                        TextEntry::make('visibility')
                            ->badge()
                            ->formatStateUsing(fn (EventVisibility $state): string => $state->label())
                            ->color(fn (EventVisibility $state): string => $state->color()),
                        TextEntry::make('series.name')
                            ->label('Series')
                            ->placeholder('No series'),
                        TextEntry::make('product.name')
                            ->label('Product')
                            ->placeholder('Not linked'),
                        TextEntry::make('default_timezone')
                            ->placeholder('Not set'),
                        TextEntry::make('default_duration_minutes')
                            ->suffix(' minutes')
                            ->placeholder('Not set'),
                        TextEntry::make('published_at')
                            ->dateTime()
                            ->placeholder('Not set'),
                        TextEntry::make('public_starts_at')
                            ->dateTime()
                            ->placeholder('Not set'),
                        TextEntry::make('public_ends_at')
                            ->dateTime()
                            ->placeholder('Not set'),
                        TextEntry::make('summary')
                            ->columnSpanFull()
                            ->placeholder('No summary'),
                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description'),
                        TextEntry::make('search_keywords')
                            ->columnSpanFull()
                            ->placeholder('No search keywords'),
                        TextEntry::make('media_references')
                            ->label('Media references')
                            ->formatStateUsing(fn (mixed $state): string => static::formatJsonState($state))
                            ->columnSpanFull(),
                        TextEntry::make('taxonomy')
                            ->formatStateUsing(fn (mixed $state): string => static::formatJsonState($state))
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Read Model Snapshot')
                    ->description('Projections generated by the package EventQueryService from the EventDetailData DTO.')
                    ->schema([
                        TextEntry::make('snapshot.peopleNames')
                            ->label('Featured People')
                            ->state(fn (Event $record): string => self::formatStringList(self::snapshot($record)->peopleNames))
                            ->placeholder('No people linked')
                            ->columnSpanFull(),

                        TextEntry::make('snapshot.references')
                            ->label('Reference Materials')
                            ->state(fn (Event $record): string => static::formatJsonState(self::snapshot($record)->references))
                            ->placeholder('No references')
                            ->columnSpanFull(),

                        TextEntry::make('snapshot.metadata')
                            ->label('Metadata')
                            ->state(fn (Event $record): string => static::formatJsonState(self::snapshot($record)->metadata))
                            ->placeholder('No metadata')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible(),

                Section::make('Review Workflow')
                    ->description('Available actions driven by the package EventReviewSchemaData and EventModerationWorkflow.')
                    ->schema([
                        TextEntry::make('reviewSchema.actions')
                            ->label('Allowed actions for current state')
                            ->state(fn (Event $record): string => self::formatStringList(self::reviewSchema($record)->actions))
                            ->placeholder('No actions available')
                            ->columnSpanFull(),

                        TextEntry::make('reviewSchema.reasonCodes')
                            ->label('Configured reason codes')
                            ->state(function (Event $record): string {
                                $codes = self::reviewSchema($record)->reasonCodes;
                                $labels = [];
                                foreach ($codes as $key => $payload) {
                                    $label = is_array($payload) ? ($payload['label'] ?? $key) : $payload;
                                    $labels[] = "{$key}: {$label}";
                                }

                                return implode("\n", $labels);
                            })
                            ->placeholder('No reason codes configured')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OccurrencesRelationManager::class,
            RelationManagers\EventPeopleRelationManager::class,
            RelationManagers\EventSubmissionsRelationManager::class,
            RelationManagers\EventReviewsRelationManager::class,
            RelationManagers\EventChangeNoticesRelationManager::class,
            RelationManagers\EventAssetsRelationManager::class,
            RelationManagers\EventClassificationsRelationManager::class,
            RelationManagers\EventEngagementsRelationManager::class,
            RelationManagers\EventAttendanceRelationManager::class,
            RelationManagers\EventAgendaItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'view' => Pages\ViewEvent::route('/{record}'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'summary', 'description'];
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        return collect(EventStatus::cases())
            ->mapWithKeys(fn (EventStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function moderationStatusOptions(): array
    {
        return collect(EventModerationStatus::cases())
            ->mapWithKeys(fn (EventModerationStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function visibilityOptions(): array
    {
        return collect(EventVisibility::cases())
            ->mapWithKeys(fn (EventVisibility $visibility): array => [$visibility->value => $visibility->label()])
            ->all();
    }

    /**
     * Resolve the package-native EventDetailData read model for an event.
     * The infolist uses this so admin views read the same projection that
     * search payloads, MCP tools, and API adapters consume.
     */
    public static function snapshot(Event $event): EventDetailData
    {
        return app(EventQueryService::class)->detail($event);
    }

    /**
     * Resolve the package-native EventReviewSchemaData so the infolist can
     * surface the current state's allowed actions and configured reason codes
     * without re-deriving them in Filament.
     */
    public static function reviewSchema(Event $event): EventReviewSchemaData
    {
        return app(EventQueryService::class)->reviewSchema($event);
    }

    /**
     * Submit-for-review action delegating to the package's
     * EventModerationWorkflow contract. Gated by the schema's allowed actions
     * for the event's current moderation state.
     */
    public static function submitForReviewAction(): Action
    {
        return Action::make('submitForReview')
            ->label('Submit for Review')
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->visible(fn (Event $record): bool => in_array('submit', static::reviewSchema($record)->actions, true))
            ->requiresConfirmation()
            ->action(function (Event $record): void {
                $actor = auth()->user();
                app(EventModerationWorkflow::class)->submit($record, $actor instanceof Model ? $actor : null, [
                    'source' => 'filament',
                ]);
            });
    }

    /**
     * Approve action delegating to EventModerationWorkflow.
     */
    public static function approveAction(): Action
    {
        return Action::make('approveEvent')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Event $record): bool => in_array('approve', static::reviewSchema($record)->actions, true))
            ->requiresConfirmation()
            ->action(function (Event $record): void {
                $actor = auth()->user();
                app(EventModerationWorkflow::class)->approve($record, $actor instanceof Model ? $actor : null, [
                    'source' => 'filament',
                ]);
            });
    }

    /**
     * Request-changes action delegating to EventModerationWorkflow. The
     * reason_key field is required for this transition; the policy
     * determines that server-side.
     */
    public static function requestChangesAction(): Action
    {
        return Action::make('requestChanges')
            ->label('Request Changes')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->visible(fn (Event $record): bool => in_array('request_changes', static::reviewSchema($record)->actions, true))
            ->schema([
                Select::make('reason_key')
                    ->label('Reason')
                    ->options(fn (): array => static::reasonCodeOptions())
                    ->required()
                    ->helperText('A reason code is required when requesting changes.'),

                Textarea::make('note')
                    ->label('Note')
                    ->rows(3)
                    ->required()
                    ->helperText('A reviewer note is required when requesting changes.'),
            ])
            ->action(function (Event $record, array $data): void {
                $actor = auth()->user();
                app(EventModerationWorkflow::class)->requestChanges($record, $actor instanceof Model ? $actor : null, [
                    'source' => 'filament',
                    'reason_key' => $data['reason_key'] ?? null,
                    'note' => $data['note'] ?? null,
                ]);
            });
    }

    /**
     * Reject action delegating to EventModerationWorkflow.
     */
    public static function rejectEventAction(): Action
    {
        return Action::make('rejectEvent')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Event $record): bool => in_array('reject', static::reviewSchema($record)->actions, true))
            ->schema([
                Select::make('reason_key')
                    ->label('Reason')
                    ->options(fn (): array => static::reasonCodeOptions())
                    ->required(),

                Textarea::make('note')
                    ->label('Note')
                    ->rows(3)
                    ->required(),
            ])
            ->action(function (Event $record, array $data): void {
                $actor = auth()->user();
                app(EventModerationWorkflow::class)->reject($record, $actor instanceof Model ? $actor : null, [
                    'source' => 'filament',
                    'reason_key' => $data['reason_key'] ?? null,
                    'note' => $data['note'] ?? null,
                ]);
            });
    }

    /**
     * @return array<string, string>
     */
    public static function reasonCodeOptions(): array
    {
        $codes = EventModerationPolicy::reasonCodes();

        $options = [];
        foreach ($codes as $key => $payload) {
            $label = is_array($payload) ? ($payload['label'] ?? $key) : $payload;
            $options[(string) $key] = (string) $label;
        }

        return $options;
    }

    /**
     * @param  array<int, string>  $items
     */
    private static function formatStringList(array $items): string
    {
        $filtered = array_values(array_filter(
            $items,
            static fn (mixed $item): bool => is_string($item) && mb_trim($item) !== '',
        ));

        return $filtered === [] ? '' : implode(', ', $filtered);
    }

    private static function jsonTextarea(string $name, string $label): Textarea
    {
        return Textarea::make($name)
            ->label($label)
            ->rows(5)
            ->formatStateUsing(fn (mixed $state): ?string => static::formatNullableJsonState($state))
            ->dehydrateStateUsing(fn (?string $state): ?array => static::decodeJsonState($state))
            ->rules(['nullable', 'json'])
            ->columnSpanFull();
    }

    /**
     * @return array<int|string, mixed>|null
     */
    private static function decodeJsonState(?string $state): ?array
    {
        if ($state === null || mb_trim($state) === '') {
            return null;
        }

        $decoded = json_decode($state, true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function formatNullableJsonState(mixed $state): ?string
    {
        if ($state === null || $state === []) {
            return null;
        }

        return static::formatJsonState($state);
    }

    private static function formatJsonState(mixed $state): string
    {
        if (! is_array($state)) {
            return '';
        }

        return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
