<?php

declare(strict_types=1);

namespace AIArmada\References\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;

final readonly class ValidateContributorIdentity
{
    public function type(string $type): string
    {
        $type = mb_trim($type);
        $class = Relation::getMorphedModel($type) ?? $type;

        if (! is_subclass_of($class, Model::class) || ! (new ReflectionClass($class))->isInstantiable()) {
            throw new InvalidArgumentException('Reference contributor type must identify a concrete Eloquent model.');
        }

        return (new $class)->getMorphClass();
    }

    public function id(string $id): string
    {
        $id = mb_strtolower(mb_trim($id));

        if (! Str::isUuid($id)) {
            throw new InvalidArgumentException('Reference contributor IDs must be UUIDs.');
        }

        return $id;
    }
}
