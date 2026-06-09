<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources;

use AIArmada\Chip\Models\Payment;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

final class FraudReviewResource extends BaseChipResource
{
    protected static ?string $model = Payment::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $modelLabel = 'Fraud Review';

    protected static ?string $pluralModelLabel = 'Fraud Reviews';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getPages(): array
    {
        return [];
    }

    protected static function navigationSortKey(): string
    {
        return 'fraud_reviews';
    }
}
