<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Contracts\EventCloneService;
use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Enums\PricingMode;
use AIArmada\Events\Enums\RegistrationMode;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Support\ModelResolver;
use AIArmada\FilamentEvents\Actions\Exporter\EventExporter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\HasColor;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class EventResource extends Resource
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
                    ->color(fn (mixed $state): string | array | null => $state instanceof HasColor ? $state->getColor() : 'gray'),
                Tables\Columns\TextColumn::make('visibility')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::extractString($state))
                    ->color(fn (mixed $state): string => match (self::extractString($state)) {
                        'public' => 'success',
                        'unlisted' => 'warning',
                        'private', 'internal' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('delivery_mode')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::extractString($state))
                    ->color(fn (mixed $state): string => match (self::extractString($state)) {
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
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(fn (): array => static::getStatusOptions()),
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
                    ->visible(fn (?Event $record) => $record !== null && in_array((string) $record->status, ['draft', 'pending', 'pending_review', 'needs_changes'], true))
                    ->requiresConfirmation(),
                Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->action(function (Event $record): void {
                        app(EventLifecycleWorkflow::class)->archive($record);
                    })
                    ->visible(fn (?Event $record) => $record !== null && (string) $record->status === 'approved')
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
                    ->visible(fn (?Event $record) => $record !== null && ! in_array((string) $record->status, ['cancelled', 'completed', 'archived'], true))
                    ->requiresConfirmation(),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('clone')
                    ->label('Clone')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->action(function (Event $record): void {
                        $clone = app(EventCloneService::class)->cloneEvent($record, [
                            'clone_occurrences' => false,
                        ]);
                        redirect(EventResource::getUrl('edit', ['record' => $clone]));
                    })
                    ->requiresConfirmation(),
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
                        TextEntry::make('visibility')->badge()->formatStateUsing(fn (mixed $state): string => self::extractString($state)),
                        TextEntry::make('delivery_mode')->badge()->formatStateUsing(fn (mixed $state): string => self::extractString($state)),
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
                            ->visible(fn (?array $state): bool => ! empty($state)),
                    ]),
            ]);
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Basic Details')
                    ->schema([
                        TextInput::make('title')->required()->maxLength(255),
                        TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),
                        Textarea::make('summary')->rows(3),
                        Textarea::make('description')->rows(5),
                    ])->columns(2),
                Section::make('Lifecycle')
                    ->schema([
                        Select::make('status')
                            ->options(fn (): array => static::getStatusOptions())
                            ->default('draft')
                            ->hiddenOn('edit')
                            ->required(),
                        Select::make('visibility')
                            ->options([
                                'public' => 'Public',
                                'unlisted' => 'Unlisted',
                                'private' => 'Private',
                                'internal' => 'Internal',
                            ])
                            ->default('public')
                            ->hiddenOn('edit')
                            ->required(),
                        Select::make('delivery_mode')
                            ->options([
                                'physical' => 'Physical',
                                'online' => 'Online',
                                'hybrid' => 'Hybrid',
                            ])
                            ->default('physical')
                            ->hiddenOn('edit'),
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
            EventResource\RelationManagers\AttendancesRelationManager::class,
            EventResource\RelationManagers\ClassificationsRelationManager::class,
        ];
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
            return $value->value;
        }

        if (is_string($value)) {
            return $value;
        }

        return '';
    }
}
