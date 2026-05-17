<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Enums\DocApprovalStatus;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Enums\ResetFrequency;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocApproval;
use AIArmada\Docs\Models\DocEmailTemplate;
use AIArmada\Docs\Models\DocSequence;
use AIArmada\Docs\Models\DocStatusHistory;
use AIArmada\Docs\Models\DocTemplate;
use AIArmada\Docs\States\Overdue;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\Pending;
use AIArmada\Docs\States\Sent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * 🎭 SHOWCASE: Documents, Approvals, and Cash Collection
 *
 * Demonstrates the docs package with:
 * - Templates and numbering sequences
 * - Paid, pending, and overdue commercial documents
 * - Approval workflows and reminder templates
 */
final class DocsShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎭 Creating Docs Showcase Data...');

        $owner = OwnerContext::resolve();

        if (! $owner instanceof User) {
            return;
        }

        $requestedBy = User::query()
            ->where('email', 'manager@commerce.demo')
            ->first() ?? $owner;

        $invoiceTemplate = DocTemplate::query()->updateOrCreate(
            ['slug' => 'doc-default'],
            [
                'name' => 'Default Invoice Template',
                'description' => 'Clean invoice template used for the commerce demo storefront.',
                'view_name' => 'doc-default',
                'doc_type' => DocType::Invoice->value,
                'is_default' => true,
                'settings' => [
                    'show_logo' => false,
                    'primary_color' => '#111827',
                    'accent_color' => '#f59e0b',
                ],
            ],
        );
        $invoiceTemplate->setAsDefault();

        $quotationTemplate = DocTemplate::query()->updateOrCreate(
            ['slug' => 'quotation-default'],
            [
                'name' => 'Sales Quotation Template',
                'description' => 'Quote-friendly layout for higher-value assisted sales.',
                'view_name' => 'doc-default',
                'doc_type' => DocType::Quotation->value,
                'is_default' => true,
                'settings' => [
                    'show_logo' => false,
                    'primary_color' => '#0f766e',
                    'accent_color' => '#14b8a6',
                ],
            ],
        );
        $quotationTemplate->setAsDefault();

        DocSequence::query()->updateOrCreate(
            ['name' => 'Invoice Sequence'],
            [
                'doc_type' => DocType::Invoice->value,
                'prefix' => 'INV-DEMO',
                'format' => '{PREFIX}-{NUMBER}',
                'reset_frequency' => ResetFrequency::Yearly,
                'start_number' => 101,
                'increment' => 1,
                'padding' => 6,
                'is_active' => true,
            ],
        );

        DocSequence::query()->updateOrCreate(
            ['name' => 'Quotation Sequence'],
            [
                'doc_type' => DocType::Quotation->value,
                'prefix' => 'QUO-DEMO',
                'format' => '{PREFIX}-{NUMBER}',
                'reset_frequency' => ResetFrequency::Yearly,
                'start_number' => 201,
                'increment' => 1,
                'padding' => 6,
                'is_active' => true,
            ],
        );

        DocEmailTemplate::query()->updateOrCreate(
            ['slug' => 'invoice-payment-reminder'],
            [
                'name' => 'Invoice Payment Reminder',
                'doc_type' => DocType::Invoice->value,
                'trigger' => 'payment_reminder',
                'subject' => 'Reminder: payment due for {{ doc_number }}',
                'body' => 'Hi {{ customer_name }}, this is a friendly reminder that {{ doc_number }} for {{ total }} is still awaiting payment.',
                'is_active' => true,
            ],
        );

        $paidInvoice = Doc::query()->updateOrCreate(
            ['doc_number' => 'INV-DEMO-000101'],
            [
                'doc_type' => DocType::Invoice->value,
                'doc_template_id' => $invoiceTemplate->getKey(),
                'status' => Paid::class,
                'issue_date' => CarbonImmutable::now()->subDays(14),
                'due_date' => CarbonImmutable::now()->subDays(4),
                'paid_at' => CarbonImmutable::now()->subDays(2),
                'subtotal' => '2499.00',
                'tax_amount' => '0.00',
                'discount_amount' => '150.00',
                'total' => '2349.00',
                'currency' => 'MYR',
                'notes' => 'Paid invoice generated from a completed assisted sale.',
                'terms' => 'Payable within 30 days via bank transfer or demo gateway.',
                'customer_data' => $this->customerData('Alicia Tan', 'alicia@commerce.demo', '+60112233445'),
                'company_data' => $this->companyData(),
                'items' => [
                    $this->lineItem('Commerce Growth Suite', 1, '2499.00'),
                ],
                'metadata' => [
                    'showcase_key' => 'paid_invoice',
                    'sales_channel' => 'assisted',
                ],
            ],
        );

        $overdueInvoice = Doc::query()->updateOrCreate(
            ['doc_number' => 'INV-DEMO-000102'],
            [
                'doc_type' => DocType::Invoice->value,
                'doc_template_id' => $invoiceTemplate->getKey(),
                'status' => Overdue::class,
                'issue_date' => CarbonImmutable::now()->subDays(21),
                'due_date' => CarbonImmutable::now()->subDays(6),
                'paid_at' => null,
                'subtotal' => '1299.00',
                'tax_amount' => '0.00',
                'discount_amount' => '0.00',
                'total' => '1299.00',
                'currency' => 'MYR',
                'notes' => 'Outstanding invoice used to demonstrate overdue tracking.',
                'terms' => 'Please settle within 14 days to avoid service interruption.',
                'customer_data' => $this->customerData('Rahim Salleh', 'rahim@commerce.demo', '+60119876543'),
                'company_data' => $this->companyData(),
                'items' => [
                    $this->lineItem('Inventory Optimization Audit', 1, '1299.00'),
                ],
                'metadata' => [
                    'showcase_key' => 'overdue_invoice',
                    'sales_channel' => 'b2b',
                ],
            ],
        );

        $pendingQuotation = Doc::query()->updateOrCreate(
            ['doc_number' => 'QUO-DEMO-000201'],
            [
                'doc_type' => DocType::Quotation->value,
                'doc_template_id' => $quotationTemplate->getKey(),
                'status' => Pending::class,
                'issue_date' => CarbonImmutable::now()->subDays(2),
                'due_date' => CarbonImmutable::now()->addDays(10),
                'paid_at' => null,
                'subtotal' => '4999.00',
                'tax_amount' => '0.00',
                'discount_amount' => '250.00',
                'total' => '4749.00',
                'currency' => 'MYR',
                'notes' => 'Enterprise quotation awaiting final approval before conversion.',
                'terms' => 'Quotation valid for 14 days from issue date.',
                'customer_data' => $this->customerData('Siti Commerce Ops', 'siti.ops@commerce.demo', '+60135556677'),
                'company_data' => $this->companyData(),
                'items' => [
                    $this->lineItem('Commerce Operations Retainer', 1, '3999.00'),
                    $this->lineItem('Analytics Enablement Workshop', 1, '1000.00'),
                ],
                'metadata' => [
                    'showcase_key' => 'pending_quotation',
                    'sales_channel' => 'field',
                ],
            ],
        );

        $this->createStatusHistory($paidInvoice, Pending::class, 'Invoice sent to customer.', (string) $requestedBy->getKey());
        $this->createStatusHistory($paidInvoice, Paid::class, 'Customer completed payment via assisted flow.', (string) $owner->getKey());

        $this->createStatusHistory($overdueInvoice, Sent::class, 'Reminder email delivered automatically.', (string) $requestedBy->getKey());
        $this->createStatusHistory($overdueInvoice, Overdue::class, 'Payment deadline elapsed; invoice is now overdue.', (string) $owner->getKey());

        $this->createStatusHistory($pendingQuotation, Pending::class, 'Quotation shared with customer and awaiting approval.', (string) $requestedBy->getKey());

        DocApproval::query()->updateOrCreate(
            [
                'doc_id' => $pendingQuotation->getKey(),
                'assigned_to' => (string) $owner->getKey(),
            ],
            [
                'requested_by' => (string) $requestedBy->getKey(),
                'status' => DocApprovalStatus::Pending,
                'comments' => 'Please confirm margin and customer commitments before sending.',
                'approved_at' => null,
                'rejected_at' => null,
                'expires_at' => CarbonImmutable::now()->addDays(5),
            ],
        );

        $this->command->info('✅ Docs Showcase Complete!');
    }

    private function createStatusHistory(Doc $doc, string $status, string $notes, string $changedBy): void
    {
        DocStatusHistory::query()->firstOrCreate(
            [
                'doc_id' => $doc->getKey(),
                'status' => $status,
            ],
            [
                'notes' => $notes,
                'changed_by' => $changedBy,
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    private function customerData(string $name, string $email, string $phone): array
    {
        return [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function companyData(): array
    {
        return [
            'name' => 'Commerce Demo Sdn. Bhd.',
            'email' => 'finance@commerce.demo',
            'phone' => '+60312345678',
            'city' => 'Kuala Lumpur',
            'country' => 'MY',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineItem(string $description, int $quantity, string $unitPrice): array
    {
        $unitPriceFloat = (float) $unitPrice;

        return [
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPriceFloat,
            'total' => $unitPriceFloat * $quantity,
        ];
    }
}
