<?php

declare(strict_types=1);

namespace AIArmada\Chip\Support;

use AIArmada\Chip\Events\PaymentRefunded;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Listeners\GenerateDocOnPayment;
use AIArmada\Chip\Listeners\GenerateDocOnRefund;
use AIArmada\Docs\Services\DocService;
use Illuminate\Support\Facades\Event;

/**
 * Registers integration with docs package when installed.
 *
 * When AIArmada\Docs is installed, this registrar:
 * - Auto-generates invoices when purchases are paid
 * - Auto-generates credit notes when payments are refunded
 */
final class DocsIntegrationRegistrar
{
    public function register(): void
    {
        if (! $this->isDocsPackageInstalled()) {
            return;
        }

        if (! config('chip.integrations.docs.enabled', false)) {
            return;
        }

        $this->registerEventListeners();
    }

    private function registerEventListeners(): void
    {
        if (config('chip.integrations.docs.auto_generate_invoice', false)) {
            Event::listen(PurchasePaid::class, GenerateDocOnPayment::class);
        }

        if (config('chip.integrations.docs.auto_generate_credit_note', false)) {
            Event::listen(PaymentRefunded::class, GenerateDocOnRefund::class);
        }
    }

    private function isDocsPackageInstalled(): bool
    {
        return class_exists(DocService::class);
    }
}
