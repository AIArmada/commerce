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
        $written = null;
        $obsolete = null;

        $connection = (new AffiliateTaxDocument)->getConnection();

        $this->payouts->assertSharedConnection(spl_object_id($connection));

        try {
            $result = $connection->transaction(function () use ($connection, $affiliateId, $year, $documentId, &$written, &$obsolete): ?TaxDocumentRecord {
                $this->affiliates->lockForWrite($affiliateId, spl_object_id($connection));
                $currency = mb_strtoupper((string) config('affiliates.tax.1099_threshold_currency', 'USD'));
                $document = $documentId === null
                    ? AffiliateTaxDocument::query()->where(['affiliate_id' => $affiliateId, 'document_type' => '1099-NEC', 'tax_year' => $year, 'currency' => $currency])->lockForUpdate()->first()
                    : AffiliateTaxDocument::query()->whereKey($documentId)->where('affiliate_id', $affiliateId)->where('tax_year', $year)->where('document_type', '1099-NEC')->lockForUpdate()->firstOrFail();

                if ($document?->isSent()) {
                    if ($documentId !== null) {
                        throw new InvalidArgumentException('Sent documents cannot be regenerated.');
                    }

                    return TaxDocumentQuery::record($document);
                }

                $currency = $document?->currency ?? $currency;
                $amount = $this->payouts->total($affiliateId, $year, $currency);
                if ($document === null && $amount < (int) config('affiliates.tax.1099_threshold', 60000)) {
                    return null;
                }

                $excluded = $this->payouts->excluded($affiliateId, $year, $currency);
                $notes = $excluded === [] ? null : 'Excluded non-' . $currency . ' payouts for manual review: ' . implode(', ', array_map(
                    static fn (array $leg): string => $leg['reference'] . ' (' . MoneyFormatter::formatMinor($leg['amount_minor'], $leg['currency']) . ')',
                    $excluded,
                )) . '.';
                $recipient = $this->recipients->find($affiliateId);
                $document ??= new AffiliateTaxDocument(['affiliate_id' => $affiliateId, 'document_type' => '1099-NEC', 'tax_year' => $year, 'currency' => $currency]);
                $obsolete = $document->document_path;
                $document->total_amount_minor = $amount;
                $document->sent_at = null;

                if ($recipient === null || mb_trim($recipient->legalName) === '' || ! preg_match('/^[0-9]{9}$/D', preg_replace('/[^0-9]/', '', $recipient->tin) ?? '')) {
                    $document->status = TaxDocumentStatus::PendingInfo;
                    $document->notes = mb_trim('Missing required tax information (TIN or legal name). ' . ($notes ?? ''));
                    $document->document_path = null;
                    $document->generated_at = null;
                } else {
                    $written = $this->writer->write(new TaxWorksheetRequest($affiliateId, $year, $amount, $currency, $recipient, $notes));
                    $path = $written;
                    $connection->afterRollBack(function () use ($path): void {
                        $this->deleteSafely($path);
                    });
                    $document->status = TaxDocumentStatus::Generated;
                    $document->notes = $notes;
                    $document->document_path = $written;
                    $document->generated_at = CarbonImmutable::now();
                }

                if (! $document->save()) {
                    throw new RuntimeException('Tax document could not be persisted.');
                }

                return TaxDocumentQuery::record($document);
            });
        } catch (Throwable $exception) {
            if ($written !== null) {
                $this->deleteSafely($written);
            }

            throw $exception;
        }

        if ($obsolete !== null && $obsolete !== $written) {
            $connection->afterCommit(fn () => $this->deleteSafely($obsolete));
        }

        return $result;
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
