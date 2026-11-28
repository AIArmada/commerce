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

    /**
     * Pivot id-column presence by connection and table. Pivot schemas are
     * deploy-time constants, so this never varies within a worker lifecycle.
     *
     * @var array<string, bool>
     */
    private static array $pivotIdColumnCache = [];

    public static function flushPivotIdColumnCache(): void
    {
        self::$pivotIdColumnCache = [];
    }

    public function handle(Model $subject, Model $user, MemberRole $role, bool $notifyAdded = true): void
    {
        app(MembershipSubjectGuard::class)->validate($subject);
        $membershipTable = $this->membershipTable($subject);

        try {
            DB::transaction(function () use ($membershipTable, $role, $subject, $user): void {
                $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();

                /** @phpstan-ignore method.notFound */
                $existingMember = $subject->members()->whereKey($user->getKey())->first();

                /** @phpstan-ignore property.notFound */
                $existingRole = $existingMember?->pivot?->role;

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

        if ($notifyAdded && app()->bound(MembershipHook::class)) {
            app(MembershipHook::class)->onMemberAdded($subject, $user, $role);
        }
    }

    private function pivotHasIdColumn(Model $subject, string $membershipTable): bool
    {
        $key = ($subject->getConnectionName() ?? 'default') . '.' . $membershipTable;

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
