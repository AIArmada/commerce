<?php

declare(strict_types=1);

namespace AIArmada\Docs\Data;

use AIArmada\Docs\Enums\DocType;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class DocData
{
    public ?DateTimeImmutable $issueDate;

    public ?DateTimeImmutable $dueDate;

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>|null  $customerData
     * @param  array<string, mixed>|null  $companyData
     * @param  array<string, mixed>|null  $body
     * @param  array<string, mixed>|null  $metadata
     * @param  array<string, mixed>|null  $pdfOptions
     */
    public function __construct(
        public ?string $docNumber = null,
        public ?string $docType = null,
        public ?string $docTemplateId = null,
        public ?string $templateSlug = null,
        public ?string $docableType = null,
        public ?string $docableId = null,
        public ?string $status = null,
        ?DateTimeInterface $issueDate = null,
        ?DateTimeInterface $dueDate = null,
        public array $items = [],
        public ?int $subtotalMinor = null,
        public ?int $totalMinor = null,
        public ?int $taxRateBasisPoints = null,
        public ?int $taxAmountMinor = null,
        public ?int $discountAmountMinor = null,
        public ?string $currency = null,
        public ?string $notes = null,
        public ?string $terms = null,
        public ?array $body = null,
        public ?array $customerData = null,
        public ?array $companyData = null,
        public ?array $metadata = null,
        public ?array $pdfOptions = null,
        public ?bool $generatePdf = false,
    ) {
        $this->issueDate = $issueDate === null ? null : DateTimeImmutable::createFromInterface($issueDate);
        $this->dueDate = $dueDate === null ? null : DateTimeImmutable::createFromInterface($dueDate);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        foreach (['subtotal', 'total', 'tax_rate', 'tax_amount', 'discount_amount'] as $unsupportedKey) {
            if (array_key_exists($unsupportedKey, $data)) {
                throw new InvalidArgumentException(sprintf(
                    'Removed major-unit document field `%s` is not accepted; provide the corresponding minor-unit integer field.',
                    $unsupportedKey,
                ));
            }
        }

        foreach (['subtotal_minor', 'total_minor', 'tax_rate_basis_points', 'tax_amount_minor', 'discount_amount_minor'] as $integerKey) {
            if (array_key_exists($integerKey, $data) && $data[$integerKey] !== null && ! is_int($data[$integerKey])) {
                throw new InvalidArgumentException(sprintf('Document field `%s` must be an integer.', $integerKey));
            }
        }

        $docType = $data['doc_type'] ?? 'invoice';

        if (! is_string($docType) || DocType::tryFrom($docType) === null) {
            throw new InvalidArgumentException(sprintf('Unknown document type [%s].', is_scalar($docType) ? (string) $docType : gettype($docType)));
        }

        return new self(
            docNumber: $data['doc_number'] ?? null,
            docType: $docType,
            docTemplateId: $data['doc_template_id'] ?? null,
            templateSlug: $data['template_slug'] ?? null,
            docableType: $data['docable_type'] ?? null,
            docableId: $data['docable_id'] ?? null,
            status: $data['status'] ?? null,
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
