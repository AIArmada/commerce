<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalGoalResource\Schemas;

use AIArmada\FilamentSignals\Support\SignalFormOptionLists;
use AIArmada\Signals\Models\TrackedProperty;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class SignalGoalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Goal')
                ->description('Define the event that counts as success, then add optional rules to narrow it down.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Goal name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Example: Shared link produced a signup')
                        ->helperText('Shown to teammates in dashboards, funnels, and reports.')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', $state ? Str::slug($state) : '')),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug / internal key')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('shared-link-produced-a-signup')
                        ->helperText('Auto-filled from the name. Edit it if you need a shorter internal key.')
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
                        ->helperText('Choose which tracked property this goal should watch.')
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('goal_type')
                        ->label('Goal type')
                        ->options(function (): array {
                            $options = [
                                'conversion' => 'Conversion',
                                'engagement' => 'Engagement',
                            ];
                            if (config('signals.features.monetary.enabled', true)) {
                                $options['revenue'] = 'Revenue';
                            }

                            return $options;
                        })
                        ->helperText(config('signals.features.monetary.enabled', true)
                            ? 'Use Conversion for success actions, Engagement for softer actions, and Revenue for money-based goals.'
                            : 'Use Conversion for success actions and Engagement for softer actions.')
                        ->default('conversion')
                        ->required(),

                    Forms\Components\TextInput::make('event_name')
                        ->label('Event name to count')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Example: affiliate.conversion.recorded')
                        ->datalist(SignalFormOptionLists::eventNames())
                        ->helperText('Choose a common event name or type the exact event you want this goal to count.'),

                    Forms\Components\TextInput::make('event_category')
                        ->label('Event category (optional)')
                        ->maxLength(100)
                        ->placeholder('Example: acquisition')
                        ->datalist(SignalFormOptionLists::eventCategories())
                        ->helperText('Optional. Use this when the event name alone is not specific enough.'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),

                    Forms\Components\Textarea::make('description')
                        ->label('Notes')
                        ->rows(3)
                        ->placeholder('Optional context for teammates about what this goal represents.')
                        ->helperText('Optional. Add plain-language context if the goal name is not self-explanatory.')
                        ->columnSpanFull(),

                    Forms\Components\Repeater::make('conditions')
                        ->label('Extra rules')
                        ->helperText('Add optional rules if this goal should only count a subset of matching events.')
                        ->schema([
                            Forms\Components\TextInput::make('field')
                                ->label('Field to check')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Example: path or properties.checkout.gateway')
                                ->datalist(SignalFormOptionLists::conditionFields())
                                ->helperText('Choose a common field or type your own custom properties.* key.'),
                            Forms\Components\Select::make('operator')
                                ->label('Compare using')
                                ->options([
                                    'equals' => 'Equals',
                                    'not_equals' => 'Not Equals',
                                    'contains' => 'Contains',
                                    'starts_with' => 'Starts With',
                                    'ends_with' => 'Ends With',
                                    'greater_than' => 'Greater Than',
                                    'greater_than_or_equal' => 'Greater Than or Equal',
                                    'less_than' => 'Less Than',
                                    'less_than_or_equal' => 'Less Than or Equal',
                                    'in' => 'In List',
                                ])
                                ->helperText('Use numeric comparisons for amounts like revenue_minor. Use In List when you want to check a comma-separated list.')
                                ->required(),
                            Forms\Components\TextInput::make('value')
                                ->label('Value to match')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Example: signup, telegram, or /majlis'),
                        ])
                        ->columns(3)
                        ->columnSpanFull()
                        ->defaultItems(1)
                        ->addActionLabel('Add rule'),
                ])
                ->columns(2),
        ]);
    }
}
