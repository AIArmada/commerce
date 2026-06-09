<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalInteractionRuleResource\Schemas;

use AIArmada\Signals\Models\TrackedProperty;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class SignalInteractionRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Interaction rule')
                ->description('Select which page interaction to track, then map it to a Signals event.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Rule name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Hero CTA click')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', $state ? Str::slug($state) : '')),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug / internal key')
                        ->required()
                        ->maxLength(255)
                        ->alphaDash()
                        ->unique(ignoreRecord: true),

                    Forms\Components\Select::make('tracked_property_id')
                        ->label('Website or app')
                        ->relationship(
                            name: 'trackedProperty',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->whereIn(
                                'id',
                                TrackedProperty::query()->forOwner()->select('id')
                            ),
                        )
                        ->helperText('Leave empty only if you want this rule to apply across all tracked properties in this owner scope.')
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('trigger_type')
                        ->label('Interaction type')
                        ->options([
                            'click' => 'Click',
                            'accordion' => 'Accordion toggle',
                            'media' => 'Audio / Video',
                            'youtube' => 'YouTube embed click',
                        ])
                        ->required()
                        ->default('click')
                        ->helperText('Use media for audio/video play/pause/ended hooks. Use youtube for embed wrapper click tracking.'),

                    Forms\Components\TextInput::make('event_name')
                        ->label('Event name')
                        ->required()
                        ->maxLength(255)
                        ->default('ui.click')
                        ->placeholder('ui.click'),

                    Forms\Components\TextInput::make('event_category')
                        ->label('Event category')
                        ->maxLength(100)
                        ->default('engagement')
                        ->placeholder('engagement'),

                    Forms\Components\TextInput::make('selector')
                        ->label('CSS selector')
                        ->maxLength(255)
                        ->placeholder('.hero-cta, [data-track-cta]')
                        ->helperText('For media interactions, leave blank to auto-match native audio/video tags.'),

                    Forms\Components\TextInput::make('page_pattern')
                        ->label('Path pattern (optional)')
                        ->maxLength(255)
                        ->placeholder('/pricing*')
                        ->helperText('Wildcard * is supported. Empty means all paths.'),

                    Forms\Components\KeyValue::make('settings')
                        ->label('Advanced settings')
                        ->helperText('Optional. Example: once_per_session => true')
                        ->reorderable(false)
                        ->addActionLabel('Add setting')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('Sort order')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),

                    Forms\Components\Textarea::make('description')
                        ->label('Notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }
}
