<?php

declare(strict_types=1);

namespace AIArmada\Authz\Drivers;

use AIArmada\Authz\Contracts\AdminUserProvisioner;
use AIArmada\CommerceSupport\Data\RecordReference;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class EloquentAdminUserProvisioner implements AdminUserProvisioner
{
    public function __construct(private Hasher $hasher) {}

    /** @param class-string<Model&Authenticatable> $model */
    public function execute(string $model, string $nameColumn, string $emailColumn, string $name, string $email, string $password): RecordReference
    {
        if (! is_a($model, Model::class, true) || ! is_a($model, Authenticatable::class, true)) {
            throw new InvalidArgumentException('The configured user must be an Eloquent authenticatable model.');
        }

        $user = new $model;
        $user->setAttribute($nameColumn, $name);
        $user->setAttribute($emailColumn, $email);
        $user->setAttribute('password', $this->hasher->make($password));
        $user->saveOrFail();

        return new RecordReference($user->getMorphClass(), (string) $user->getKey());
    }
}
