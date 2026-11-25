<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Actions;

use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use Filament\Actions\BulkAction;
use Filament\Forms;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

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
            $status = FraudSignalStatus::tryFrom((string) ($data['status'] ?? ''));

            if (! $status instanceof FraudSignalStatus) {
                throw new InvalidArgumentException('Invalid fraud signal status selected.');
            }

            $reviewNotes = $data['review_notes'] ?? null;
            $reviewNotes = is_string($reviewNotes) && $reviewNotes !== '' ? $reviewNotes : null;

            $records->each(function ($record) use ($status, $reviewNotes): void {
                if (! $record instanceof AffiliateFraudSignal) {
                    return;
                }

                UpdateAffiliateFraudSignalStatus::run($record, $status, $reviewNotes);
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
