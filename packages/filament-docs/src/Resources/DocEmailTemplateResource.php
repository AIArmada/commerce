<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\DocEmailTemplate;
use AIArmada\FilamentDocs\Support\DocPermissions;
use AIArmada\FilamentDocs\Support\DocsOwnerScope;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

final class DocEmailTemplateResource extends Resource
{
    protected static ?string $model = DocEmailTemplate::class;

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Email Templates';

    protected static ?string $modelLabel = 'Email Template';

    protected static ?string $pluralModelLabel = 'Email Templates';

    public static function canViewAny(): bool
    {
        return DocPermissions::allows(DocPermissions::DOCUMENT_EMAIL_TEMPLATE, 'viewAny');
    }

    public static function canView(Model $record): bool
    {
        return DocPermissions::allows(DocPermissions::DOCUMENT_EMAIL_TEMPLATE, 'view');
    }

    public static function canCreate(): bool
    {
        return DocPermissions::allows(DocPermissions::DOCUMENT_EMAIL_TEMPLATE, 'create');
    }

    public static function canEdit(Model $record): bool
    {
        return DocPermissions::allows(DocPermissions::DOCUMENT_EMAIL_TEMPLATE, 'update');
    }

    public static function canDelete(Model $record): bool
    {
        return DocPermissions::allows(DocPermissions::DOCUMENT_EMAIL_TEMPLATE, 'delete');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Template Settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => DocsOwnerScope::scopeUniqueRuleToOwner($rule)),

                                Select::make('doc_type')
                                    ->label('Document Type')
                                    ->options(collect(DocType::cases())
                                        ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                                        ->all())
                                    ->required(),

                                Select::make('trigger')
                                    ->options([
                                        'send' => 'When document is sent',
                                        'due_soon' => 'Upcoming due date reminder',
                                        'reminder' => 'Payment reminder',
                                        'overdue' => 'When overdue',
                                        'paid' => 'When paid',
                                        'created' => 'When created',
                                    ])
                                    ->required(),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                            ]),
                    ]),

                Section::make('Email Content')
                    ->schema([
                        TextInput::make('subject')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Variables: {{doc_number}}, {{customer_name}}, {{total}}, {{due_date}}, {{company_name}}')
                            ->columnSpanFull(),

                        RichEditor::make('body')
                            ->required()
                            ->helperText('Variables: {{doc_number}}, {{customer_name}}, {{total}}, {{currency}}, {{due_date}}, {{issue_date}}, {{company_name}}')
                            ->columnSpanFull(),
                    ]),

                Section::make('Available Variables')
                    ->schema([
                        Text::make('variables')
                            ->content('
                                • {{doc_number}} - Document number
                                • {{doc_type}} - Document type
                                • {{customer_name}} - Customer name
                                • {{total}} - Total amount
                                • {{currency}} - Currency code
                                • {{due_date}} - Due date
                                • {{issue_date}} - Issue date
                                • {{company_name}} - Your company name
                            '),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('doc_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst(str_replace('_', ' ', $state))),

                TextColumn::make('trigger')
                    ->badge()
                    ->color('info'),

                TextColumn::make('subject')
                    ->limit(40)
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('doc_type')
                    ->options(collect(DocType::cases())
                        ->mapWithKeys(fn ($type) => [$type->value => $type->label()])
                        ->all()),

                SelectFilter::make('trigger')
                    ->options([
                        'send' => 'When document is sent',
                        'due_soon' => 'Upcoming due date reminder',
                        'reminder' => 'Payment reminder',
                        'overdue' => 'When overdue',
                        'paid' => 'When paid',
                        'created' => 'When created',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(fn (DocEmailTemplate $record): DocEmailTemplate => self::duplicateTemplate($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('delete_selected')
                        ->label('Delete Selected')
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            /** @var Collection<int|string, DocEmailTemplate> $records */
                            $records->each(function (DocEmailTemplate $record): void {
                                $record->delete();
                            });
                        }),
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
            'index' => DocEmailTemplateResource\Pages\ListDocEmailTemplates::route('/'),
            'create' => DocEmailTemplateResource\Pages\CreateDocEmailTemplate::route('/create'),
            'edit' => DocEmailTemplateResource\Pages\EditDocEmailTemplate::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-docs.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-docs.resources.navigation_sort.email_templates', 91);
    }

    /**
     * @return EloquentBuilder<DocEmailTemplate>
     */
    public static function getEloquentQuery(): EloquentBuilder
    {
        /** @var EloquentBuilder<DocEmailTemplate> $query */
        $query = parent::getEloquentQuery();

        return OwnerUiScope::apply($query, includeGlobal: false);
    }

    public static function duplicateTemplate(DocEmailTemplate $record): DocEmailTemplate
    {
        DocsOwnerScope::assertCanAccessRecord($record, 'Email template not found.');

        $copy = $record->replicate();
        $copy->name = $record->name . ' (Copy)';
        $copy->slug = self::uniqueDuplicateSlug($record->slug);
        $copy->save();

        Notification::make()
            ->title('Email template duplicated')
            ->success()
            ->send();

        return $copy;
    }

    private static function uniqueDuplicateSlug(string $slug): string
    {
        $base = $slug . '-copy-' . CarbonImmutable::now()->timestamp;
        $candidate = $base;

        // The database unique is effectively global (see docs package follow-up),
        // so probe every scope before saving.
        for ($i = 2; DocEmailTemplate::query()->withoutOwnerScope()->where('slug', $candidate)->exists(); $i++) {
            $candidate = $base . '-' . $i;
        }

        return $candidate;
    }
}
