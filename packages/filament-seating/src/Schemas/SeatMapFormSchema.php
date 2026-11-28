<?php

declare(strict_types=1);

namespace AIArmada\FilamentSeating\Schemas;

use AIArmada\CommerceSupport\Support\OwnerUniqueRule;
use AIArmada\Seating\Features\Maps\Models\SeatMap as SeatMapModel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Illuminate\Validation\Rules\Unique;

/**
 * Shared seat-map form sections, used by the resource and relation managers alike.
 */
final class SeatMapFormSchema
{
    /**
     * @return array<int, Component>
     */
    public static function make(): array
    {
        return [
            Section::make('Details')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => OwnerUniqueRule::scopeToOwner($rule, SeatMapModel::class)),
                    TextInput::make('version')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->default(1),
                    Select::make('status')
                        ->options([
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                            'archived' => 'Archived',
                        ])
                        ->required(),
                ]),
            Section::make('Metadata')
                ->schema([
                    Textarea::make('layout_metadata')
                        ->json()
                        ->formatStateUsing(static fn (mixed $state): ?string => $state === null ? null : json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))
                        ->dehydrateStateUsing(static fn (?string $state): mixed => blank($state) ? null : json_decode($state, true, 512, JSON_THROW_ON_ERROR))
                        ->nullable()
                        ->columnSpanFull(),
                ]),
        ];
    }
}
