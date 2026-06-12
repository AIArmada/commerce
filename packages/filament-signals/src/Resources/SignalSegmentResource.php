<?php

declare(strict_types=1);

namespace AIArmada\FilamentSignals\Resources;

use AIArmada\FilamentSignals\Resources\SignalSegmentResource\Pages;
use AIArmada\FilamentSignals\Resources\SignalSegmentResource\Schemas\SignalSegmentForm;
use AIArmada\FilamentSignals\Resources\SignalSegmentResource\Tables\SignalSegmentTable;
use AIArmada\Signals\Models\SignalSegment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class SignalSegmentResource extends Resource
{
    protected static ?string $model = SignalSegment::class;

    protected static ?string $modelLabel = 'Audience segment';

    protected static ?string $pluralModelLabel = 'Audience segments';

    protected static ?string $navigationLabel = 'Audience Segments';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-users';

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
        return config('filament-signals.navigation.group', 'Insights');
    }

    public static function getNavigationSort(): ?int
    {
        return (int) config('filament-signals.resources.navigation_sort.segments', 31);
    }

    public static function form(Schema $schema): Schema
    {
        return SignalSegmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SignalSegmentTable::configure($table);
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
