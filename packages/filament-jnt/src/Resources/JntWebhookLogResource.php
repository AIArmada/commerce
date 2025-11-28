<?php

declare(strict_types=1);

namespace AIArmada\FilamentJnt\Resources;

use AIArmada\FilamentJnt\Resources\JntWebhookLogResource\Pages\ListJntWebhookLogs;
use AIArmada\FilamentJnt\Resources\JntWebhookLogResource\Pages\ViewJntWebhookLog;
use AIArmada\FilamentJnt\Resources\JntWebhookLogResource\Schemas\JntWebhookLogInfolist;
use AIArmada\FilamentJnt\Resources\JntWebhookLogResource\Tables\JntWebhookLogTable;
use AIArmada\Jnt\Models\JntWebhookLog;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Override;

final class JntWebhookLogResource extends BaseJntResource
{
    protected static ?string $model = JntWebhookLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $modelLabel = 'Webhook Log';

    protected static ?string $pluralModelLabel = 'Webhook Logs';

    protected static ?string $recordTitleAttribute = 'tracking_number';

    #[Override]
    public static function table(Table $table): Table
    {
        return JntWebhookLogTable::configure($table);
    }

    #[Override]
    public static function infolist(Schema $schema): Schema
    {
        return JntWebhookLogInfolist::configure($schema);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'tracking_number',
            'order_reference',
            'processing_status',
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJntWebhookLogs::route('/'),
            'view' => ViewJntWebhookLog::route('/{record}'),
        ];
    }

    protected static function navigationSortKey(): string
    {
        return 'webhook_logs';
    }
}
