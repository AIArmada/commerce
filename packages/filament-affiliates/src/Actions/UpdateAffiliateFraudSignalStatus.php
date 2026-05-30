<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Actions;

use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\States\RejectedConversion;
use AIArmada\FilamentAffiliates\Support\OwnerScopedQuery;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class UpdateAffiliateFraudSignalStatus
{
    use AsAction;

    public function handle(
        AffiliateFraudSignal $record,
        FraudSignalStatus $status,
        ?string $reviewNotes = null,
        bool $rejectLinkedConversion = false,
    ): AffiliateFraudSignal {
        Gate::authorize('update', $record);

        /** @var AffiliateFraudSignal $signal */
        $signal = OwnerScopedQuery::throughAffiliate(AffiliateFraudSignal::query())
            ->whereKey($record->getKey())
            ->firstOrFail();

        $reviewedBy = auth()->user()?->getAuthIdentifier();
        $reviewedBy = $reviewedBy === null ? null : (string) $reviewedBy;

        match ($status) {
            FraudSignalStatus::Reviewed => $signal->markAsReviewed($reviewedBy),
            FraudSignalStatus::Dismissed => $signal->dismiss($reviewedBy),
            FraudSignalStatus::Confirmed => $signal->confirm($reviewedBy),
            default => throw new InvalidArgumentException(sprintf(
                'Fraud signal status "%s" cannot be applied as a manual review outcome.',
                $status->value,
            )),
        };

        $normalizedReviewNotes = is_string($reviewNotes) ? mb_trim($reviewNotes) : null;

        if ($normalizedReviewNotes !== null && $normalizedReviewNotes !== '') {
            $signal->update([
                'evidence' => array_merge($signal->evidence ?? [], [
                    'review_notes' => $normalizedReviewNotes,
                ]),
            ]);
        }

        if ($rejectLinkedConversion && $signal->conversion !== null) {
            $signal->conversion->update([
                'status' => RejectedConversion::class,
            ]);
        }

        return $signal->refresh();
    }
}
