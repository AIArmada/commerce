<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventCloneService;
use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Data\EventVisibility;
use AIArmada\Events\Data\RegistrationMode;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Platform\ModelResolver;
use AIArmada\FilamentEvents\Actions\Exporter\EventExporter;
use AIArmada\FilamentEvents\Contracts\EventFormExtension;
use AIArmada\FilamentEvents\Extensions\ConfiguredFormExtensions;
use AIArmada\Ticketing\Enums\PricingMode;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\HasColor;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class EventResource extends Resource
{
    protected static ?string $model = null;

    public static function getModel(): string
    {
        return ModelResolver::eventClass();
    }

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-calendar';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-events.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-events.resources.navigation_sort.event');

        return is_numeric($sort) ? (int) $sort : null;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return OwnerUiScope::apply($query, includeGlobal: false)
            ->withCount('occurrences');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::getTableColumns())
            ->filters(static::getTableFilters())
            ->headerActions(static::getTableHeaderActions())
            ->actions(static::getTableRecordActions());
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema(static::getInfolistSections());
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema(static::getFormSections());
    }

    /**
     * @return array<int, Tables\Columns\Column>
     */
    protected static function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('title')
                ->searchable(),
            Tables\Columns\TextColumn::make('status')
                ->badge()
                ->color(static fn (mixed $state): string | array | null => $state instanceof HasColor ? $state->getColor() : 'gray'),
            Tables\Columns\TextColumn::make('visibility')
                ->badge()
                ->formatStateUsing(static fn (mixed $state): string => static::extractString($state))
                ->color(static fn (mixed $state): string => match (static::extractString($state)) {
                    'public' => 'success',
                    'unlisted' => 'warning',
                    'private', 'internal', 'hidden' => 'danger',
                    default => 'gray',
                }),
            Tables\Columns\TextColumn::make('delivery_mode')
                ->badge()
                ->formatStateUsing(static fn (mixed $state): string => static::extractString($state))
                ->color(static fn (mixed $state): string => match (static::extractString($state)) {
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
            Tables\Columns\TextColumn::make('last_state_change_at')
                ->dateTime()
                ->label('Last Status Change')
                ->sortable(),
            Tables\Columns\TextColumn::make('updated_at')
                ->dateTime()
                ->sortable(),
        ];
    }

    /**
     * @return array<int, Tables\Filters\BaseFilter>
     */
    protected static function getTableFilters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('status')
                ->options(static fn (): array => static::getStatusOptions()),
            Tables\Filters\SelectFilter::make('visibility')
                ->options(EventVisibility::options()),
            Tables\Filters\SelectFilter::make('delivery_mode')
                ->options([
                    'physical' => 'Physical',
                    'online' => 'Online',
                    'hybrid' => 'Hybrid',
                ]),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected static function getTableHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(EventExporter::class)
                ->label('Export Events'),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected static function getTableRecordActions(): array
    {
        return [
            ViewAction::make(),
            EditAction::make(),
            Action::make('publish')
                ->label('Publish')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->authorize(fn (Event $record): bool => Gate::forUser(Filament::auth()->user())->allows('publish', $record))
                ->action(static function (Event $record): void {
                    Gate::forUser(Filament::auth()->user())->authorize('publish', $record);

                    app(EventLifecycleWorkflow::class)->publish((string) $record->getKey());
                })
                ->visible(static fn (?Event $record): bool => $record !== null && in_array((string) $record->status, ['pending_review', 'scheduled', 'delayed', 'postponed', 'rescheduled'], true))
                ->requiresConfirmation(),
            Action::make('archive')
                ->label('Archive')
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->authorize(fn (Event $record): bool => Gate::forUser(Filament::auth()->user())->allows('archive', $record))
                ->action(static function (Event $record): void {
                    Gate::forUser(Filament::auth()->user())->authorize('archive', $record);

                    app(EventLifecycleWorkflow::class)->archive('event', (string) $record->getKey());
                })
                ->visible(static fn (?Event $record): bool => $record !== null && in_array((string) $record->status, ['draft', 'pending_review', 'scheduled', 'published', 'cancelled', 'completed', 'expired'], true))
                ->requiresConfirmation(),
            Action::make('cancel')
                ->label('Cancel')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->form([
                    Textarea::make('reason')->required(),
                ])
                ->authorize(fn (Event $record): bool => Gate::forUser(Filament::auth()->user())->allows('cancel', $record))
                ->action(static function (array $data, Event $record): void {
                    Gate::forUser(Filament::auth()->user())->authorize('cancel', $record);

                    app(EventLifecycleWorkflow::class)->cancel('event', (string) $record->getKey(), $data['reason']);
                })
                ->visible(static fn (?Event $record): bool => $record !== null && in_array((string) $record->status, ['pending_review', 'scheduled', 'published', 'delayed', 'postponed', 'rescheduled'], true))
                ->requiresConfirmation(),
            Action::make('clone')
                ->label('Clone')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->authorize(fn (Event $record): bool => Gate::forUser(Filament::auth()->user())->allows('update', $record) && static::canCreate())
                ->action(static function (Event $record): void {
                    Gate::forUser(Filament::auth()->user())->authorize('update', $record);
                    Gate::forUser(Filament::auth()->user())->authorize('create', static::getModel());

                    $cloneId = app(EventCloneService::class)->cloneEvent((string) $record->getKey(), [
                        'clone_occurrences' => false,
                    ]);
                    redirect(static::getUrl('edit', ['record' => $cloneId]));
                })
                ->requiresConfirmation(),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function getInfolistSections(): array
    {
        return [
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
                    TextEntry::make('visibility')->badge()->formatStateUsing(static fn (mixed $state): string => static::extractString($state)),
                    TextEntry::make('delivery_mode')->badge()->formatStateUsing(static fn (mixed $state): string => static::extractString($state)),
                    TextEntry::make('published_at')->dateTime(),
                    TextEntry::make('cancelled_at')->dateTime(),
                    TextEntry::make('postponed_at')->dateTime(),
                    TextEntry::make('archived_at')->dateTime(),
                    TextEntry::make('completed_at')->dateTime(),
                    TextEntry::make('last_state_change_at')->dateTime()->label('Last Status Change'),
                ])->columns(2),
            Section::make('Ownership')
                ->schema([
                    TextEntry::make('owner_type'),
                    TextEntry::make('owner_id'),
                ])->columns(2),
            Section::make('Metadata')
                ->schema([
                    CodeEntry::make('metadata')
                        ->visible(static fn (?array $state): bool => ! empty($state)),
                ]),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function getFormSections(): array
    {
        return [
            Section::make('Basic Details')
                ->schema([
                    TextInput::make('title')->required()->maxLength(255),
                    TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true, modifyRuleUsing: static function (Unique $rule): Unique {
                        if (! Event::ownerScopeConfig()->enabled) {
                            return $rule;
                        }

                        $owner = OwnerContext::resolve();

                        if (! $owner instanceof Model) {
                            return $rule->whereNull('owner_type')->whereNull('owner_id');
                        }

                        return $rule->where('owner_type', $owner->getMorphClass())->where('owner_id', $owner->getKey());
                    }),
                    Textarea::make('summary')->rows(3),
                    Textarea::make('description')->rows(5),
                ])->columns(2),
            ...static::configuredFormExtensions(),
            Section::make('Lifecycle')
                ->schema([
                    Select::make('status')
                        ->options(['draft' => 'Draft', 'pending_review' => 'Pending Review', 'scheduled' => 'Scheduled'])
                        ->default('draft')
                        ->hiddenOn('edit')
                        ->required(),
                    Select::make('visibility')
                        ->options(EventVisibility::options())
                        ->default('public')
                        ->hiddenOn('edit')
                        ->required(),
                    Select::make('delivery_mode')
                        ->options([
                            'physical' => 'Physical',
                            'online' => 'Online',
                            'hybrid' => 'Hybrid',
                        ])
                        ->default('physical'),
                ])->columns(3),
            Section::make('Pricing & Registration')
                ->schema([
                    Select::make('pricing_mode')
                        ->options(PricingMode::options())
                        ->placeholder('Derive from ticket types'),
                    Select::make('registration_mode')
                        ->options(RegistrationMode::options())
                        ->placeholder('Use config default'),
                    Select::make('issue_passes_for_free')
                        ->label('Issue passes for free registrations')
                        ->nullable()
                        ->options([
                            1 => 'Issue passes',
                            0 => 'Do not issue passes',
                        ])
                        ->placeholder('Inherit from parent/config')
                        ->helperText('Leave blank to use the configured or inherited default.'),
                ])->columns(2),
        ];
    }

    /**
     * @return array<int, Component>
     */
    protected static function configuredFormExtensions(): array
    {
        return ConfiguredFormExtensions::components('filament-events.resources.event_form_extensions', EventFormExtension::class);
    }

    /**
     * @return array<int, class-string>
     */
    protected static function getDefaultRelationManagers(): array
    {
        return [
            EventResource\RelationManagers\OccurrencesRelationManager::class,
            EventResource\RelationManagers\SessionsRelationManager::class,
            EventResource\RelationManagers\LocationsRelationManager::class,
            EventResource\RelationManagers\InvolvementsRelationManager::class,
            EventResource\RelationManagers\RegistrationsRelationManager::class,
            EventResource\RelationManagers\AttendancesRelationManager::class,
            EventResource\RelationManagers\ClassificationsRelationManager::class,
        ];
    }

    public static function getRelations(): array
    {
        $relations = static::getDefaultRelationManagers();

        $configuredRelations = config('filament-events.resources.event_relation_managers', []);

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

    public static function getPages(): array
    {
        return [
            'index' => EventResource\Pages\ListEvents::route('/'),
            'create' => EventResource\Pages\CreateEvent::route('/create'),
            'view' => EventResource\Pages\ViewEvent::route('/{record}'),
            'edit' => EventResource\Pages\EditEvent::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected static function getStatusOptions(): array
    {
        $modelClass = static::getModel();
        $statusCast = app($modelClass)->getCasts()['status'] ?? null;

        if ($statusCast === null) {
            return [];
        }

        if (method_exists($statusCast, 'getStatesLabel')) {
            return $statusCast::getStatesLabel($modelClass);
        }

        if (method_exists($statusCast, 'options')) {
            return $statusCast::options();
        }

        return [];
    }

    protected static function extractString(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if (is_string($value)) {
            return $value;
        }

        return '';
    }
}
