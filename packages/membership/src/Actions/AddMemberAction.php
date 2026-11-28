<?php

declare(strict_types=1);

namespace AIArmada\Membership\Actions;

use AIArmada\Membership\Contracts\MembershipHook;
use AIArmada\Membership\Contracts\MembershipMutationGuard;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Queries\MembershipMembersQuery;
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

    public function __construct(
        private readonly MembershipMembersQuery $members,
        private readonly MembershipSubjectGuard $subjectGuard,
        private readonly SyncMembershipRolesAction $roleSync,
    ) {}

    /**
     * Pivot id-column presence by connection and table. Pivot schemas are
     * deploy-time constants, so this never varies within a worker lifecycle.
     *
     * @var array<string, bool>
     */
    private static array $pivotIdColumnCache = [];

    public function handle(Model $subject, Model $user, MemberRole $role): void
    {
        $this->subjectGuard->validate($subject);

        DB::transaction(function () use ($subject, $user, $role): void {
            $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();

            $existingMember = $this->members->find($subject, $user);

            $this->persist($subject, $user, $role, $existingMember, false);
        });

        if (app()->bound(MembershipHook::class)) {
            app(MembershipHook::class)->onMemberAdded($subject, $user, $role);
        }
    }

    /**
     * Persist a membership when the caller already resolved the member row.
     * This keeps role-change workflows from issuing the same membership read twice.
     * Role changes suppress the added hook because they dispatch a role-changed hook instead.
     */
    public function handleResolvedMember(Model $subject, Model $user, MemberRole $role, ?Model $existingMember, bool $notifyAdded = true): void
    {
        $this->subjectGuard->validate($subject);

        $this->persist($subject, $user, $role, $existingMember, $notifyAdded);
    }

    public static function flushPivotIdColumnCache(): void
    {
        self::$pivotIdColumnCache = [];
    }

    private function persist(Model $subject, Model $user, MemberRole $role, ?Model $existingMember, bool $notifyAdded = true): void
    {
        $existingRole = $this->members->role($existingMember);
        $membershipTable = $this->membershipTable($subject);

        try {
            DB::transaction(function () use ($existingMember, $existingRole, $membershipTable, $role, $subject, $user): void {
                if ($subject instanceof MembershipMutationGuard) {
                    $subject->assertMemberCanBeAdded($user, $role, $existingMember);
                }

                $pivotData = [
                    'role' => $role->spatieRoleName(),
                ];

                if ($existingMember === null) {
                    $pivotData['joined_at'] = CarbonImmutable::now();

                    if ($this->pivotHasIdColumn($subject, $membershipTable)) {
                        $pivotData['id'] = (string) Str::uuid();
                    }
                }

                $this->members->relation($subject)->syncWithoutDetaching([
                    $user->getKey() => $pivotData,
                ]);

                $oldRole = is_string($existingRole)
                    ? MemberRole::fromSpatieRoleName($existingRole)
                    : null;

                if ($oldRole !== null && $oldRole !== $role) {
                    $this->roleSync->revokeFromUser($subject, $user, $oldRole);
                }

                $this->roleSync->assignToUser($subject, $user, $role);
            });
        } catch (QueryException $exception) {
            if (! $this->isMembershipUniquenessViolation($exception, $membershipTable)) {
                throw $exception;
            }

            throw new AuthorizationException('The membership already exists or was created concurrently.');
        }

        if ($notifyAdded && app()->bound(MembershipHook::class)) {
            app(MembershipHook::class)->onMemberAdded($subject, $user, $role);
        }
    }

    private function pivotHasIdColumn(Model $subject, string $membershipTable): bool
    {
        $key = $subject->getConnection()->getName() . '.' . $subject->getConnection()->getDatabaseName() . '.' . $membershipTable;

        return self::$pivotIdColumnCache[$key] ??= $subject->getConnection()
            ->getSchemaBuilder()
            ->hasColumn($membershipTable, 'id');
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
