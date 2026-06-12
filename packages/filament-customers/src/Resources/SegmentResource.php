<?php

declare(strict_types=1);

namespace AIArmada\FilamentCustomers\Resources;

use AIArmada\CommerceSupport\Support\Filament\OwnerUiScope;
use AIArmada\Customers\Models\Segment;
use AIArmada\FilamentCustomers\Resources\SegmentResource\Pages;
use AIArmada\FilamentCustomers\Resources\SegmentResource\Schemas\SegmentForm;
use AIArmada\FilamentCustomers\Resources\SegmentResource\Tables\SegmentsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SegmentResource extends Resource
{
    protected static ?string $model = Segment::class;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-customers.navigation.group');
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        $count = OwnerUiScope::apply(static::getModel()::query(), includeGlobal: false)
            ->whereNull('deactivated_at')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * @return Builder<Segment>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Segment> $query */
        $query = parent::getEloquentQuery();

        return OwnerUiScope::apply($query, includeGlobal: false);
    }

    public static function form(Schema $schema): Schema
    {
        return SegmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SegmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSegments::route('/'),
            'create' => Pages\CreateSegment::route('/create'),
            'view' => Pages\ViewSegment::route('/{record}'),
            'edit' => Pages\EditSegment::route('/{record}/edit'),
        ];
    }
}
