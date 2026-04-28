<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Actions;

use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use AIArmada\FilamentAffiliates\Support\OwnerScopedQuery;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class UpdateAffiliateFraudSignalStatus
{
    use AsAction;

    public function handle(AffiliateFraudSignal $record, FraudSignalStatus $status): AffiliateFraudSignal
    {
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

        return $signal->refresh();
    }
}
