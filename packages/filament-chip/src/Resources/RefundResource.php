<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources;

use AIArmada\Chip\Models\Payment;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

final class RefundResource extends BaseChipResource
{
    protected static ?string $model = Payment::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static ?string $modelLabel = 'Refund';

    protected static ?string $pluralModelLabel = 'Refunds';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getPages(): array
    {
        return [];
    }

    protected static function navigationSortKey(): string
    {
        return 'refunds';
    }
}
