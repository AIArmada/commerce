<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources;

use AIArmada\FilamentJnt\Resources\JntTrackingEventResource\Pages\ListJntTrackingEvents;
use AIArmada\FilamentJnt\Resources\JntTrackingEventResource\Pages\ViewJntTrackingEvent;
use AIArmada\FilamentJnt\Resources\JntTrackingEventResource\Schemas\JntTrackingEventInfolist;
use AIArmada\FilamentJnt\Resources\JntTrackingEventResource\Tables\JntTrackingEventTable;
use AIArmada\Jnt\Models\JntTrackingEvent;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;

final class JntTrackingEventResource extends BaseJntResource
{
    protected static ?string $model = JntTrackingEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $modelLabel = 'Tracking Event';

    protected static ?string $pluralModelLabel = 'Tracking Events';

    protected static ?string $recordTitleAttribute = 'tracking_number';

    #[Override]
    public static function table(Table $table): Table
    {
        return JntTrackingEventTable::configure($table);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return JntTrackingEventInfolist::configure($schema);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'tracking_number',
            'order_reference',
            'scan_type_name',
            'description',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJntTrackingEvents::route('/'),
            'view' => ViewJntTrackingEvent::route('/{record}'),
        ];
    }

    protected static function navigationSortKey(): string
    {
        return 'tracking_events';
    }
}
