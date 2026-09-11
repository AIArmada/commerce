<?php

declare(strict_types=1);

namespace AIArmada\Products\Concerns;

use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Builder;
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

                $owner = $model->isGlobal() ? null : OwnerContext::resolve();

                foreach ($model->uniqueIdentityColumns() as $column) {
                    $value = $model->getAttribute($column);

                    if ($value === null || $value === '') {
                        continue;
                    }

                    $query = $model->newQueryWithoutScopes()
                        ->where($column, $value);

                    if (method_exists($model, 'modifyUniqueIdentityQuery')) {
                        /** @var Builder<self> $query */
                        $query = $model->modifyUniqueIdentityQuery($query);
                    }

                    if ($owner === null) {
                        $query->whereNull('owner_type')->whereNull('owner_id');
                    } else {
                        $query->where('owner_type', $owner->getMorphClass())
                            ->where('owner_id', $owner->getKey());
                    }

                    if ($model->exists) {
                        $query->where($model->getKeyName(), '!=', $model->getKey());
                    }

                    $existing = $query->first();

                    if ($existing !== null) {
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
}
