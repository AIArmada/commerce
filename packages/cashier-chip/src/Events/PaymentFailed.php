<?php

namespace AIArmada\CashierChip\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\Eloquent\Model;

class PaymentFailed
{
    use Dispatchable, SerializesModels;

    /**
     * The billable entity.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public Model $billable;

    /**
     * The CHIP purchase data.
     *
     * @var array
     */
    public array $purchase;

    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $billable
     * @param  array  $purchase
     * @return void
     */
    public function __construct(Model $billable, array $purchase)
    {
        $this->billable = $billable;
        $this->purchase = $purchase;
    }
}
