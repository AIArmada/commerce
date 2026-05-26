<?php

declare(strict_types=1);

namespace AIArmada\Docs\Enums;

use AIArmada\Docs\Models\Doc;

enum DocMergeTag: string
{
    case DocNumber = 'doc_number';
    case DocType = 'doc_type';
    case CustomerName = 'customer_name';
    case CustomerEmail = 'customer_email';
    case CompanyName = 'company_name';
    case Currency = 'currency';
    case Subtotal = 'subtotal';
    case TaxAmount = 'tax_amount';
    case DiscountAmount = 'discount_amount';
    case Total = 'total';
    case IssueDate = 'issue_date';
    case DueDate = 'due_date';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::DocNumber->value => 'Document number',
            self::DocType->value => 'Document type',
            self::CustomerName->value => 'Customer name',
            self::CustomerEmail->value => 'Customer email',
            self::CompanyName->value => 'Company name',
            self::Currency->value => 'Currency',
            self::Subtotal->value => 'Subtotal',
            self::TaxAmount->value => 'Tax amount',
            self::DiscountAmount->value => 'Discount amount',
            self::Total->value => 'Total',
            self::IssueDate->value => 'Issue date',
            self::DueDate->value => 'Due date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function valuesFor(Doc $doc): array
    {
        return [
            self::DocNumber->value => (string) $doc->doc_number,
            self::DocType->value => (string) $doc->doc_type,
            self::CustomerName->value => (string) data_get($doc->customer_data, 'name', ''),
            self::CustomerEmail->value => (string) data_get($doc->customer_data, 'email', ''),
            self::CompanyName->value => (string) data_get($doc->company_data, 'name', config('docs.company.name', '')),
            self::Currency->value => (string) $doc->currency,
            self::Subtotal->value => number_format((float) $doc->subtotal, 2),
            self::TaxAmount->value => number_format((float) $doc->tax_amount, 2),
            self::DiscountAmount->value => number_format((float) $doc->discount_amount, 2),
            self::Total->value => number_format((float) $doc->total, 2),
            self::IssueDate->value => $doc->issue_date?->format('d M Y') ?? '',
            self::DueDate->value => $doc->due_date?->format('d M Y') ?? '',
        ];
    }
}
