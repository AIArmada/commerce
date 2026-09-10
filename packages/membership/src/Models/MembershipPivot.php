<?php

declare(strict_types=1);

namespace AIArmada\Membership\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Base pivot model for host applications that need custom membership pivot
 * behavior. The host relationship supplies the concrete table name.
 */
abstract class MembershipPivot extends Pivot
{
    protected function casts(): array
    {
        return [
            'joined_at' => 'immutable_datetime',
        ];
    }
}
