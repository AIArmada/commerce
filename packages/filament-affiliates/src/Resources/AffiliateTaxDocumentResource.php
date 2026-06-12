<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Resources;

use AIArmada\Affiliates\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Services\Tax\TaxDocumentService;
use AIArmada\CommerceSupport\Support\FilamentPermission;
use AIArmada\FilamentAffiliates\Resources\AffiliateTaxDocumentResource\Pages\ListAffiliateTaxDocuments;
use AIArmada\FilamentAffiliates\Resources\AffiliateTaxDocumentResource\Pages\ViewAffiliateTaxDocument;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class AffiliateTaxDocumentResource extends Resource
{
    protected static ?string $model = AffiliateTaxDocument::class;

    protected static ?string $tenantOwnershipRelationshipName = 'owner';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Tax Documents';

    protected static ?string $modelLabel = 'Tax Document';

    protected static ?string $pluralModelLabel = 'Tax Documents';

    public static function canViewAny(): bool
    {
        return FilamentPermission::hasAbility('affiliate.viewAny');
    }

    public static function canView(Model $record): bool
    {
        return FilamentPermission::hasAbility('affiliate.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return FilamentPermission::hasAbility('affiliate.update');
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<AffiliateTaxDocument> $query */
        $query = parent::getEloquentQuery();

        if (! (bool) config('affiliates.owner.enabled', false)) {
            /** @var Builder<Model> $unscopedQuery */
            $unscopedQuery = $query;

            return $unscopedQuery;
        }

        /** @var Builder<Model> $modelQuery */
        $modelQuery = $query;

        return $modelQuery;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('affiliate.name')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('document_type')
                    ->badge(),

                TextColumn::make('tax_year')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('total_amount_minor')
                    ->label('Total')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('generated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending_info' => 'Pending Info',
                        'generated' => 'Generated',
                        'sent' => 'Sent',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('mark_sent')
                    ->label('Mark Sent')
                    ->icon('heroicon-o-paper-airplane')
                    ->authorize(fn (): bool => FilamentPermission::hasAbility('affiliate.update'))
                    ->action(fn (AffiliateTaxDocument $record): AffiliateTaxDocument => app(TaxDocumentService::class)->markDocumentAsSent($record)),
                Action::make('regenerate')
                    ->label('Regenerate')
                    ->icon('heroicon-o-arrow-path')
                    ->authorize(fn (): bool => FilamentPermission::hasAbility('affiliate.update'))
                    ->action(fn (AffiliateTaxDocument $record): AffiliateTaxDocument => app(TaxDocumentService::class)->regenerateDocument($record)),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Tax Document')
                ->schema([
                    TextEntry::make('affiliate.name')->label('Affiliate'),
                    TextEntry::make('document_type'),
                    TextEntry::make('tax_year')->numeric(),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('total_amount_minor')->label('Total')->numeric(),
                    TextEntry::make('document_path')->placeholder('—'),
                    TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateTaxDocuments::route('/'),
            'view' => ViewAffiliateTaxDocument::route('/{record}'),
        ];
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-affiliates.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliates.resources.navigation_sort.affiliate_tax_documents', 72);
    }
}
