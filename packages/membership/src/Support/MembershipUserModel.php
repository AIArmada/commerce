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
}
