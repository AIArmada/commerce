<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources;

use AIArmada\Chip\Models\Purchase;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

final class ComplianceReportResource extends BaseChipResource
{
    protected static ?string $model = Purchase::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $modelLabel = 'Compliance Report';

    protected static ?string $pluralModelLabel = 'Compliance Reports';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getPages(): array
    {
        return [];
    }

    protected static function navigationSortKey(): string
    {
        return 'compliance_reports';
    }
}
