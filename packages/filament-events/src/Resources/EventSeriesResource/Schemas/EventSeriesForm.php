<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\EventSeriesResource\Schemas;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Enums\SeriesStatus;
use AIArmada\Events\Models\EventSeries;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class EventSeriesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Series')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state): mixed => $set('slug', Str::slug($state ?? ''))),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->scopedUnique(EventSeries::class, 'slug', modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false)),

                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),

                        Select::make('status')
                            ->options(collect(SeriesStatus::cases())->mapWithKeys(fn (SeriesStatus $s): array => [$s->value => $s->label()]))
                            ->default(SeriesStatus::Active->value)
                            ->required(),

                        KeyValue::make('metadata')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
