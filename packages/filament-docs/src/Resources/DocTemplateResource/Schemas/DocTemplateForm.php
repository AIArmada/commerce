<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocTemplateResource\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class DocTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Template Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Template Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state))),

                                TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->helperText('Used for referencing the template in code'),
                            ]),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                Select::make('doc_type')
                                    ->label('Document Type')
                                    ->options([
                                        'invoice' => 'Invoice',
                                        'receipt' => 'Receipt',
                                    ])
                                    ->default('invoice')
                                    ->required(),

                                TextInput::make('view_name')
                                    ->label('View Name')
                                    ->required()
                                    ->default('doc-default')
                                    ->helperText('Blade view name (e.g., doc-default, modern)'),
                            ]),

                        Toggle::make('is_default')
                            ->label('Default Template')
                            ->helperText('Set as the default template for this document type'),
                    ]),

                Section::make('PDF Settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('settings.pdf.format')
                                    ->label('Paper Format')
                                    ->options([
                                        'a4' => 'A4',
                                        'letter' => 'Letter',
                                        'legal' => 'Legal',
                                        'a3' => 'A3',
                                        'a5' => 'A5',
                                    ])
                                    ->default('a4'),

                                Select::make('settings.pdf.orientation')
                                    ->label('Orientation')
                                    ->options([
                                        'portrait' => 'Portrait',
                                        'landscape' => 'Landscape',
                                    ])
                                    ->default('portrait'),
                            ]),

                        Grid::make(4)
                            ->schema([
                                TextInput::make('settings.pdf.margin.top')
                                    ->label('Top Margin (mm)')
                                    ->numeric()
                                    ->default(10),

                                TextInput::make('settings.pdf.margin.right')
                                    ->label('Right Margin (mm)')
                                    ->numeric()
                                    ->default(10),

                                TextInput::make('settings.pdf.margin.bottom')
                                    ->label('Bottom Margin (mm)')
                                    ->numeric()
                                    ->default(10),

                                TextInput::make('settings.pdf.margin.left')
                                    ->label('Left Margin (mm)')
                                    ->numeric()
                                    ->default(10),
                            ]),

                        Toggle::make('settings.pdf.print_background')
                            ->label('Print Background')
                            ->helperText('Enable background colors and gradients')
                            ->default(true),
                    ])
                    ->collapsible(),

                Section::make('Custom Settings')
                    ->schema([
                        KeyValue::make('settings.custom')
                            ->label('Custom Settings')
                            ->keyLabel('Setting Key')
                            ->valueLabel('Value')
                            ->reorderable()
                            ->columnSpanFull()
                            ->helperText('Add custom settings for this template'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
