<?php

declare(strict_types=1);

namespace AIArmada\Persons\Support;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class PersonsModelReferenceGuard
{
    /**
     * @param  class-string<Model>|null  $modelClass
     */
    public function resolve(?string $modelClass, mixed $id, string $field): ?Model
    {
        if ($id === null) {
            return null;
        }

        if ($modelClass === null) {
            throw new InvalidArgumentException(sprintf(
                'The %s reference cannot be saved until its model is configured.',
                $field,
            ));
        }

        if (! class_exists($modelClass) || ! is_a($modelClass, Model::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'The configured model for %s must be an Eloquent model.',
                $field,
            ));
        }

        if ($this->usesOwnerScope($modelClass)) {
            return OwnerWriteGuard::findOrFailForOwner($modelClass, (string) $id);
        }

        /** @var Model|null $model */
        $model = $modelClass::query()->find($id);

        if (! $model instanceof Model) {
            throw new InvalidArgumentException(sprintf('The referenced %s does not exist.', $field));
        }

        return $model;
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
