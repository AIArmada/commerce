<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\CompanyStatementResource\Pages;

use AIArmada\CommerceSupport\Filament\Pages\ReadOnlyListRecords;
use AIArmada\FilamentChip\Resources\CompanyStatementResource;
use Override;

final class ListCompanyStatements extends ReadOnlyListRecords
{
    protected static string $resource = CompanyStatementResource::class;

    #[Override]
    public function getTitle(): string
    {
        return 'Company Statements';
    }

    #[Override]
    public function getSubheading(): string
    {
        return 'View and download financial statements from CHIP.';
    }
}
