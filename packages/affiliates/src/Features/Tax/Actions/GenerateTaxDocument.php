<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Tax\Actions;

use AIArmada\Affiliates\Contracts\TaxRecipientProvider;
use AIArmada\Affiliates\Contracts\TaxWorksheetWriter;
use AIArmada\Affiliates\Data\TaxWorksheetRequest;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateAccessGuard;
use AIArmada\Affiliates\Features\Payouts\Api\AnnualPayoutReader;
use AIArmada\Affiliates\Features\Tax\Api\TaxDocumentRecord;
use AIArmada\Affiliates\Features\Tax\Api\TaxDocumentStatus;
use AIArmada\Affiliates\Features\Tax\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Features\Tax\Queries\TaxDocumentQuery;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final readonly class GenerateTaxDocument
{
    public function __construct(private AnnualPayoutReader $payouts, private TaxRecipientProvider $recipients, private TaxWorksheetWriter $writer, private AffiliateAccessGuard $affiliates) {}

    public function execute(string $affiliateId, int $year, ?string $documentId = null): ?TaxDocumentRecord
    {
        $connection = (new AffiliateTaxDocument)->getConnection();
        $this->payouts->assertSharedConnection(spl_object_id($connection));

        $plan = $connection->transaction(function () use ($affiliateId, $year, $documentId): ?array {
            $this->affiliates->lockForWrite($affiliateId, spl_object_id((new AffiliateTaxDocument)->getConnection()));
            $currency = mb_strtoupper((string) config('affiliates.tax.1099_threshold_currency', 'USD'));
            $document = $documentId === null
                ? AffiliateTaxDocument::query()->where(['affiliate_id' => $affiliateId, 'document_type' => '1099-NEC', 'tax_year' => $year, 'currency' => $currency])->lockForUpdate()->first()
                : AffiliateTaxDocument::query()->whereKey($documentId)->where('affiliate_id', $affiliateId)->where('tax_year', $year)->where('document_type', '1099-NEC')->lockForUpdate()->firstOrFail();

            if ($document?->isSent()) {
                if ($documentId !== null) {
                    throw new InvalidArgumentException('Sent documents cannot be regenerated.');
                }

                return ['existing' => TaxDocumentQuery::record($document)];
            }

            $currency = $document?->currency ?? $currency;
            $amount = $this->payouts->total($affiliateId, $year, $currency);

            if ($document === null && $amount < (int) config('affiliates.tax.1099_threshold', 60000)) {
                return null;
            }

            $excluded = $this->payouts->excluded($affiliateId, $year, $currency);

            return [
                'amount' => $amount,
                'currency' => $currency,
                'notes' => $excluded === [] ? null : 'Excluded non-' . $currency . ' payouts for manual review: ' . implode(', ', array_map(
                    static fn (array $leg): string => $leg['reference'] . ' (' . MoneyFormatter::formatMinor($leg['amount_minor'], $leg['currency']) . ')',
                    $excluded,
                )) . '.',
            ];
        });

        if ($plan === null) {
            return null;
        }

        if (isset($plan['existing'])) {
            return $plan['existing'];
        }

        $recipient = $this->recipients->find($affiliateId);
        $missingInfo = true;
        $written = null;
        $notes = $plan['notes'];

        if ($recipient !== null) {
            $missingInfo = mb_trim($recipient->legalName) === ''
                || ! preg_match('/^[0-9]{9}$/D', preg_replace('/[^0-9]/', '', $recipient->tin) ?? '');

            if (! $missingInfo) {
                $written = $this->writer->write(new TaxWorksheetRequest($affiliateId, $year, $plan['amount'], $plan['currency'], $recipient, $notes));
            }
        }

        try {
            $result = $connection->transaction(function () use ($affiliateId, $year, $documentId, $plan, $notes, $written, $missingInfo, $connection): array {
                $this->affiliates->lockForWrite($affiliateId, spl_object_id($connection));
                $document = $documentId === null
                    ? AffiliateTaxDocument::query()->where(['affiliate_id' => $affiliateId, 'document_type' => '1099-NEC', 'tax_year' => $year, 'currency' => $plan['currency']])->lockForUpdate()->first()
                    : AffiliateTaxDocument::query()->whereKey($documentId)->where('affiliate_id', $affiliateId)->where('tax_year', $year)->where('document_type', '1099-NEC')->lockForUpdate()->firstOrFail();

                if ($document?->isSent()) {
                    if ($documentId !== null) {
                        throw new InvalidArgumentException('Sent documents cannot be regenerated.');
                    }

                    return ['record' => TaxDocumentQuery::record($document), 'obsolete' => null];
                }

                if ($this->payouts->total($affiliateId, $year, $plan['currency']) !== $plan['amount']) {
                    throw new RuntimeException('Tax document source totals changed during generation; retry the document.');
                }

                $obsolete = $document?->document_path;
                $document ??= new AffiliateTaxDocument(['affiliate_id' => $affiliateId, 'document_type' => '1099-NEC', 'tax_year' => $year, 'currency' => $plan['currency']]);
                $document->total_amount_minor = $plan['amount'];
                $document->sent_at = null;

                if ($missingInfo) {
                    $document->status = TaxDocumentStatus::PendingInfo;
                    $document->notes = mb_trim('Missing required tax information (TIN or legal name). ' . ($notes ?? ''));
                    $document->document_path = null;
                    $document->generated_at = null;
                } else {
                    $path = $written;
                    $connection->afterRollBack(function () use ($path): void {
                        if ($path !== null) {
                            $this->deleteSafely($path);
                        }
                    });
                    $document->status = TaxDocumentStatus::Generated;
                    $document->notes = $notes;
                    $document->document_path = $written;
                    $document->generated_at = CarbonImmutable::now();
                }

                if (! $document->save()) {
                    throw new RuntimeException('Tax document could not be persisted.');
                }

                return ['record' => TaxDocumentQuery::record($document), 'obsolete' => $obsolete];
            });
        } catch (Throwable $exception) {
            if ($written !== null) {
                $this->deleteSafely($written);
            }

            throw $exception;
        }

        if ($result['obsolete'] !== null && $result['obsolete'] !== $written) {
            $connection->afterCommit(fn () => $this->deleteSafely($result['obsolete']));
        }

        return $result['record'];
    }

    private function deleteSafely(string $path): void
    {
        try {
            $this->writer->delete($path);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
