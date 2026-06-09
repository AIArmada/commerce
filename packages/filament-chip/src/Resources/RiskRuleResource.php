<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources;

use AIArmada\Chip\Models\Purchase;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

final class RiskRuleResource extends BaseChipResource
{
    protected static ?string $model = Purchase::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?string $modelLabel = 'Risk Rule';

    protected static ?string $pluralModelLabel = 'Risk Rules';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getPages(): array
    {
        return [];
    }

    protected static function navigationSortKey(): string
    {
        return 'risk_rules';
    }
}
