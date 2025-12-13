<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip\Resources\SendInstructionResource\Pages;

use AIArmada\FilamentChip\Resources\SendInstructionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Override;

final class ListSendInstructions extends ListRecords
{
    protected static string $resource = SendInstructionResource::class;

    #[Override]
    public function getTitle(): string
    {
        return 'CHIP Send Payouts';
    }

    #[Override]
    public function getSubheading(): string
    {
        return 'Manage and monitor all payouts sent via CHIP Send.';
    }

    /**
     * @return array<Actions\Action>
     */
    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Payout')
                ->icon('heroicon-o-plus'),
        ];
    }
}
