<?php

declare(strict_types=1);

namespace AIArmada\FilamentReferrals\Support;

use AIArmada\Referrals\Models\ReferralProgram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ReferralFilamentScope
{
    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function applyProgramColumn(Builder $query, string $column = 'program_id'): Builder
    {
        return $query->whereIn($column, self::scopedProgramIds());
    }

    /**
     * Owner-scoped program ID subquery. ReferralProgram owns the tenant
     * boundary and its OwnerScope resolves the active owner context.
     *
     * @return Builder<ReferralProgram>
     */
    public static function scopedProgramIds(): Builder
    {
        return ReferralProgram::query()->select((new ReferralProgram)->getKeyName());
    }
}
