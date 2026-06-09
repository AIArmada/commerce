<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources\SignalAlertRuleResource\Schemas;

use AIArmada\FilamentSignals\Support\SignalFormOptionLists;
use AIArmada\Signals\Models\TrackedProperty;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class SignalAlertRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Alert Rule')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', $state ? Str::slug($state) : '')),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->alphaDash()
                        ->unique(ignoreRecord: true),

                    Forms\Components\Select::make('tracked_property_id')
                        ->label('Tracked Property')
                        ->relationship(
                            name: 'trackedProperty',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->whereIn(
                                'id',
                                TrackedProperty::query()->forOwner()->select('id')
                            ),
                        )
                        ->searchable()
                        ->preload(),

                    Forms\Components\Select::make('metric_key')
                        ->options(function (): array {
                            $options = [
                                'events' => 'Events',
                                'event_count' => 'Event Count',
                                'page_views' => 'Page Views',
                                'conversions' => 'Conversions',
                                'conversion_rate' => 'Conversion Rate (%)',
                                'property_sum:cart_total_minor' => 'Cart Total Sum',
                                'property_avg:cart_total_minor' => 'Average Cart Total',
                                'property_max:cart_total_minor' => 'Max Cart Total',
                            ];
                            if (config('signals.features.monetary.enabled', true)) {
                                $options['revenue_minor'] = 'Revenue (Minor)';
                            }

                            return $options;
                        })
                        ->required(),

                    Forms\Components\Select::make('operator')
                        ->options([
                            '>' => 'Greater Than',
                            '>=' => 'Greater Than or Equal',
                            '<' => 'Less Than',
                            '<=' => 'Less Than or Equal',
                            '=' => 'Equals',
                            '!=' => 'Not Equals',
                        ])
                        ->default('>=')
                        ->required(),

                    Forms\Components\TextInput::make('threshold')
                        ->numeric()
                        ->default(0)
                        ->required(),

                    Forms\Components\TextInput::make('timeframe_minutes')
                        ->numeric()
                        ->default(60)
                        ->required(),

                    Forms\Components\TextInput::make('cooldown_minutes')
                        ->numeric()
                        ->default(60)
                        ->required(),

                    Forms\Components\Select::make('severity')
                        ->options([
                            'info' => 'Info',
                            'warning' => 'Warning',
                            'critical' => 'Critical',
                        ])
                        ->default('warning')
                        ->required(),

                    Forms\Components\TextInput::make('priority')
                        ->numeric()
                        ->default(0),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true),

                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Event filters')
                ->description('Filter by event name, category, and optional event properties. These filters are generic and work for any package that records Signals events.')
                ->schema([
                    Forms\Components\TagsInput::make('event_filters.event_names')
                        ->label('Event names')
                        ->suggestions(SignalFormOptionLists::eventNames())
                        ->placeholder('cart.abandoned'),

                    Forms\Components\TagsInput::make('event_filters.event_categories')
                        ->label('Event categories')
                        ->suggestions(SignalFormOptionLists::eventCategories())
                        ->placeholder('cart'),

                    Forms\Components\Repeater::make('event_filters.properties')
                        ->label('Property conditions')
                        ->schema([
                            Forms\Components\TextInput::make('key')
                                ->required()
                                ->placeholder('cart_total_minor'),
                            Forms\Components\Select::make('operator')
                                ->options([
                                    'eq' => 'Equals',
                                    'not_eq' => 'Not equals',
                                    '>' => 'Greater Than',
                                    '>=' => 'Greater Than or Equal',
                                    '<' => 'Less Than',
                                    '<=' => 'Less Than or Equal',
                                    'contains' => 'Contains',
                                ])
                                ->default('eq')
                                ->required(),
                            Forms\Components\TextInput::make('value')
                                ->required(),
                        ])
                        ->columns(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Notification channels')
                ->description('Database logs are always created. Additional channels use named destinations from signals config by default.')
                ->schema([
                    Forms\Components\CheckboxList::make('channels')
                        ->options([
                            'database' => 'Database',
                            'email' => 'Email',
                            'webhook' => 'Webhook',
                            'slack' => 'Slack',
                        ])
                        ->columns(4)
                        ->default(['database']),

                    Forms\Components\TagsInput::make('destination_keys')
                        ->label('Destination keys')
                        ->placeholder('ops'),

                    Forms\Components\KeyValue::make('inline_destinations')
                        ->label('Inline destinations')
                        ->helperText('Only used when signals.features.alerts.allow_inline_destinations is enabled.')
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }
}
