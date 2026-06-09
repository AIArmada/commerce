<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentRefunded
{
    use Dispatchable;
    use SerializesModels;

    public Model $billable;

    public array $purchase;

    public function __construct(Model $billable, array $purchase)
    {
        $this->billable = $billable;
        $this->purchase = $purchase;
    }

    public function metadata(): array
    {
        $metadata = $this->purchase['metadata'] ?? [];

        return is_array($metadata) ? $metadata : [];
    }

    public function reference(): ?string
    {
        $reference = $this->purchase['reference']
            ?? $this->purchase['reference_generated']
            ?? null;

        return is_string($reference) && $reference !== '' ? $reference : null;
    }
}
