<?php

declare(strict_types=1);

namespace AIArmada\FilamentRewards\Support;

use AIArmada\Rewards\Models\RewardAward;
use AIArmada\Rewards\Models\RewardBenefitItem;
use AIArmada\Rewards\Models\RewardProgram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Program-boundary scoping for admin table queries.
 *
 * The program is the tenant boundary and carries the owner scope; ledger
 * tables carry no owner columns, so admin queries constrain through the
 * program instead. Programs visible in the current owner scope (plus
 * global programs where included) define the visible ledger rows.
 */
final class RewardFilamentScope
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
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function applyViaAward(Builder $query, string $awardColumn = 'award_id'): Builder
    {
        return $query->whereIn(
            $awardColumn,
            RewardAward::query()->select('id')->whereIn('program_id', self::scopedProgramIds())
        );
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function applyViaItem(Builder $query, string $itemColumn = 'item_id'): Builder
    {
        return $query->whereIn(
            $itemColumn,
            RewardBenefitItem::query()->select('id')->whereIn('program_id', self::scopedProgramIds())
        );
    }

    /**
     * Owner-scoped program id subquery. Respects the program OwnerScope
     * (and global inclusion) of the current owner context.
     *
     * @return Builder<RewardProgram>
     */
    public static function scopedProgramIds(): Builder
    {
        return RewardProgram::query()->select((new RewardProgram)->getKeyName());
    }
}
