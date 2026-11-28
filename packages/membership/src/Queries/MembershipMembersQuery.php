<?php

declare(strict_types=1);

namespace AIArmada\Membership\Queries;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

final readonly class MembershipMembersQuery
{
    /**
     * @return BelongsToMany<Model, Model>
     */
    public function relation(Model $subject): BelongsToMany
    {
        if (! method_exists($subject, 'members')) {
            throw new InvalidArgumentException('The membership subject must expose a members relationship.');
        }

        $relation = $subject->members();

        if (! $relation instanceof BelongsToMany) {
            throw new InvalidArgumentException('The membership subject must expose a belongs-to-many members relationship.');
        }

        return $relation;
    }

    public function find(Model $subject, Model $user): ?Model
    {
        return $this->relation($subject)->whereKey($user->getKey())->first();
    }

    public function role(?Model $member): ?string
    {
        $role = data_get($member?->getRelationValue('pivot'), 'role');

        return is_string($role) ? $role : null;
    }
}
