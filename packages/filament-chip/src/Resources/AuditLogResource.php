<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources;

use AIArmada\Chip\Models\Webhook;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

final class AuditLogResource extends BaseChipResource
{
    protected static ?string $model = Webhook::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static ?string $modelLabel = 'Audit Log';

    protected static ?string $pluralModelLabel = 'Audit Logs';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getPages(): array
    {
        return [];
    }

    protected static function navigationSortKey(): string
    {
        return 'audit_logs';
    }
}
