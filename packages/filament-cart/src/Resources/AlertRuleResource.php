<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Resources;

use AIArmada\Cart\Models\AlertRule;
use AIArmada\FilamentCart\Data\AlertEvent;
use AIArmada\FilamentCart\Resources\AlertRuleResource\Pages;
use AIArmada\FilamentCart\Services\AlertDispatcher;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AlertRuleResource extends Resource
{
    protected static ?string $model = AlertRule::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Alert Rules';

    protected static ?string $modelLabel = 'Alert Rule';

    protected static ?int $navigationSort = 45;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-cart.navigation_group', 'E-Commerce');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->rows(2)
                            ->maxLength(1000),

                        Select::make('event_type')
                            ->required()
                            ->options([
                                'abandonment' => 'Cart Abandonment',
                                'high_value' => 'High-Value Cart',
                                'recovery' => 'Recovery Opportunity',
                                'custom' => 'Custom Event',
                            ]),

                        Select::make('severity')
                            ->required()
                            ->default('info')
                            ->options([
                                'info' => 'Info',
                                'warning' => 'Warning',
                                'critical' => 'Critical',
                            ]),

                        TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher priority rules are evaluated first'),
                    ])
                    ->columns(2),

                Section::make('Conditions')
                    ->description('Define when this alert should trigger')
                    ->schema([
                        Repeater::make('conditions.all')
                            ->label('All conditions must match (AND)')
                            ->schema([
                                TextInput::make('field')
                                    ->required()
                                    ->placeholder('e.g., cart_value_cents'),

                                Select::make('operator')
                                    ->required()
                                    ->default('>=')
                                    ->options([
                                        '=' => 'Equals (=)',
                                        '!=' => 'Not Equals (!=)',
                                        '>' => 'Greater Than (>)',
                                        '>=' => 'Greater Than or Equal (>=)',
                                        '<' => 'Less Than (<)',
                                        '<=' => 'Less Than or Equal (<=)',
                                        'in' => 'In Array',
                                        'not_in' => 'Not In Array',
                                        'contains' => 'Contains',
                                        'is_null' => 'Is Null',
                                        'is_not_null' => 'Is Not Null',
                                        'between' => 'Between',
                                    ]),

                                TextInput::make('value')
                                    ->placeholder('Value to compare'),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('Add Condition'),
                    ]),

                Section::make('Notification Channels')
                    ->schema([
                        Toggle::make('notify_database')
                            ->label('In-App Notifications')
                            ->default(true),

                        Toggle::make('notify_email')
                            ->label('Email Notifications')
                            ->live(),

                        TagsInput::make('email_recipients')
                            ->label('Email Recipients')
                            ->placeholder('Add email address')
                            ->visible(fn (Get $get) => $get('notify_email')),

                        Toggle::make('notify_slack')
                            ->label('Slack Notifications')
                            ->live(),

                        TextInput::make('slack_webhook_url')
                            ->label('Slack Webhook URL')
                            ->url()
                            ->visible(fn (Get $get) => $get('notify_slack')),

                        Toggle::make('notify_webhook')
                            ->label('Webhook Notifications')
                            ->live(),

                        TextInput::make('webhook_url')
                            ->label('Webhook URL')
                            ->url()
                            ->visible(fn (Get $get) => $get('notify_webhook')),
                    ])
                    ->columns(2),

                Section::make('Throttling')
                    ->schema([
                        TextInput::make('cooldown_minutes')
                            ->label('Cooldown Period (minutes)')
                            ->numeric()
                            ->default(60)
                            ->helperText('Minimum time between alerts of this type'),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('event_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'abandonment' => 'warning',
                        'high_value' => 'info',
                        'recovery' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'warning' => 'warning',
                        default => 'info',
                    }),

                Tables\Columns\TextColumn::make('channels')
                    ->label('Channels')
                    ->state(function (AlertRule $record): string {
                        $channels = [];
                        if ($record->notify_database) {
                            $channels[] = 'App';
                        }
                        if ($record->notify_email) {
                            $channels[] = 'Email';
                        }
                        if ($record->notify_slack) {
                            $channels[] = 'Slack';
                        }
                        if ($record->notify_webhook) {
                            $channels[] = 'Webhook';
                        }

                        return implode(', ', $channels) ?: 'None';
                    }),

                Tables\Columns\TextColumn::make('cooldown_minutes')
                    ->label('Cooldown')
                    ->suffix(' min'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_triggered_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('logs_count')
                    ->label('Alerts')
                    ->counts('logs'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->options([
                        'abandonment' => 'Cart Abandonment',
                        'high_value' => 'High-Value Cart',
                        'recovery' => 'Recovery Opportunity',
                        'custom' => 'Custom Event',
                    ]),

                Tables\Filters\SelectFilter::make('severity')
                    ->options([
                        'info' => 'Info',
                        'warning' => 'Warning',
                        'critical' => 'Critical',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\Action::make('test')
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Test Alert Rule')
                    ->modalDescription('This will send a test alert to all configured channels.')
                    ->action(function (AlertRule $record): void {
                        // Dispatch test alert
                        $dispatcher = app(AlertDispatcher::class);
                        $event = AlertEvent::custom(
                            eventType: $record->event_type,
                            severity: 'info',
                            title: "[TEST] {$record->name}",
                            message: 'This is a test alert to verify your notification channels are working.',
                            data: ['test' => true, 'rule_id' => $record->id],
                        );
                        $dispatcher->dispatch($record, $event);
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\BulkAction::make('activate')
                        ->icon('heroicon-o-check')
                        ->action(fn ($records) => $records->each->update(['is_active' => true])),
                    Actions\BulkAction::make('deactivate')
                        ->icon('heroicon-o-x-mark')
                        ->action(fn ($records) => $records->each->update(['is_active' => false])),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAlertRules::route('/'),
            'create' => Pages\CreateAlertRule::route('/create'),
            'view' => Pages\ViewAlertRule::route('/{record}'),
            'edit' => Pages\EditAlertRule::route('/{record}/edit'),
        ];
    }
}
