<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Upline\Queries;

use AIArmada\Affiliates\Contracts\MerchantIdentity;
use AIArmada\Affiliates\Features\Upline\Models\AffiliateUpline;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\AffiliateStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use LogicException;

final readonly class UplinePathQuery
{
    public function __construct(private MerchantIdentity $identity) {}

    /**
     * @param Builder<AffiliateUpline> $paths
     * @param 'ancestor_id'|'descendant_id' $column
     * @return Builder<AffiliateUpline>
     */
    public function commercialEndpoint(Builder $paths, string $column, bool $active = false): Builder
    {
        $model = $paths->getModel();
        $connection = $model->getConnection();
        $identity = $this->identity->ownerTable();

        if ($identity->connectionIdentity !== spl_object_id($connection)) {
            throw new LogicException('Upline queries require a shared database connection.');
        }

        $table = $identity->table;
        $purpose = $connection->getQueryGrammar()->wrap($table . '.purpose');
        $ownerEnabled = (bool) config('affiliates.owner.enabled', false);

        return $paths->whereExists(function (QueryBuilder $query) use ($identity, $table, $purpose, $model, $column, $active, $ownerEnabled): void {
            $query->from($table)->selectRaw('1')
                ->whereColumn($table . '.' . $identity->keyColumn, $model->qualifyColumn($column))
                ->where(function (QueryBuilder $query) use ($table, $purpose): void {
                    $query->whereNull($table . '.purpose')->orWhereRaw('LOWER(TRIM(' . $purpose . ')) = ?', ['general']);
                });

            if ($ownerEnabled) {
                foreach (['owner_type', 'owner_id'] as $ownerColumn) {
                    $left = $table . '.' . $ownerColumn;
                    $right = $model->qualifyColumn($ownerColumn);
                    $query->where(function (QueryBuilder $query) use ($left, $right): void {
                        $query->whereColumn($left, $right)
                            ->orWhere(fn (QueryBuilder $query) => $query->whereNull($left)->whereNull($right));
                    });
                }
            }

            if ($active) {
                $query->where($table . '.status', AffiliateStatus::normalize(Active::class));
            }
        });
    }
}
