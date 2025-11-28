<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Cashier\Fixtures;

use AIArmada\Cashier\Billable;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Test fixture for a billable user.
 *
 * This is a minimal implementation for testing. In real applications,
 * this would use the gateway-specific traits (StripeBillable, ChipBillable)
 * alongside the unified Billable trait.
 */
class User extends Authenticatable
{
    use Billable;

    protected $guarded = [];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Get the customer name.
     */
    public function customerName(): ?string
    {
        return $this->name;
    }

    /**
     * Get the customer email.
     */
    public function customerEmail(): ?string
    {
        return $this->email;
    }

    /**
     * Get the customer phone.
     */
    public function customerPhone(): ?string
    {
        return $this->phone ?? null;
    }
}
