<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferCategoryResource\Schemas;

use AIArmada\CommerceSupport\Support\OwnerScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class AffiliateOfferCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category Details')
                    ->schema([
                        // Admin form: cross-tenant — relationship select searches server-side
                        // (no unbounded preload) with explicit global scope bypass. The
                        // current record is excluded from its own parent options; deeper
                        // cycle protection is enforced server-side on save.
                        Select::make('parent_id')
                            ->label('Parent Category')
                            ->relationship('parent', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query
                                ->withoutGlobalScope(OwnerScope::class)
                                ->orderBy('sort_order')
                                ->orderBy('name'), ignoreRecord: true)
                            ->searchable()
                            ->nullable(),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        TextInput::make('icon')
                            ->maxLength(100)
                            ->placeholder('heroicon-o-tag'),

                        Textarea::make('description')
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),

                        Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
