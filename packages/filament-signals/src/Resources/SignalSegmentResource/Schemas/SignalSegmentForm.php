<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalSegmentResource\Schemas;

use AIArmada\FilamentSignals\Support\SignalFormOptionLists;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class SignalSegmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Segment')
                ->description('Create a reusable audience group for reports, saved filters, and comparisons.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Segment name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Example: Visitors from Telegram')
                        ->helperText('Shown to teammates in reports and filter pickers.')
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', $state ? Str::slug($state) : '')),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug / internal key')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('visitors-from-telegram')
                        ->helperText('Auto-filled from the name. You can edit it if you want a shorter internal key.')
                        ->alphaDash()
                        ->unique(ignoreRecord: true),

                    Forms\Components\Select::make('match_type')
                        ->options([
                            'all' => 'Match every rule (AND)',
                            'any' => 'Match any rule (OR)',
                        ])
                        ->label('How rules should match')
                        ->helperText('Use every rule when all conditions must be true. Use any rule when one matching condition is enough.')
                        ->default('all')
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),

                    Forms\Components\Textarea::make('description')
                        ->label('Notes')
                        ->rows(3)
                        ->placeholder('Optional context for teammates about when to use this segment.')
                        ->helperText('Optional. Add a short explanation if the segment name alone is not enough.')
                        ->columnSpanFull(),

                    Forms\Components\Repeater::make('conditions')
                        ->label('Rules')
                        ->helperText('Add one or more rules that describe who belongs in this segment.')
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
                                    'in' => 'In',
                                ])
                                ->helperText('Use numeric comparisons for amounts like revenue_minor. Use In when you want to check a comma-separated list.')
                                ->required(),
                            Forms\Components\TextInput::make('value')
                                ->label('Value to match')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Example: /majlis, telegram, or affiliate.conversion.recorded'),
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
