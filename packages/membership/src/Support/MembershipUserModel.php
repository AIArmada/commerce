<?php

declare(strict_types=1);

namespace AIArmada\Membership\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class MembershipUserModel
{
    /** @return class-string<Model> */
    public static function resolve(): string
    {
        $model = config('auth.providers.users.model');

        if (! is_string($model) || ! is_a($model, Model::class, true)) {
            throw new InvalidArgumentException('Membership requires a configured Eloquent user model.');
        }

        return $model;
    }

    public static function assertPersisted(Model $user): void
    {
        if (! is_a($user, self::resolve()) || ! $user->exists || $user->getKey() === null) {
            throw new InvalidArgumentException('The actor must be a persisted instance of the configured membership user model.');
        }
    }
}
