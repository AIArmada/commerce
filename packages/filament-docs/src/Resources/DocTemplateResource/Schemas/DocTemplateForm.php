<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocTemplateResource\Schemas;

use AIArmada\Docs\Enums\DocMergeTag;
use AIArmada\Docs\Support\DocRichContentStorage;
use AIArmada\Docs\Support\TemplateBlockRegistry;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\RichEditor;
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
        $docTypes = config('docs.types', []);

        if (! is_array($docTypes)) {
            $docTypes = [];
        }

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
                                    ->helperText('Used for selecting this template from documents'),
                            ]),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                Select::make('doc_type')
                                    ->label('Document Type')
                                    ->options(
                                        collect($docTypes)
                                            ->keys()
                                            ->mapWithKeys(static fn (string $type): array => [$type => Str::headline($type)])
                                            ->all()
                                    )
                                    ->default(array_key_first($docTypes) ?? 'invoice')
                                    ->required(),

                                Toggle::make('is_default')
                                    ->label('Default Template')
                                    ->helperText('Use this template by default for the selected document type'),
                            ]),
                    ]),

                Section::make('Layout Builder')
                    ->schema([
                        Builder::make('layout')
                            ->label('Document Layout')
                            ->required()
                            ->default(TemplateBlockRegistry::defaultLayout())
                            ->blocks(self::blocks())
                            ->blockPickerColumns(2)
                            ->blockPickerWidth('4xl')
                            ->collapsible()
                            ->cloneable()
                            ->reorderableWithButtons()
                            ->columnSpanFull(),
                    ]),

                Section::make('Page & PDF Settings')
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
                            ->default(true),
                    ])
                    ->collapsible(),
            ]);
    }

    /**
     * @return array<Block>
     */
    private static function blocks(): array
    {
        return [
            Block::make('document_header')
                ->label('Document Header')
                ->schema([
                    Toggle::make('visible')->default(true),
                    TextInput::make('label')->label('Heading')->placeholder('Invoice, Quotation, Proposal'),
                ]),

            Block::make('parties')
                ->label('Company / Customer')
                ->schema([
                    Toggle::make('visible')->default(true),
                    Grid::make(2)->schema([
                        TextInput::make('company_label')->default('From'),
                        TextInput::make('customer_label')->default('Bill To'),
                    ]),
                ]),

            Block::make('document_metadata')
                ->label('Document Details')
                ->schema([
                    Toggle::make('visible')->default(true),
                    TextInput::make('label')->default('Document Details'),
                ]),

            Block::make('rich_body')
                ->label('Document Body Slot')
                ->schema([
                    Toggle::make('visible')->default(true),
                ]),

            Block::make('static_rich_text')
                ->label('Static Rich Text')
                ->schema([
                    Toggle::make('visible')->default(true),
                    RichEditor::make('content')
                        ->label('Content')
                        ->json()
                        ->mergeTags(DocMergeTag::labels())
                        ->toolbarButtons([
                            ['bold', 'italic', 'underline', 'strike', 'link'],
                            ['h2', 'h3'],
                            ['blockquote', 'bulletList', 'orderedList'],
                            ['table', 'attachFiles'],
                            ['mergeTags'],
                            ['undo', 'redo'],
                        ])
                        ->fileAttachmentsDisk((string) config('docs.storage.disk', 'local'))
                        ->fileAttachmentsDirectory(fn (): string => DocRichContentStorage::directory())
                        ->fileAttachmentsVisibility((string) config('docs.storage.rich_content_visibility', 'private'))
                        ->columnSpanFull(),
                ]),

            Block::make('line_items')
                ->label('Line Items')
                ->schema([
                    Toggle::make('visible')->default(true),
                    TextInput::make('label')->default('Items'),
                ]),

            Block::make('totals')
                ->label('Totals')
                ->schema([
                    Toggle::make('visible')->default(true),
                    TextInput::make('label')->default('Totals'),
                ]),

            Block::make('notes_terms')
                ->label('Notes / Terms')
                ->schema([
                    Toggle::make('visible')->default(true),
                    Grid::make(2)->schema([
                        TextInput::make('notes_label')->default('Notes'),
                        TextInput::make('terms_label')->default('Terms'),
                    ]),
                ]),

            Block::make('signature_payment')
                ->label('Signature / Payment Instructions')
                ->schema([
                    Toggle::make('visible')->default(true),
                    TextInput::make('label')->default('Signature / Payment Instructions'),
                    Textarea::make('body')->rows(3)->columnSpanFull(),
                ]),

            Block::make('page_break')
                ->label('Page Break')
                ->schema([
                    Toggle::make('visible')->default(true),
                ]),

            Block::make('footer')
                ->label('Footer')
                ->schema([
                    Toggle::make('visible')->default(true),
                    TextInput::make('text')->default('Thank you for your business.'),
                ]),
        ];
    }
}
