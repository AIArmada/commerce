<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Support;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

final class ContactingModelReferenceGuard
{
    public function resolve(?string $type, ?string $id): ?Model
    {
        if ($type === null && $id === null) {
            return null;
        }

        if ($type === null || $id === null) {
            throw new InvalidArgumentException('Model reference type and id must both be present or both be null.');
        }

        $modelClass = Relation::getMorphedModel($type) ?? $type;

        if (! class_exists($modelClass) || ! is_a($modelClass, Model::class, true)) {
            throw new InvalidArgumentException('The model reference type is invalid.');
        }

        try {
            return OwnerWriteGuard::findOrFailForOwner($modelClass, $id);
        } catch (InvalidArgumentException) {
        }

        /** @var Model|null $model */
        $model = $modelClass::query()->find($id);

        if (! $model instanceof Model) {
            throw new InvalidArgumentException('The referenced model does not exist.');
        }

        return $model;
    }
}
