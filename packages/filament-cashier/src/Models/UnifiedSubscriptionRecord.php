<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class UnifiedSubscriptionRecord extends Model
{
    use HasUuids;

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function getTable(): string
    {
        return 'subscriptions';
    }
}
