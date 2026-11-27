<?php

declare(strict_types=1);

namespace AIArmada\FilamentGrowth\Resources;

use AIArmada\CommerceSupport\Support\OwnerTuple\OwnerTupleColumns;
use AIArmada\FilamentGrowth\Authorization\ExperimentAccess;
use AIArmada\FilamentGrowth\Resources\VariantResource\Pages;
use AIArmada\FilamentGrowth\Resources\VariantResource\Schemas\VariantForm;
use AIArmada\FilamentGrowth\Resources\VariantResource\Tables\VariantsTable;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Features\Ingestion\Models\TrackedProperty;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

final class VariantResource extends Resource
{
    protected static ?string $model = Variant::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * @return Builder<Variant>
     */
    public static function getEloquentQuery(): Builder
    {
        $experimentColumns = OwnerTupleColumns::forModelClass(Experiment::class);
        $propertyColumns = OwnerTupleColumns::forModelClass(TrackedProperty::class);

        return ExperimentAccess::scopeVariants(Variant::query())
            ->with([
                'experiment:' . implode(',', array_values(array_unique([
                    'id',
                    'name',
                    'tracked_property_id',
                    $experimentColumns->ownerTypeColumn,
                    $experimentColumns->ownerIdColumn,
                ]))),
                'experiment.trackedProperty:' . implode(',', array_values(array_unique([
                    'id',
                    $propertyColumns->ownerTypeColumn,
                    $propertyColumns->ownerIdColumn,
                ]))),
            ]);
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-growth.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-growth.resources.navigation_sort.variants', 21);
    }

    public static function form(Schema $schema): Schema
    {
        return VariantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VariantsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return parent::canCreate();
    }

    public static function canEdit(Model $record): bool
    {
        if (! $record instanceof Variant) {
            return false;
        }

        if (! parent::canEdit($record)) {
            return false;
        }

        return ExperimentAccess::canMutateVariant($record);
    }

    public static function canDelete(Model $record): bool
    {
        if (! $record instanceof Variant) {
            return false;
        }

        if (! parent::canDelete($record)) {
            return false;
        }

        return ExperimentAccess::canMutateVariant($record);
    }

    public static function canDeleteAny(): bool
    {
        return parent::canDeleteAny()
            && Gate::allows('deleteAny', static::getModel());
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVariants::route('/'),
            'create' => Pages\CreateVariant::route('/create'),
            'edit' => Pages\EditVariant::route('/{record}/edit'),
        ];
    }
}
