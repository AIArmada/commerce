<?php

declare(strict_types=1);

namespace AIArmada\Products\Concerns;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;
use RuntimeException;

trait EnforcesOwnerUniqueIdentity
{
    protected static function bootEnforcesOwnerUniqueIdentity(): void
    {
        static::saving(
            function (self $model): void {
                if (! (bool) config('products.features.owner.enabled', true)) {
                    return;
                }

                if (! method_exists($model, 'uniqueIdentityColumns')) {
                    return;
                }

                $slug = $model->getAttribute('slug');
                $slugMaxLength = (int) config('products.seo.slug_max_length', 100);

                if (is_string($slug) && mb_strlen($slug) > $slugMaxLength) {
                    throw new InvalidArgumentException(sprintf(
                        'The slug may not be longer than %d characters.',
                        $slugMaxLength,
                    ));
                }

                $owner = ! $model->exists && (bool) config('products.features.owner.auto_assign_on_create', true)
                    ? OwnerContext::resolve()
                    : ($model->isGlobal() ? null : ($model->owner ?? OwnerContext::resolve()));

                foreach ($model->uniqueIdentityColumns() as $column) {
                    $value = $model->getAttribute($column);

                    if ($value === null || $value === '') {
                        continue;
                    }

                    $query = $model->newQueryWithoutScopes()
                        ->where($column, $value);

                    if ($column !== 'slug') {
                        $query->forOwner($owner, false);
                    }

                    if ($model->exists) {
                        $query->where($model->getKeyName(), '!=', $model->getKey());
                    }

                    $existing = $query->first();

                    if ($existing !== null) {
                        if ($column === 'slug' && ! self::sameOwner($existing, $owner)) {
                            throw new InvalidArgumentException(sprintf(
                                'The %s "%s" is already used by another owner.',
                                $column,
                                $value,
                            ));
                        }

                        if (! $model->exists) {
                            $exception = new UniqueConstraintViolationException(
                                $model->getConnectionName(),
                                "insert into {$model->getTable()} ({$column}) values (?)",
                                [$value],
                                new RuntimeException(sprintf(
                                    'The %s "%s" is already used by another record for this owner.',
                                    $column,
                                    $value,
                                )),
                            );
                            $exception->setColumns([$column]);

                            throw $exception;
                        }

                        throw new InvalidArgumentException(sprintf(
                            'The %s "%s" is already used by another record for this owner.',
                            $column,
                            $value,
                        ));
                    }
                }
            }
        );
    }

    private static function sameOwner(Model $model, ?Model $owner): bool
    {
        return $model->getAttribute('owner_type') === $owner?->getMorphClass()
            && (string) $model->getAttribute('owner_id') === (string) $owner?->getKey();
    }
}
