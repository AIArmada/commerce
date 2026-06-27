<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class UnifiedSubscriptionRecord extends Model
{
    use HasUuids;

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'billable_type',
        'billable_id',
        'type',
        'name',
        'stripe_price',
        'chip_price',
        'plan_id',
        'currency',
        'quantity',
        'trial_ends_at',
        'ends_at',
        'next_billing_at',
        'created_at',
        'updated_at',
    ];

    public function getTable(): string
    {
        return 'subscriptions';
    }
}
