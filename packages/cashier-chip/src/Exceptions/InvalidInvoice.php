<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Exceptions;

use AIArmada\CashierChip\Invoice;
use Exception;
use Illuminate\Database\Eloquent\Model;

class InvalidInvoice extends Exception
{
    /**
     * Create a new InvalidInvoice instance for invalid owner.
     */
    public static function invalidOwner(Invoice $invoice, Model $owner): self
    {
        $chipId = $owner->getAttribute('chip_id');

        return new self("The invoice `{$invoice->id()}` does not belong to this customer `{$chipId}`.");
    }

    /**
     * Create a new InvalidInvoice instance for not found invoice.
     */
    public static function notFound(string $invoiceId): self
    {
        return new self("The invoice `{$invoiceId}` was not found.");
    }

    /**
     * Create a new InvalidInvoice instance for invalid status.
     */
    public static function invalidStatus(string $invoiceId, string $status): self
    {
        return new self("The invoice `{$invoiceId}` has an invalid status `{$status}`.");
    }
}
