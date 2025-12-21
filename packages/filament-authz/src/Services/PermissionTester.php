<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Services;

use AIArmada\FilamentAuthz\Enums\PolicyDecision;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;

class PermissionTester
{
    public function __construct(
        protected PermissionAggregator $aggregator,
        protected PolicyEngine $policyEngine,
        protected ContextualAuthorizationService $contextualAuth
    ) {}

    /**
     * Test if a user would have a permission.
     *
     * @param  object  $user
     * @param  array<string, mixed>  $context
     * @return array{
     *     allowed: bool,
     *     reason: string,
     *     source: array{type: string, source: string|null, via: string|null},
     *     policy_decision: PolicyDecision|null
     * }
     */
    public function test($user, string $permission, array $context = []): array
    {
        // Check aggregated permissions first
        $hasPermission = $this->aggregator->userHasPermission($user, $permission);

        if ($hasPermission) {
            $source = $this->aggregator->getPermissionSource($user, $permission);

            return [
                'allowed' => true,
                'reason' => $this->formatReason($source),
                'source' => $source,
                'policy_decision' => null,
            ];
        }

        // Check contextual permissions
        if (! empty($context)) {
            $hasContextual = $this->contextualAuth->canWithContext($user, $permission, $context);

            if ($hasContextual) {
                return [
                    'allowed' => true,
                    'reason' => 'Granted via contextual/scoped permission',
                    'source' => ['type' => 'contextual', 'source' => null, 'via' => null],
                    'policy_decision' => null,
                ];
            }
        }

        // Check ABAC policies
        $parts = explode('.', $permission);
        $action = count($parts) > 1 ? $parts[1] : $permission;
        $resource = count($parts) > 0 ? $parts[0] : '*';

        $policyDecision = $this->policyEngine->evaluate($action, $resource, $context);

        if ($policyDecision === PolicyDecision::Permit) {
            return [
                'allowed' => true,
                'reason' => 'Granted via ABAC policy',
                'source' => ['type' => 'policy', 'source' => null, 'via' => null],
                'policy_decision' => $policyDecision,
            ];
        }

        return [
            'allowed' => false,
            'reason' => 'Permission not found through any authorization mechanism',
            'source' => ['type' => 'none', 'source' => null, 'via' => null],
            'policy_decision' => $policyDecision,
        ];
    }

    /**
     * Test multiple permissions at once.
     *
     * @param  object  $user
     * @param  array<string>  $permissions
     * @param  array<string, mixed>  $context
     * @return array<string, array{allowed: bool, reason: string}>
     */
    public function testBatch($user, array $permissions, array $context = []): array
    {
        $results = [];

        foreach ($permissions as $permission) {
            $result = $this->test($user, $permission, $context);
            $results[$permission] = [
                'allowed' => $result['allowed'],
                'reason' => $result['reason'],
            ];
        }

        return $results;
    }

    /**
     * Test what permissions a user would have if granted a role.
     *
     * @param  object  $user
     * @return array{
     *     current_permissions: array<string>,
     *     new_permissions: array<string>,
     *     removed_permissions: array<string>,
     *     net_change: int
     * }
     */
    public function simulateRoleGrant($user, Role $role): array
    {
        $currentPermissions = $this->aggregator->getEffectivePermissionNames($user)->toArray();
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        $newPermissions = array_diff($rolePermissions, $currentPermissions);
        $allAfter = array_unique(array_merge($currentPermissions, $rolePermissions));

        return [
            'current_permissions' => $currentPermissions,
            'new_permissions' => array_values($newPermissions),
            'removed_permissions' => [],
            'net_change' => count($newPermissions),
        ];
    }

    /**
     * Test what permissions a user would lose if a role is revoked.
     *
     * @param  object  $user
     * @return array{
     *     current_permissions: array<string>,
     *     new_permissions: array<string>,
     *     removed_permissions: array<string>,
     *     net_change: int
     * }
     */
    public function simulateRoleRevoke($user, Role $role): array
    {
        $currentPermissions = $this->aggregator->getEffectivePermissionNames($user)->toArray();
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        // Check which permissions would be lost
        // (only if no other role provides them)
        $otherRoles = $this->aggregator->getEffectiveRoles($user)
            ->filter(fn (Role $r): bool => $r->id !== $role->id);

        $otherPermissions = $otherRoles->flatMap(fn (Role $r) => $r->permissions->pluck('name'))->unique()->toArray();

        $removed = array_diff($rolePermissions, $otherPermissions);
        $remaining = array_diff($currentPermissions, $removed);

        return [
            'current_permissions' => $currentPermissions,
            'new_permissions' => [],
            'removed_permissions' => array_values($removed),
            'net_change' => -count($removed),
        ];
    }

    /**
     * Generate a full permission matrix for a user.
     *
     * @param  object  $user
     * @return array<string, array{has_permission: bool, source: string, inherited: bool}>
     */
    public function generatePermissionMatrix($user): array
    {
        $allPermissions = Permission::all()->pluck('name');
        $matrix = [];

        foreach ($allPermissions as $permission) {
            $result = $this->test($user, $permission);
            $source = $result['source'];

            $matrix[$permission] = [
                'has_permission' => $result['allowed'],
                'source' => $source['source'] ?? $source['type'],
                'inherited' => $source['type'] === 'inherited',
            ];
        }

        return $matrix;
    }

    /**
     * Check for permission conflicts.
     *
     * @param  object  $user
     * @return array<int, array{permission: string, conflict_type: string, details: string}>
     */
    public function detectConflicts($user): array
    {
        $conflicts = [];
        $permissions = $this->aggregator->getEffectivePermissionNames($user);

        // Check for deny policies conflicting with granted permissions
        foreach ($permissions as $permission) {
            $parts = explode('.', $permission);
            $action = count($parts) > 1 ? $parts[1] : $permission;
            $resource = count($parts) > 0 ? $parts[0] : '*';

            $policyDecision = $this->policyEngine->evaluate($action, $resource, []);

            if ($policyDecision === PolicyDecision::Deny) {
                $conflicts[] = [
                    'permission' => $permission,
                    'conflict_type' => 'policy_override',
                    'details' => 'Permission is granted but an ABAC policy denies it',
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Format the reason from source info.
     *
     * @param  array{type: string, source: string|null, via: string|null}  $source
     */
    protected function formatReason(array $source): string
    {
        return match ($source['type']) {
            'direct' => 'Granted directly to user',
            'role' => "Granted via role '{$source['source']}'",
            'inherited' => "Inherited from role '{$source['source']}' via '{$source['via']}'",
            'wildcard' => "Matched wildcard permission '{$source['source']}'",
            'implicit' => "Implied by permission '{$source['source']}'",
            default => 'Unknown source',
        };
    }
}
