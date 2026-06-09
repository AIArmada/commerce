<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Resources\UnifiedInvoiceResource\Schemas;

use Filament\Schemas\Schema;

final class InvoiceForm
{
    /**
     * Invoice records are read-only DTO-based views.
     * No inline form is needed — all interaction is via the table.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema;
    }
}
