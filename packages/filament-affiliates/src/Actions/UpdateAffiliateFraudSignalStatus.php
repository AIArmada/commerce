<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates\Actions;

use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Features\Fraud\Actions\ReviewFraudSignal;
use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;
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

        $reviewedBy = auth()->user()?->getAuthIdentifier();
        $result = app(ReviewFraudSignal::class)->execute(
            (string) $record->getKey(),
            $status,
            $reviewedBy === null ? null : (string) $reviewedBy,
            $reviewNotes,
            $rejectLinkedConversion,
        );
        $linkedRejectionError = $result->linkedRejectionError;

        if (is_string($linkedRejectionError)) {
            Notification::make()
                ->warning()
                ->title('Review saved; linked conversion kept')
                ->body($linkedRejectionError)
                ->send();
        }

        return AffiliateFraudSignal::query()->findOrFail($result->signalId);
    }
}
