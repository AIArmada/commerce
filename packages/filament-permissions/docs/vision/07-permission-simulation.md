# Permission Simulation & Testing

> **Document:** 7 of 10  
> **Package:** `aiarmada/filament-permissions`  
> **Status:** Vision

---

## Overview

Build **permission simulation tools** for testing, debugging, and analyzing access control changes before deployment—including "what if" scenarios and role comparisons.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                 PERMISSION SIMULATION SYSTEM                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                    SIMULATION ENGINE                      │   │
│  │                                                          │   │
│  │   ┌──────────┐  ┌──────────┐  ┌──────────┐              │   │
│  │   │ User     │  │ Role     │  │ Impact   │              │   │
│  │   │ Tester   │  │ Comparer │  │ Analyzer │              │   │
│  │   └──────────┘  └──────────┘  └──────────┘              │   │
│  │                                                          │   │
│  └──────────────────────────────────────────────────────────┘   │
│                            │                                     │
│                            ▼                                     │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                    TEST SCENARIOS                         │   │
│  │                                                          │   │
│  │   • What if user gets role X?                            │   │
│  │   • What if permission Y is revoked?                     │   │
│  │   • What can user access after promotion?                │   │
│  │   • Compare role A vs role B                             │   │
│  │   • Find users affected by permission change             │   │
│  │                                                          │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## User Permission Tester

### PermissionTester

```php
final class PermissionTester
{
    public function __construct(
        private readonly ContextualAuthorizationService $contextualAuth,
        private readonly PolicyEngine $policyEngine,
    ) {}

    /**
     * Test all permissions for a user.
     */
    public function testUser(User $user, ?array $permissions = null): UserPermissionReport
    {
        $permissions ??= Permission::pluck('name')->toArray();

        $results = [];
        foreach ($permissions as $permission) {
            $results[$permission] = $this->testPermission($user, $permission);
        }

        return new UserPermissionReport(
            user: $user,
            results: $results,
            testedAt: now(),
        );
    }

    /**
     * Test a single permission for a user.
     */
    public function testPermission(User $user, string $permission, ?Model $resource = null): PermissionTestResult
    {
        $sources = [];

        // Check direct permission
        if ($user->hasDirectPermission($permission)) {
            $sources[] = new PermissionSource('direct', $permission);
        }

        // Check role permissions
        foreach ($user->roles as $role) {
            if ($role->hasPermissionTo($permission)) {
                $sources[] = new PermissionSource('role', $role->name);
            }
        }

        // Check wildcard permissions
        $wildcardMatch = $this->checkWildcard($user, $permission);
        if ($wildcardMatch) {
            $sources[] = new PermissionSource('wildcard', $wildcardMatch);
        }

        // Check ABAC policies
        $policyDecision = $this->policyEngine->evaluate($user, $permission, $resource);

        return new PermissionTestResult(
            permission: $permission,
            granted: $user->can($permission) || $policyDecision === PolicyDecision::Allow,
            sources: $sources,
            policyDecision: $policyDecision,
        );
    }

    /**
     * Simulate adding a role to user.
     */
    public function simulateRoleAssignment(User $user, Role $role): SimulationResult
    {
        $beforePermissions = $user->getAllPermissions()->pluck('name')->toArray();

        // Clone user and add role
        $simulatedUser = $user->replicate();
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        $afterPermissions = array_unique(array_merge($beforePermissions, $rolePermissions));

        $gained = array_diff($afterPermissions, $beforePermissions);

        return new SimulationResult(
            user: $user,
            scenario: "Add role: {$role->name}",
            before: $beforePermissions,
            after: $afterPermissions,
            gained: array_values($gained),
            lost: [],
        );
    }

    /**
     * Simulate removing a role from user.
     */
    public function simulateRoleRemoval(User $user, Role $role): SimulationResult
    {
        $beforePermissions = $user->getAllPermissions()->pluck('name')->toArray();
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        // Calculate remaining permissions from other roles
        $otherRoles = $user->roles->reject(fn ($r) => $r->id === $role->id);
        $remainingFromRoles = $otherRoles->flatMap(fn ($r) => $r->permissions->pluck('name'))->unique()->toArray();

        // Add direct permissions
        $directPermissions = $user->getDirectPermissions()->pluck('name')->toArray();
        $afterPermissions = array_unique(array_merge($remainingFromRoles, $directPermissions));

        $lost = array_diff($beforePermissions, $afterPermissions);

        return new SimulationResult(
            user: $user,
            scenario: "Remove role: {$role->name}",
            before: $beforePermissions,
            after: $afterPermissions,
            gained: [],
            lost: array_values($lost),
        );
    }

    private function checkWildcard(User $user, string $permission): ?string
    {
        $parts = explode('.', $permission);
        $wildcard = $parts[0].'.*';

        if ($user->hasPermissionTo($wildcard)) {
            return $wildcard;
        }

        return null;
    }
}
```

---

## Data Transfer Objects

### PermissionTestResult

```php
final readonly class PermissionTestResult
{
    /**
     * @param array<PermissionSource> $sources
     */
    public function __construct(
        public string $permission,
        public bool $granted,
        public array $sources,
        public PolicyDecision $policyDecision,
    ) {}

    public function toArray(): array
    {
        return [
            'permission' => $this->permission,
            'granted' => $this->granted,
            'sources' => array_map(fn ($s) => $s->toArray(), $this->sources),
            'policy_decision' => $this->policyDecision->value,
        ];
    }
}

final readonly class PermissionSource
{
    public function __construct(
        public string $type, // direct, role, wildcard, policy
        public string $name,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
        ];
    }
}

final readonly class SimulationResult
{
    public function __construct(
        public User $user,
        public string $scenario,
        public array $before,
        public array $after,
        public array $gained,
        public array $lost,
    ) {}

    public function hasChanges(): bool
    {
        return count($this->gained) > 0 || count($this->lost) > 0;
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->user->id,
            'scenario' => $this->scenario,
            'permissions_before' => count($this->before),
            'permissions_after' => count($this->after),
            'gained' => $this->gained,
            'lost' => $this->lost,
        ];
    }
}
```

---

## Role Comparison

### RoleComparer

```php
final class RoleComparer
{
    /**
     * Compare two roles.
     */
    public function compare(Role $roleA, Role $roleB): RoleComparisonResult
    {
        $permissionsA = $roleA->permissions->pluck('name')->toArray();
        $permissionsB = $roleB->permissions->pluck('name')->toArray();

        return new RoleComparisonResult(
            roleA: $roleA,
            roleB: $roleB,
            onlyInA: array_values(array_diff($permissionsA, $permissionsB)),
            onlyInB: array_values(array_diff($permissionsB, $permissionsA)),
            common: array_values(array_intersect($permissionsA, $permissionsB)),
        );
    }

    /**
     * Find the minimal role for a user's actual usage.
     */
    public function findMinimalRole(User $user, Collection $auditLogs): RoleSuggestion
    {
        $usedPermissions = $auditLogs
            ->where('event_type', AuditEventType::AccessGranted)
            ->pluck('context.permission')
            ->unique()
            ->filter()
            ->toArray();

        $currentPermissions = $user->getAllPermissions()->pluck('name')->toArray();
        $unusedPermissions = array_diff($currentPermissions, $usedPermissions);

        // Find best matching role
        $roles = Role::with('permissions')->get();
        $bestMatch = null;
        $bestScore = 0;

        foreach ($roles as $role) {
            $rolePermissions = $role->permissions->pluck('name')->toArray();
            $coversUsed = count(array_intersect($rolePermissions, $usedPermissions));
            $excess = count(array_diff($rolePermissions, $usedPermissions));

            $score = $coversUsed - ($excess * 0.5);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $role;
            }
        }

        return new RoleSuggestion(
            user: $user,
            suggestedRole: $bestMatch,
            usedPermissions: $usedPermissions,
            unusedPermissions: array_values($unusedPermissions),
            coverage: $bestMatch 
                ? count(array_intersect($bestMatch->permissions->pluck('name')->toArray(), $usedPermissions)) / max(count($usedPermissions), 1)
                : 0,
        );
    }
}

final readonly class RoleComparisonResult
{
    public function __construct(
        public Role $roleA,
        public Role $roleB,
        public array $onlyInA,
        public array $onlyInB,
        public array $common,
    ) {}

    public function areIdentical(): bool
    {
        return empty($this->onlyInA) && empty($this->onlyInB);
    }

    public function isASubsetOfB(): bool
    {
        return empty($this->onlyInA);
    }

    public function toArray(): array
    {
        return [
            'role_a' => $this->roleA->name,
            'role_b' => $this->roleB->name,
            'only_in_a' => $this->onlyInA,
            'only_in_b' => $this->onlyInB,
            'common' => $this->common,
            'identical' => $this->areIdentical(),
        ];
    }
}
```

---

## Impact Analyzer

### PermissionImpactAnalyzer

```php
final class PermissionImpactAnalyzer
{
    /**
     * Analyze impact of revoking a permission.
     */
    public function analyzePermissionRevocation(Permission $permission): PermissionImpactReport
    {
        $affectedUsers = User::permission($permission->name)->get();
        $affectedRoles = Role::whereHas('permissions', fn ($q) => $q->where('id', $permission->id))->get();

        // Check dependencies (e.g., orders.view requires orders.viewAny)
        $dependentPermissions = $this->findDependentPermissions($permission);

        return new PermissionImpactReport(
            permission: $permission,
            affectedUsers: $affectedUsers,
            affectedRoles: $affectedRoles,
            dependentPermissions: $dependentPermissions,
            impactLevel: $this->calculateImpactLevel($affectedUsers->count()),
        );
    }

    /**
     * Analyze impact of deleting a role.
     */
    public function analyzeRoleDeletion(Role $role): RoleImpactReport
    {
        $affectedUsers = $role->users;

        // Calculate what permissions users will lose
        $rolePermissions = $role->permissions->pluck('name');

        $userImpacts = $affectedUsers->map(function ($user) use ($rolePermissions, $role) {
            $otherRoles = $user->roles->reject(fn ($r) => $r->id === $role->id);
            $remainingPermissions = $otherRoles->flatMap(fn ($r) => $r->permissions->pluck('name'))->unique();

            $lostPermissions = $rolePermissions->diff($remainingPermissions);

            return [
                'user' => $user,
                'lost_permissions' => $lostPermissions->toArray(),
            ];
        });

        return new RoleImpactReport(
            role: $role,
            affectedUsers: $affectedUsers,
            userImpacts: $userImpacts->toArray(),
            totalPermissionsInRole: $rolePermissions->count(),
        );
    }

    /**
     * Find all permissions that depend on this one.
     */
    private function findDependentPermissions(Permission $permission): array
    {
        $name = $permission->name;
        [$domain, $action] = explode('.', $name) + [null, null];

        if ($action === 'viewAny') {
            // view, update, delete typically require viewAny
            return Permission::query()
                ->where('name', 'like', "{$domain}.%")
                ->whereIn('name', ["{$domain}.view", "{$domain}.update", "{$domain}.delete"])
                ->pluck('name')
                ->toArray();
        }

        return [];
    }

    private function calculateImpactLevel(int $affectedCount): ImpactLevel
    {
        return match (true) {
            $affectedCount === 0 => ImpactLevel::None,
            $affectedCount <= 5 => ImpactLevel::Low,
            $affectedCount <= 20 => ImpactLevel::Medium,
            $affectedCount <= 100 => ImpactLevel::High,
            default => ImpactLevel::Critical,
        };
    }
}

enum ImpactLevel: string
{
    case None = 'none';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}

final readonly class PermissionImpactReport
{
    public function __construct(
        public Permission $permission,
        public Collection $affectedUsers,
        public Collection $affectedRoles,
        public array $dependentPermissions,
        public ImpactLevel $impactLevel,
    ) {}

    public function toArray(): array
    {
        return [
            'permission' => $this->permission->name,
            'affected_users_count' => $this->affectedUsers->count(),
            'affected_roles' => $this->affectedRoles->pluck('name')->toArray(),
            'dependent_permissions' => $this->dependentPermissions,
            'impact_level' => $this->impactLevel->value,
        ];
    }
}
```

---

## CLI Testing Commands

### TestUserPermissionsCommand

```php
#[AsCommand(name: 'permissions:test-user')]
final class TestUserPermissionsCommand extends Command
{
    protected $signature = 'permissions:test-user 
        {user : User ID or email}
        {--permission= : Test specific permission}
        {--format=table : Output format (table, json)}';

    public function handle(PermissionTester $tester): int
    {
        $user = $this->resolveUser($this->argument('user'));

        if (! $user) {
            $this->error('User not found.');
            return self::FAILURE;
        }

        if ($permission = $this->option('permission')) {
            $result = $tester->testPermission($user, $permission);
            $this->displayResult($result);
        } else {
            $report = $tester->testUser($user);
            $this->displayReport($report);
        }

        return self::SUCCESS;
    }

    private function displayResult(PermissionTestResult $result): void
    {
        $this->info("Permission: {$result->permission}");
        $this->info("Granted: ".($result->granted ? 'Yes' : 'No'));
        $this->info("Policy Decision: {$result->policyDecision->value}");

        if (! empty($result->sources)) {
            $this->info('Sources:');
            foreach ($result->sources as $source) {
                $this->line("  - {$source->type}: {$source->name}");
            }
        }
    }

    private function displayReport(UserPermissionReport $report): void
    {
        $rows = [];
        foreach ($report->results as $permission => $result) {
            $rows[] = [
                $permission,
                $result->granted ? '✓' : '✗',
                implode(', ', array_map(fn ($s) => $s->name, $result->sources)),
            ];
        }

        $this->table(['Permission', 'Granted', 'Sources'], $rows);
    }

    private function resolveUser(string $identifier): ?User
    {
        if (Str::isUuid($identifier)) {
            return User::find($identifier);
        }

        return User::where('email', $identifier)->first();
    }
}
```

### SimulateRoleChangeCommand

```php
#[AsCommand(name: 'permissions:simulate')]
final class SimulateRoleChangeCommand extends Command
{
    protected $signature = 'permissions:simulate 
        {user : User ID or email}
        {--add-role= : Role to add}
        {--remove-role= : Role to remove}';

    public function handle(PermissionTester $tester): int
    {
        $user = $this->resolveUser($this->argument('user'));

        if ($addRole = $this->option('add-role')) {
            $role = Role::findByName($addRole);
            $result = $tester->simulateRoleAssignment($user, $role);
        } elseif ($removeRole = $this->option('remove-role')) {
            $role = Role::findByName($removeRole);
            $result = $tester->simulateRoleRemoval($user, $role);
        } else {
            $this->error('Specify --add-role or --remove-role');
            return self::FAILURE;
        }

        $this->info("Scenario: {$result->scenario}");
        $this->info("Permissions before: ".count($result->before));
        $this->info("Permissions after: ".count($result->after));

        if (! empty($result->gained)) {
            $this->info('Gained:');
            foreach ($result->gained as $perm) {
                $this->line("  + {$perm}");
            }
        }

        if (! empty($result->lost)) {
            $this->warn('Lost:');
            foreach ($result->lost as $perm) {
                $this->line("  - {$perm}");
            }
        }

        return self::SUCCESS;
    }
}
```

---

## Navigation

**Previous:** [06-policy-evolution.md](06-policy-evolution.md)  
**Next:** [08-database-evolution.md](08-database-evolution.md)
