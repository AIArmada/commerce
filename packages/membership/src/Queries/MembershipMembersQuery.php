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
        return $this->relationForMember($subject, $user)->whereKey($user->getKey())->first();
    }

    /** @return BelongsToMany<Model, Model> */
    public function relationForMember(Model $subject, Model $user): BelongsToMany
    {
        $relation = $this->relation($subject);

        if (! is_a($user, $relation->getRelated()::class) || ! $user->exists || $user->getKey() === null) {
            throw new InvalidArgumentException('The member must be a persisted instance of the membership user model.');
        }

        return $relation;
    }

    public function role(?Model $member): ?string
    {
        $role = data_get($member?->getRelationValue('pivot'), 'role');

        return is_string($role) ? $role : null;
    }
}
