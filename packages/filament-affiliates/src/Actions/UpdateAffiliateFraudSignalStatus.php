<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Actions;

use AIArmada\Affiliates\Actions\Conversions\VoidAffiliateConversion;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
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

        $linkedRejectionError = null;

        $signal = DB::transaction(function () use ($record, $status, $reviewNotes, $rejectLinkedConversion, &$linkedRejectionError): AffiliateFraudSignal {
            /** @var AffiliateFraudSignal $signal */
            $signal = AffiliateFraudSignal::query()
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
                try {
                    VoidAffiliateConversion::run(
                        $signal->conversion,
                        sprintf('Fraud review rejected linked conversion (signal %s).', (string) $signal->getKey()),
                    );
                } catch (InvalidArgumentException $exception) {
                    // A refused linked rejection (reserved by an open
                    // payout, conversion-side validation) must not eat
                    // the analyst's review: the transaction commits and
                    // the refusal surfaces as its own notification.
                    $linkedRejectionError = $exception->getMessage();
                }
            }

            return $signal->refresh();
        }, attempts: 3);

        if (is_string($linkedRejectionError)) {
            Notification::make()
                ->warning()
                ->title('Review saved; linked conversion kept')
                ->body($linkedRejectionError)
                ->send();
        }

        return $signal;
    }
}
