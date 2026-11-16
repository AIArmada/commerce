<?php

declare(strict_types=1);

namespace AIArmada\Products\Support;

use AIArmada\CommerceSupport\Contracts\Buyable;
use AIArmada\Products\Exceptions\InvalidCompositePackageException;
use AIArmada\Products\Models\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * Explicit application-controlled mapping of external Eloquent Buyables.
 * No untrusted model class names are ever instantiated from a component row.
 */
final class CompositeBuyableRegistry
{
    /** @return class-string<Model&Buyable> */
    public function classFor(string $alias): string
    {
        $types = config('products.composite_buyable_types', []);
        $class = is_array($types) ? ($types[$alias] ?? null) : null;

        if (! is_string($class) || ! is_subclass_of($class, Model::class)
            || ! is_subclass_of($class, Buyable::class)
            || is_a($class, Product::class, true)) {
            throw new InvalidCompositePackageException('Composite Buyable type is not registered or is not an Eloquent Buyable.');
        }

        return $class;
    }

    public function resolve(Product $parent, string $alias, string $id): Model & Buyable
    {
        $class = $this->classFor($alias);

        if (mb_trim($id) === '') {
            throw new InvalidCompositePackageException('Composite Buyable identity cannot be empty.');
        }

        $model = $class::query()->whereKey($id)->first();
        if (! $model instanceof Model || ! $model instanceof Buyable) {
            throw new InvalidCompositePackageException('Referenced composite Buyable is unavailable.');
        }

        $attributes = $model->getAttributes();
        $parentOwnerType = $parent->owner_type;
        $parentOwnerId = $parent->owner_id;

        // An owned package may not reference an unscoped or foreign-owned
        // entity. Even for global packages, the owner tuple must match.
        if (! array_key_exists('owner_type', $attributes)
            || ! array_key_exists('owner_id', $attributes)
            || (string) ($attributes['owner_type'] ?? '') !== (string) ($parentOwnerType ?? '')
            || (string) ($attributes['owner_id'] ?? '') !== (string) ($parentOwnerId ?? '')) {
            throw new InvalidCompositePackageException('Composite Buyable must have the same explicit owner tuple as the package.');
        }

        return $model;
    }
}
