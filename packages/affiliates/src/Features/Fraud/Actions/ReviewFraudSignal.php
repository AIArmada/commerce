<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Actions;

use AIArmada\Affiliates\Actions\Conversions\VoidAffiliateConversion;
use AIArmada\Affiliates\Enums\FraudSignalStatus;
use AIArmada\Affiliates\Features\Fraud\Api\FraudReviewResult;
use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class ReviewFraudSignal
{
    public function execute(string $signalId, FraudSignalStatus $status, ?string $reviewedBy, ?string $reviewNotes = null, bool $rejectLinkedConversion = false): FraudReviewResult
    {
        if ($status === FraudSignalStatus::Detected) {
            throw new InvalidArgumentException('Detected is not a valid manual review outcome.');
        }

        $connection = (new AffiliateFraudSignal)->getConnection();

        return $connection->transaction(function () use ($signalId, $status, $reviewedBy, $reviewNotes, $rejectLinkedConversion): FraudReviewResult {
            $signal = AffiliateFraudSignal::query()->whereKey($signalId)->lockForUpdate()->firstOrFail();
            $now = CarbonImmutable::now();
            $attributes = [
                'status' => $status,
                'reviewed_at' => $now,
                'reviewed_by' => $reviewedBy,
            ];

            if ($status === FraudSignalStatus::Dismissed) {
                $attributes['dismissed_at'] = $now;
            }

            if ($status === FraudSignalStatus::Confirmed) {
                $attributes['confirmed_at'] = $now;
            }

            if ($reviewNotes !== null && mb_trim($reviewNotes) !== '') {
                $attributes['evidence'] = array_merge($signal->evidence ?? [], ['review_notes' => mb_trim($reviewNotes)]);
            }

            $signal->update($attributes);
            $rejectionError = null;

            if ($rejectLinkedConversion && $signal->conversion !== null) {
                try {
                    VoidAffiliateConversion::run($signal->conversion, sprintf('Fraud review rejected linked conversion (signal %s).', $signalId));
                } catch (InvalidArgumentException $exception) {
                    $rejectionError = $exception->getMessage();
                }
            }

            return new FraudReviewResult($signalId, $rejectionError);
        }, attempts: 3);
    }
}
