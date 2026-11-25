<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Tax\Actions;

use AIArmada\Affiliates\Contracts\TaxRecipientProvider;
use AIArmada\Affiliates\Contracts\TaxWorksheetWriter;
use AIArmada\Affiliates\Data\TaxWorksheetRequest;
use AIArmada\Affiliates\Features\Tax\Api\TaxDocumentStatus;
use AIArmada\Affiliates\Features\Tax\Api\TaxDocumentRecord;
use AIArmada\Affiliates\Features\Tax\Models\AffiliateTaxDocument;
use AIArmada\Affiliates\Features\Tax\Queries\TaxDocumentQuery;
use AIArmada\Affiliates\Features\Tax\Queries\TaxPayoutQuery;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Throwable;

final readonly class GenerateTaxDocument
{
    public function __construct(private TaxPayoutQuery $payouts, private TaxRecipientProvider $recipients, private TaxWorksheetWriter $writer) {}

    public function execute(string $affiliateId, int $year, ?string $documentId = null): ?TaxDocumentRecord
    {
        $written = null;
        $obsolete = null;

        try {
            $result = (new AffiliateTaxDocument)->getConnection()->transaction(function () use ($affiliateId, $year, $documentId, &$written, &$obsolete): ?TaxDocumentRecord {
                Affiliate::query()->whereKey($affiliateId)->lockForUpdate()->firstOrFail();
                $currency = $this->payouts->thresholdCurrency();
                $document = $documentId === null
                    ? AffiliateTaxDocument::query()->where(['affiliate_id' => $affiliateId, 'document_type' => '1099-NEC', 'tax_year' => $year, 'currency' => $currency])->lockForUpdate()->first()
                    : AffiliateTaxDocument::query()->whereKey($documentId)->where('affiliate_id', $affiliateId)->lockForUpdate()->firstOrFail();

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
                    $document->status = TaxDocumentStatus::Generated;
                    $document->notes = $notes;
                    $document->document_path = $written;
                    $document->generated_at = CarbonImmutable::now();
                }

                $document->save();

                return TaxDocumentQuery::record($document);
            });
        } catch (Throwable $exception) {
            if ($written !== null) {
                $this->writer->delete($written);
            }

            throw $exception;
        }

        if ($obsolete !== null && $obsolete !== $written) {
            (new AffiliateTaxDocument)->getConnection()->afterCommit(fn () => $this->writer->delete($obsolete));
        }

        return $result;
    }
}
