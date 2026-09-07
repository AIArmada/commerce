<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Contracts\MembershipHook;
use AIArmada\Membership\Contracts\MembershipMutationGuard;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Services\MembershipRoleSyncService;
use AIArmada\Membership\Support\MembershipSubjectGuard;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class AddMemberAction
{
    use AsAction;

    public function handle(Model $subject, Model $user, MemberRole $role): void
    {
        app(MembershipSubjectGuard::class)->validate($subject);

        /** @phpstan-ignore method.notFound */
        $existingMember = $subject->members()->whereKey($user->getKey())->first();

        $this->persist($subject, $user, $role, $existingMember);
    }

    /**
     * Persist a membership when the caller already resolved the member row.
     * This keeps role-change workflows from issuing the same membership read twice.
     */
    public function handleResolvedMember(Model $subject, Model $user, MemberRole $role, ?Model $existingMember): void
    {
        app(MembershipSubjectGuard::class)->validate($subject);

        $this->persist($subject, $user, $role, $existingMember);
    }

    private function persist(Model $subject, Model $user, MemberRole $role, ?Model $existingMember): void
    {
        /** @phpstan-ignore property.notFound */
        $existingRole = $existingMember?->pivot?->role;
        $membershipTable = $this->membershipTable($subject);

        try {
            DB::transaction(function () use ($existingMember, $existingRole, $membershipTable, $role, $subject, $user): void {
                if ($subject instanceof MembershipMutationGuard) {
                    $subject->assertMemberCanBeAdded($user, $role, $existingMember);
                }

                $pivotData = [
                    'role' => $role->spatieRoleName(),
                    'joined_at' => CarbonImmutable::now(),
                ];

                if (
                    $existingMember === null
                    && $subject->getConnection()->getSchemaBuilder()->hasColumn($membershipTable, 'id')
                ) {
                    $pivotData['id'] = (string) Str::uuid();
                }

                /** @phpstan-ignore method.notFound */
                $subject->members()->syncWithoutDetaching([
                    $user->getKey() => $pivotData,
                ]);

                $oldRole = is_string($existingRole)
                    ? MemberRole::fromSpatieRoleName($existingRole)
                    : null;

                if ($oldRole !== null && $oldRole !== $role) {
                    app(MembershipRoleSyncService::class)->revokeFromUser($subject, $user, $oldRole);
                }

                app(MembershipRoleSyncService::class)->assignToUser($subject, $user, $role);
            });
        } catch (QueryException $exception) {
            if (! $this->isMembershipUniquenessViolation($exception, $membershipTable)) {
                throw $exception;
            }

            throw new AuthorizationException('The membership already exists or was created concurrently.');
        }

        if (app()->bound(MembershipHook::class)) {
            app(MembershipHook::class)->onMemberAdded($subject, $user, $role);
        }
    }

    private function membershipTable(Model $subject): string
    {
        if (! method_exists($subject::class, 'membersTable')) {
            throw new InvalidArgumentException('The membership subject must expose a membersTable method.');
        }

        /** @var callable(): string $resolveMembershipTable */
        $resolveMembershipTable = [$subject, 'membersTable'];

        return $resolveMembershipTable();
    }

    private function isMembershipUniquenessViolation(QueryException $exception, string $membershipTable): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            && (str_contains($message, mb_strtolower($membershipTable))
                || str_contains($message, 'organization_id') && str_contains($message, 'user_id'));
    }
}
