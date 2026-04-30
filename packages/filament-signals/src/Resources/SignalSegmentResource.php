<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources;

use AIArmada\FilamentSignals\Resources\SignalSegmentResource\Pages;
use AIArmada\FilamentSignals\Support\SignalFormOptionLists;
use AIArmada\Signals\Models\SignalSegment;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

final class SignalSegmentResource extends Resource
{
    protected static ?string $model = SignalSegment::class;

    protected static ?string $modelLabel = 'Audience segment';

    protected static ?string $pluralModelLabel = 'Audience segments';

    protected static ?string $navigationLabel = 'Audience Segments';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | UnitEnum | null $navigationGroup = 'Insights';

    protected static ?int $navigationSort = 31;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<SignalSegment>
     */
    public static function getEloquentQuery(): Builder
    {
        return SignalSegment::query()->forOwner();
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-signals.navigation_group', 'Insights');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-signals.resources.navigation_sort.segments', 31);
    }

    public static function form(Schema $schema): Schema
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Segment name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Internal key')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('match_type')
                    ->label('Rule match')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'all' => 'Every rule (AND)',
                        'any' => 'Any rule (OR)',
                        default => str($state)->headline()->toString(),
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('conditions')
                    ->label('Rules')
                    ->formatStateUsing(fn (mixed $state): string => (string) count(is_array($state) ? $state : [])),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No audience segments yet')
            ->emptyStateDescription('Create your first reusable audience group to filter reports and compare visitor behavior.')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\SelectFilter::make('match_type')
                    ->label('Rule match')
                    ->options([
                        'all' => 'Every rule (AND)',
                        'any' => 'Any rule (OR)',
                    ]),
            ])
            ->actions([
                EditAction::make()
                    ->label('Edit'),
                DeleteAction::make()
                    ->label('Delete')
                    ->modalHeading('Delete audience segment?')
                    ->modalDescription('This will remove the saved audience segment from reports and filters.')
                    ->successNotificationTitle('Audience segment deleted'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Delete selected')
                        ->modalHeading('Delete audience segments?')
                        ->modalDescription('This will remove the selected saved audience segments from reports and filters.')
                        ->successNotificationTitle('Audience segments deleted'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSignalSegments::route('/'),
            'create' => Pages\CreateSignalSegment::route('/create'),
            'edit' => Pages\EditSignalSegment::route('/{record}/edit'),
        ];
    }
}
