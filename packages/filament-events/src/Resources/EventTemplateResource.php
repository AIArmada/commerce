<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Events\Models\EventTemplate;
use AIArmada\FilamentEvents\Resources\EventTemplateResource\Pages\CreateEventTemplate;
use AIArmada\FilamentEvents\Resources\EventTemplateResource\Pages\EditEventTemplate;
use AIArmada\FilamentEvents\Resources\EventTemplateResource\Pages\ListEventTemplates;
use AIArmada\FilamentEvents\Resources\EventTemplateResource\Pages\ViewEventTemplate;
use BackedEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class EventTemplateResource extends Resource
{
    protected static ?string $model = EventTemplate::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-duplicate';

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-events.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-events.resources.navigation_sort.event_template');

        return is_numeric($sort) ? (int) $sort : null;
    }

    /* @phpstan-ignore return.type */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<EventTemplate> $query */
        $query = parent::getEloquentQuery();

        return OwnerUiScope::apply($query, includeGlobal: false);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('template_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'event' => 'success',
                        'occurrence' => 'info',
                        'session' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('visibility')
                    ->badge(),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('template_type')
                    ->options([
                        'event' => 'Event',
                        'occurrence' => 'Occurrence',
                        'session' => 'Session',
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Template Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('template_type')->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('visibility')->badge(),
                        TextEntry::make('description'),
                    ])->columns(2),
                Section::make('Source')
                    ->schema([
                        TextEntry::make('templateable_type')->label('Source Type'),
                        TextEntry::make('templateable_id')->label('Source ID'),
                    ])->columns(2),
                Section::make('Payload')
                    ->schema([
                        CodeEntry::make('payload')
                            ->visible(fn (?array $state): bool => ! empty($state)),
                    ]),
                Section::make('Metadata')
                    ->schema([
                        CodeEntry::make('metadata')
                            ->visible(fn (?array $state): bool => ! empty($state)),
                    ]),
            ]);
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Basic Details')
                    ->schema([
                        Hidden::make('payload')
                            ->default([]),
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('code')->maxLength(100),
                        Textarea::make('description')->rows(3),
                        Select::make('template_type')
                            ->options([
                                'event' => 'Event',
                                'occurrence' => 'Occurrence',
                                'session' => 'Session',
                            ])
                            ->default('event')
                            ->required(),
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required(),
                        Select::make('visibility')
                            ->options([
                                'public' => 'Public',
                                'private' => 'Private',
                                'internal' => 'Internal',
                            ])
                            ->default('private')
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEventTemplates::route('/'),
            'create' => CreateEventTemplate::route('/create'),
            'view' => ViewEventTemplate::route('/{record}'),
            'edit' => EditEventTemplate::route('/{record}/edit'),
        ];
    }
}
