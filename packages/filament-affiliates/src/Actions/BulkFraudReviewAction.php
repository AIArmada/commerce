<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Actions;

use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use Filament\Actions\BulkAction;
use Filament\Forms;
use Illuminate\Database\Eloquent\Collection;

final class BulkFraudReviewAction extends BulkAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Review Fraud Signals');
        $this->icon('heroicon-o-shield-check');
        $this->color('warning');
        $this->requiresConfirmation();
        $this->modalHeading('Review Selected Fraud Signals');

        $this->form([
            Forms\Components\Select::make('status')
                ->label('New Status')
                ->options([
                    FraudSignalStatus::Reviewed->value => 'Reviewed',
                    FraudSignalStatus::Dismissed->value => 'Dismissed',
                    FraudSignalStatus::Confirmed->value => 'Confirmed',
                ])
                ->required(),

            Forms\Components\Textarea::make('review_notes')
                ->label('Review Notes')
                ->rows(3),
        ]);

        $this->action(function (Collection $records, array $data): void {
            $records->each(function (AffiliateFraudSignal $signal) use ($data): void {
                $signal->update([
                    'status' => $data['status'],
                    'review_notes' => $data['review_notes'] ?? null,
                    'reviewed_at' => now(),
                ]);
            });

            $this->success();
        });

        $this->deselectRecordsAfterCompletion();
    }

    public static function getDefaultName(): ?string
    {
        return 'bulk_fraud_review';
    }
}
