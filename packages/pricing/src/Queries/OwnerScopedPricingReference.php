<?php

declare(strict_types=1);

namespace AIArmada\Pricing\Queries;

use AIArmada\CommerceSupport\Traits\HasOwner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

final readonly class OwnerScopedPricingReference
{
    public static function assertAccessible(?string $type, int | string | null $id, ?string $ownerType, int | string | null $ownerId, string $label): void
    {
        $type = $type === null ? null : (Relation::getMorphedModel($type) ?? $type);

        if ($type === null || ! class_exists($type) || ! is_a($type, Model::class, true)) {
            return;
        }

        if (! in_array(HasOwner::class, class_uses_recursive($type), true)) {
            return;
        }

        $query = $type::query()->whereKey($id);
        $model = $query->getModel();
        $typeColumn = $model->qualifyColumn('owner_type');
        $idColumn = $model->qualifyColumn('owner_id');

        $query->where(function (Builder $scope) use ($ownerType, $ownerId, $typeColumn, $idColumn): void {
            $scope->where(function (Builder $global) use ($typeColumn, $idColumn): void {
                $global->whereNull($typeColumn)->whereNull($idColumn);
            });

            if ($ownerType !== null && $ownerId !== null) {
                $scope->orWhere(function (Builder $owned) use ($ownerType, $ownerId, $typeColumn, $idColumn): void {
                    $owned->where($typeColumn, $ownerType)->where($idColumn, $ownerId);
                });
            }
        });

        if (! $query->exists()) {
            throw new AuthorizationException("{$label} is not accessible in the current owner scope.");
        }
    }
}
