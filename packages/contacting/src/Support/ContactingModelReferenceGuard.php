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

        if (mb_trim($type) === '' || mb_trim($id) === '') {
            throw new InvalidArgumentException('Model reference type and id must not be blank.');
        }

        $modelClass = Relation::getMorphedModel($type) ?? $type;

        if (! class_exists($modelClass) || ! is_a($modelClass, Model::class, true)) {
            throw new InvalidArgumentException('The model reference type is invalid.');
        }

        if ($this->usesOwnerScope($modelClass)) {
            return OwnerWriteGuard::findOrFailForOwner($modelClass, $id);
        }

        /** @var Model|null $model */
        $model = $modelClass::query()->find($id);

        if (! $model instanceof Model) {
            throw new InvalidArgumentException('The referenced model does not exist.');
        }

        return $model;
    }

    public function resolveModel(Model $model, string $argument = 'model'): Model
    {
        if (! $model->exists) {
            throw new InvalidArgumentException(sprintf('The %s must be persisted.', $argument));
        }

        $key = $model->getKey();

        if ((! is_string($key) && ! is_int($key)) || (string) $key === '') {
            throw new InvalidArgumentException(sprintf('The %s must have a non-empty key.', $argument));
        }

        $resolved = $this->resolve($model->getMorphClass(), (string) $key);

        if (! $resolved instanceof Model) {
            throw new InvalidArgumentException(sprintf('The %s does not exist.', $argument));
        }

        return $resolved;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function usesOwnerScope(string $modelClass): bool
    {
        if (method_exists($modelClass, 'ownerScopeConfig')) {
            return $modelClass::ownerScopeConfig()->enabled;
        }

        return method_exists($modelClass, 'scopeForOwner');
    }
}
