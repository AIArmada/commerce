<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources\DocResource\Schemas;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Docs\Enums\DocMergeTag;
use AIArmada\Docs\Enums\DocTemplateBlockType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocTemplate;
use AIArmada\Docs\States\DocStatus;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\Support\DocRichContentStorage;
use AIArmada\Docs\Support\TemplateBlockRegistry;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class DocForm
{
    public static function configure(Schema $schema): Schema
    {
        $docTypes = config('docs.types', []);

        if (! is_array($docTypes)) {
            $docTypes = [];
        }

        $docTypeOptions = collect($docTypes)
            ->keys()
            ->mapWithKeys(static fn (string $type): array => [$type => Str::headline($type)])
            ->all();

        $defaultDocType = array_key_first($docTypeOptions) ?? 'invoice';

        return $schema
            ->schema([
                Section::make('Document Information')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('doc_number')
                                    ->label('Document Number')
                                    ->helperText('Leave empty to auto-generate')
                                    ->unique(ignoreRecord: true),

                                Select::make('doc_type')
                                    ->label('Document Type')
                                    ->options($docTypeOptions)
                                    ->default($defaultDocType)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('doc_template_id', null);
                                    }),

                                Select::make('doc_template_id')
                                    ->label('Template')
                                    ->options(function (Get $get): array {
                                        $query = OwnerUiScope::apply(DocTemplate::query(), includeGlobal: false);

                                        $docType = $get('doc_type');
                                        if (is_string($docType) && $docType !== '') {
                                            $query->where('doc_type', $docType);
                                        }

                                        /** @var array<string, string> $options */
                                        $options = $query->orderBy('name')->pluck('name', 'id')->all();

                                        return $options;
                                    })
                                    ->live()
                                    ->searchable()
                                    ->helperText('Optional: Select a template'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                Select::make('status')
                                    ->label('Status')
                                    ->options(DocStatus::options())
                                    ->default(DocStatus::normalize(Draft::class))
                                    ->required(),

                                DatePicker::make('issue_date')
                                    ->label('Issue Date')
                                    ->default(CarbonImmutable::now())
                                    ->required(),

                                DatePicker::make('due_date')
                                    ->label('Due Date')
                                    ->helperText('Auto-calculated if empty'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('currency')
                                    ->label('Currency')
                                    ->default((string) config('docs.defaults.currency', 'MYR'))
                                    ->maxLength(3)
                                    ->required()
                                    ->live(),

                                TextInput::make('tax_rate')
                                    ->label('Tax Rate')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('%')
                                    ->helperText('e.g., 6 for 6%'),
                            ]),
                    ]),

                Section::make('Customer Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('customer_data.name')
                                    ->label('Customer Name')
                                    ->required(),

                                TextInput::make('customer_data.email')
                                    ->label('Email')
                                    ->email(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('customer_data.phone')
                                    ->label('Phone'),

                                TextInput::make('customer_data.address')
                                    ->label('Address'),
                            ]),

                        Grid::make(4)
                            ->schema([
                                TextInput::make('customer_data.city')
                                    ->label('City'),

                                TextInput::make('customer_data.state')
                                    ->label('State'),

                                TextInput::make('customer_data.postcode')
                                    ->label('Postcode'),

                                TextInput::make('customer_data.country')
                                    ->label('Country'),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Document Body')
                    ->schema([
                        RichEditor::make('body')
                            ->label('Body')
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
                            ->preventFileAttachmentPathTampering(fn (?Doc $record): bool => $record instanceof Doc && $record->exists)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible(fn (Get $get, ?Doc $record): bool => self::selectedTemplateUses($get, $record, DocTemplateBlockType::RichBody)),

                Section::make('Line Items')
                    ->schema([
                        Repeater::make('items')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Item Name')
                                            ->required()
                                            ->columnSpan(2),

                                        TextInput::make('quantity')
                                            ->label('Quantity')
                                            ->numeric()
                                            ->default(1)
                                            ->minValue(1)
                                            ->required(),

                                        TextInput::make('price')
                                            ->label('Unit Price')
                                            ->numeric()
                                            ->prefix(fn (Get $get): string => $get('../../currency') ?? config('docs.defaults.currency', 'MYR'))
                                            ->required(),
                                    ]),

                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->cloneable()
                            ->reorderable()
                            ->itemLabel(fn (array $state, Get $get): string => ($state['name'] ?? 'New Item') .
                                (isset($state['quantity'], $state['price']) ? ' - ' . $state['quantity'] . ' × ' . ($get('currency') ?? config('docs.defaults.currency', 'MYR')) . ' ' . number_format((float) $state['price'], 2) : ''))
                            ->columnSpanFull()
                            ->defaultItems(0),
                    ])
                    ->visible(fn (Get $get, ?Doc $record): bool => self::selectedTemplateUses($get, $record, DocTemplateBlockType::LineItems)),

                Section::make('Amounts')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->prefix(fn (Get $get): string => $get('currency') ?? config('docs.defaults.currency', 'MYR'))
                                    ->helperText('Auto-calculated if empty'),

                                TextInput::make('tax_amount')
                                    ->label('Tax Amount')
                                    ->numeric()
                                    ->prefix(fn (Get $get): string => $get('currency') ?? config('docs.defaults.currency', 'MYR'))
                                    ->helperText('Auto-calculated if empty'),

                                TextInput::make('discount_amount')
                                    ->label('Discount')
                                    ->numeric()
                                    ->prefix(fn (Get $get): string => $get('currency') ?? config('docs.defaults.currency', 'MYR'))
                                    ->default(0),

                                TextInput::make('total')
                                    ->label('Total')
                                    ->numeric()
                                    ->prefix(fn (Get $get): string => $get('currency') ?? config('docs.defaults.currency', 'MYR'))
                                    ->helperText('Auto-calculated if empty'),
                            ]),
                    ])
                    ->collapsible()
                    ->visible(fn (Get $get, ?Doc $record): bool => self::selectedTemplateUses($get, $record, DocTemplateBlockType::Totals)),

                Section::make('Notes & Terms')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('terms')
                            ->label('Terms & Conditions')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible(fn (Get $get, ?Doc $record): bool => self::selectedTemplateUses($get, $record, DocTemplateBlockType::NotesTerms)),

                Section::make('Metadata')
                    ->schema([
                        KeyValue::make('metadata')
                            ->label('Additional Data')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->reorderable()
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    private static function selectedTemplateUses(Get $get, ?Doc $record, DocTemplateBlockType $blockType): bool
    {
        $template = self::resolveSelectedTemplate($get, $record);

        if (! $template instanceof DocTemplate) {
            return TemplateBlockRegistry::hasBlock(TemplateBlockRegistry::defaultLayout(), $blockType);
        }

        return TemplateBlockRegistry::hasBlock($template->layout, $blockType);
    }

    private static function resolveSelectedTemplate(Get $get, ?Doc $record): ?DocTemplate
    {
        $templateId = $get('doc_template_id');
        $docType = $get('doc_type') ?: $record?->doc_type;

        if (is_string($templateId) && $templateId !== '') {
            $query = OwnerUiScope::apply(DocTemplate::query(), includeGlobal: false);

            if (is_string($docType) && $docType !== '') {
                $query->where('doc_type', $docType);
            }

            return $query->find($templateId);
        }

        if ($record?->template instanceof DocTemplate) {
            return $record->template;
        }

        if (! is_string($docType) || $docType === '') {
            return null;
        }

        $query = OwnerUiScope::apply(DocTemplate::query(), includeGlobal: false)
            ->where('doc_type', $docType)
            ->where('is_default', true);

        return $query->first();
    }
}
