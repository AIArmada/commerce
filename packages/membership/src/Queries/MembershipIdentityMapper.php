<?php

declare(strict_types=1);

namespace AIArmada\Membership\Queries;

use AIArmada\Membership\Data\MembershipIdentity;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final readonly class MembershipIdentityMapper
{
    public static function fromModel(Model $model): MembershipIdentity
    {
        $id = $model->getKey();

        if ((! is_string($id) && ! is_int($id)) || (string) $id === '') {
            throw new InvalidArgumentException('Membership identity requires a persisted record key.');
        }

        return new MembershipIdentity($model->getMorphClass(), (string) $id);
    }
}
