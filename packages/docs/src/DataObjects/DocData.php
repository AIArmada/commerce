<?php

declare(strict_types=1);

namespace AIArmada\Docs\DataObjects;

use AIArmada\Docs\States\DocStatus;
use DateTimeInterface;
use InvalidArgumentException;

final class DocData
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>|null  $customerData
     * @param  array<string, mixed>|null  $companyData
     * @param  array<string, mixed>|null  $body
     * @param  array<string, mixed>|null  $metadata
     * @param  array<string, mixed>|null  $pdfOptions
     */
    public function __construct(
        public readonly ?string $docNumber = null,
        public readonly ?string $docType = null,
        public readonly ?string $docTemplateId = null,
        public readonly ?string $templateSlug = null,
        public readonly ?string $docableType = null,
        public readonly ?string $docableId = null,
        public readonly ?DocStatus $status = null,
        public readonly ?DateTimeInterface $issueDate = null,
        public readonly ?DateTimeInterface $dueDate = null,
        public readonly array $items = [],
        public readonly ?int $subtotalMinor = null,
        public readonly ?int $totalMinor = null,
        public readonly ?int $taxRateBasisPoints = null,
        public readonly ?int $taxAmountMinor = null,
        public readonly ?int $discountAmountMinor = null,
        public readonly ?string $currency = null,
        public readonly ?string $notes = null,
        public readonly ?string $terms = null,
        public readonly ?array $body = null,
        public readonly ?array $customerData = null,
        public readonly ?array $companyData = null,
        public readonly ?array $metadata = null,
        public readonly ?array $pdfOptions = null,
        public readonly ?bool $generatePdf = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        foreach (['subtotal', 'total', 'tax_rate', 'tax_amount', 'discount_amount'] as $legacyKey) {
            if (array_key_exists($legacyKey, $data)) {
                throw new InvalidArgumentException(sprintf(
                    'Removed major-unit document field `%s` is not accepted; provide the corresponding minor-unit integer field.',
                    $legacyKey,
                ));
            }
        }

        foreach (['subtotal_minor', 'total_minor', 'tax_rate_basis_points', 'tax_amount_minor', 'discount_amount_minor'] as $integerKey) {
            if (array_key_exists($integerKey, $data) && $data[$integerKey] !== null && ! is_int($data[$integerKey])) {
                throw new InvalidArgumentException(sprintf('Document field `%s` must be an integer.', $integerKey));
            }
        }

        return new self(
            docNumber: $data['doc_number'] ?? null,
            docType: $data['doc_type'] ?? 'invoice',
            docTemplateId: $data['doc_template_id'] ?? null,
            templateSlug: $data['template_slug'] ?? null,
            docableType: $data['docable_type'] ?? null,
            docableId: $data['docable_id'] ?? null,
            status: isset($data['status']) ? DocStatus::fromString($data['status']) : null,
            issueDate: $data['issue_date'] ?? null,
            dueDate: $data['due_date'] ?? null,
            items: $data['items'] ?? [],
            subtotalMinor: $data['subtotal_minor'] ?? null,
            totalMinor: $data['total_minor'] ?? null,
            taxRateBasisPoints: $data['tax_rate_basis_points'] ?? null,
            taxAmountMinor: $data['tax_amount_minor'] ?? null,
            discountAmountMinor: $data['discount_amount_minor'] ?? null,
            currency: $data['currency'] ?? null,
            notes: $data['notes'] ?? null,
            terms: $data['terms'] ?? null,
            body: $data['body'] ?? null,
            customerData: $data['customer_data'] ?? null,
            companyData: $data['company_data'] ?? null,
            metadata: $data['metadata'] ?? null,
            pdfOptions: $data['pdf_options'] ?? $data['pdf'] ?? null,
            generatePdf: $data['generate_pdf'] ?? false,
        );
    }
}
