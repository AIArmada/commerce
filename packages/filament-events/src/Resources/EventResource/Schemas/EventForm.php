<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventResource\Schemas;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\EventVisibility;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class EventForm
{
    public static function configure(Schema $schema): Schema
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
                                    ->options(self::statusOptions())
                                    ->required()
                                    ->default(EventStatus::Draft->value),

                                Select::make('moderation_status')
                                    ->label('Moderation')
                                    ->options(self::moderationStatusOptions())
                                    ->required()
                                    ->default(EventModerationStatus::Approved->value),

                                Select::make('visibility')
                                    ->options(self::visibilityOptions())
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

                                self::jsonTextarea('media_references', 'Media references'),

                                self::jsonTextarea('taxonomy', 'Taxonomy'),

                                KeyValue::make('metadata'),
                            ])
                            ->columnSpan(['lg' => 1]),
                    ]),
            ]);
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
    private static function moderationStatusOptions(): array
    {
        return collect(EventModerationStatus::cases())
            ->mapWithKeys(fn (EventModerationStatus $status): array => [$status->value => $status->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function visibilityOptions(): array
    {
        return collect(EventVisibility::cases())
            ->mapWithKeys(fn (EventVisibility $visibility): array => [$visibility->value => $visibility->label()])
            ->all();
    }

    private static function jsonTextarea(string $name, string $label): Textarea
    {
        return Textarea::make($name)
            ->label($label)
            ->rows(5)
            ->formatStateUsing(fn (mixed $state): ?string => self::formatNullableJsonState($state))
            ->dehydrateStateUsing(fn (?string $state): ?array => self::decodeJsonState($state))
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

        return self::formatJsonState($state);
    }

    public static function formatJsonState(mixed $state): string
    {
        if (! is_array($state)) {
            return '';
        }

        return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
