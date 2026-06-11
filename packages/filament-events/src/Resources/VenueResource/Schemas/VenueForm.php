<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources\VenueResource\Schemas;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Enums\VenueStatus;
use AIArmada\Events\Models\Venue;
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

final class VenueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(3)
                    ->schema([
                        Section::make('Venue')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, ?string $state): mixed => $set('slug', Str::slug($state ?? ''))),

                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->scopedUnique(Venue::class, 'slug', modifyQueryUsing: fn (Builder $query): Builder => OwnerUiScope::apply($query, includeGlobal: false)),

                                TextInput::make('contact_name')
                                    ->maxLength(255),

                                TextInput::make('contact_email')
                                    ->email()
                                    ->maxLength(255),

                                TextInput::make('contact_phone')
                                    ->tel()
                                    ->maxLength(255),

                                Textarea::make('notes')
                                    ->rows(4)
                                    ->columnSpanFull(),

                                Select::make('status')
                                    ->options(VenueStatus::options())
                                    ->required()
                                    ->default(VenueStatus::Active->value),
                            ])
                            ->columns(2)
                            ->columnSpan(['lg' => 2]),

                        Section::make('Location')
                            ->schema([
                                Select::make('location_type')
                                    ->options([
                                        'physical' => 'Physical',
                                        'online' => 'Online',
                                        'hybrid' => 'Hybrid',
                                    ])
                                    ->required()
                                    ->default('physical'),

                                TextInput::make('line1')
                                    ->maxLength(255),

                                TextInput::make('line2')
                                    ->maxLength(255),

                                TextInput::make('city')
                                    ->maxLength(255),

                                TextInput::make('state')
                                    ->maxLength(255),

                                TextInput::make('postcode')
                                    ->maxLength(255),

                                TextInput::make('country')
                                    ->required()
                                    ->maxLength(2)
                                    ->default('MY'),

                                TextInput::make('latitude')
                                    ->numeric(),

                                TextInput::make('longitude')
                                    ->numeric(),

                                TextInput::make('map_url')
                                    ->url()
                                    ->columnSpanFull(),

                                TextInput::make('external_id')
                                    ->maxLength(255),

                                TextInput::make('timezone')
                                    ->maxLength(64)
                                    ->default('Asia/Kuala_Lumpur'),

                                KeyValue::make('metadata'),
                            ])
                            ->columnSpan(['lg' => 1]),
                    ]),
            ]);
    }
}
