<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Actions;

use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\FilamentAffiliates\Support\OwnerScopedQuery;
use Filament\Actions\BulkAction;
use Filament\Forms;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
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

            $reviewedBy = auth()->user()?->getAuthIdentifier();
            $reviewedBy = $reviewedBy === null ? null : (string) $reviewedBy;

            $reviewNotes = $data['review_notes'] ?? null;
            $reviewNotes = is_string($reviewNotes) && $reviewNotes !== '' ? $reviewNotes : null;

            $records->each(function (AffiliateFraudSignal $record) use ($status, $reviewedBy, $reviewNotes): void {
                Gate::authorize('update', $record);

                /** @var AffiliateFraudSignal $signal */
                $signal = OwnerScopedQuery::throughAffiliate(AffiliateFraudSignal::query())
                    ->whereKey($record->getKey())
                    ->firstOrFail();

                match ($status) {
                    FraudSignalStatus::Reviewed => $signal->markAsReviewed($reviewedBy),
                    FraudSignalStatus::Dismissed => $signal->dismiss($reviewedBy),
                    FraudSignalStatus::Confirmed => $signal->confirm($reviewedBy),
                    FraudSignalStatus::Detected => throw new InvalidArgumentException(
                        'Detected is not a valid manual review outcome.'
                    ),
                };

                if ($reviewNotes !== null) {
                    $signal->update([
                        'evidence' => array_merge($signal->evidence ?? [], [
                            'review_notes' => $reviewNotes,
                        ]),
                    ]);
                }
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
