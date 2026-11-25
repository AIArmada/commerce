<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class CreateAdminUser
{
    public function __construct(private Hasher $hasher) {}

    /** @param class-string<Model&Authenticatable> $model */
    public function execute(string $model, string $nameColumn, string $emailColumn, string $name, string $email, string $password): Model & Authenticatable
    {
        if (! is_a($model, Model::class, true) || ! is_a($model, Authenticatable::class, true)) {
            throw new InvalidArgumentException('The configured user must be an Eloquent authenticatable model.');
        }

        $user = new $model;
        $user->setAttribute($nameColumn, $name);
        $user->setAttribute($emailColumn, $email);
        $user->setAttribute('password', $this->hasher->make($password));
        $user->saveOrFail();

        return $user;
    }
}
