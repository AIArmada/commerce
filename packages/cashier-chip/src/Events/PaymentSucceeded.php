<?php

declare(strict_types=1);

namespace AIArmada\CashierChip\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentSucceeded
{
    use Dispatchable;
    use SerializesModels;

    /**
     * The billable entity.
     */
    public Model $billable;

    /**
     * The CHIP purchase data.
     */
    public array $purchase;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Model $billable, array $purchase)
    {
        $this->billable = $billable;
        $this->purchase = $purchase;
    }
}
