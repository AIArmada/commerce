<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Fraud\Actions;

use AIArmada\Affiliates\Features\Conversions\Api\ConversionAdministration;
use AIArmada\Affiliates\Features\Conversions\Api\ConversionInspectionReader;
use AIArmada\Affiliates\Features\Fraud\Api\FraudReviewApi;
use AIArmada\Affiliates\Features\Fraud\Api\FraudReviewResult;
use AIArmada\Affiliates\Features\Fraud\Api\FraudSignalStatus;
use AIArmada\Affiliates\Features\Fraud\Models\AffiliateFraudSignal;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateLifecycleReader;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class ReviewFraudSignal implements FraudReviewApi
{
    public function __construct(
        private ConversionAdministration $conversions,
        private ConversionInspectionReader $inspection,
        private AffiliateLifecycleReader $affiliates,
    ) {}

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
                $attributes['confirmed_at'] = null;
            }

            if ($status === FraudSignalStatus::Confirmed) {
                $attributes['confirmed_at'] = $now;
                $attributes['dismissed_at'] = null;
            }

            if ($status === FraudSignalStatus::Reviewed) {
                $attributes['dismissed_at'] = null;
                $attributes['confirmed_at'] = null;
            }

            if ($reviewNotes !== null && mb_trim($reviewNotes) !== '') {
                $attributes['evidence'] = array_merge($signal->evidence ?? [], ['review_notes' => mb_trim($reviewNotes)]);
            }

            $signal->update($attributes);
            $rejectionError = null;

            if ($rejectLinkedConversion && $status === FraudSignalStatus::Confirmed && $signal->conversion_id !== null) {
                try {
                    $conversion = $this->inspection->lock($signal->conversion_id, spl_object_id($signal->getConnection()));
                    $affiliate = $this->affiliates->findById((string) $signal->affiliate_id);

                    if ($conversion === null || $affiliate === null
                        || $conversion->affiliateId !== (string) $signal->affiliate_id
                        || $conversion->ownerType !== $affiliate->reference->ownerType
                        || $conversion->ownerId !== $affiliate->reference->ownerId) {
                        throw new InvalidArgumentException('The linked conversion does not belong to the reviewed affiliate.');
                    }

                    $this->conversions->voidConversion($conversion->id, sprintf('Fraud review rejected linked conversion (signal %s).', $signalId));
                } catch (InvalidArgumentException $exception) {
                    $rejectionError = $exception->getMessage();
                }
            }

            return new FraudReviewResult($signalId, $rejectionError);
        }, attempts: 3);
    }
}
