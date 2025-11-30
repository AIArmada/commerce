<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Exceptions;

use AIArmada\CashierChip\Invoice;
use Exception;

class InvalidInvoice extends Exception
{
    /**
     * Create a new InvalidInvoice instance for invalid owner.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     */
    public static function invalidOwner(Invoice $invoice, $owner): self
    {
        return new self("The invoice `{$invoice->id()}` does not belong to this customer `{$owner->chip_id}`.");
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
