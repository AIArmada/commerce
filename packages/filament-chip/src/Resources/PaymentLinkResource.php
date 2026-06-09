<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources;

use AIArmada\Chip\Models\Payment;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

final class PaymentLinkResource extends BaseChipResource
{
    protected static ?string $model = Payment::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?string $modelLabel = 'Payment Link';

    protected static ?string $pluralModelLabel = 'Payment Links';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getPages(): array
    {
        return [];
    }

    protected static function navigationSortKey(): string
    {
        return 'payment_links';
    }
}
