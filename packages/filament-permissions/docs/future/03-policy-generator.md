# Future: Advanced Policy Generator

> **Intelligent Laravel policy generation with ABAC integration**

## Overview

Shield generates basic Laravel policies from stubs. Our generator goes further: policies that understand hierarchies, temporal permissions, and ABAC conditions.

## Shield's Approach Analysis

Shield uses stub-based policy generation:

```php
// Shield's policy stubs
AuthenticatablePolicy.stub  // For User models
DefaultPolicy.stub          // For regular models
MultiParamMethod.stub       // Methods with $model param
SingleParamMethod.stub      // Methods without $model param (viewAny, create)
```

**Example Shield-generated policy method:**
```php
public function update(User $user, Post $post): bool
{
    return $user->can('Update:Post');
}
```

## Our Enhanced Implementation

### 1. Policy Types

```php
namespace AIArmada\FilamentPermissions\Enums;

enum PolicyType: string
{
    case Basic = 'basic';           // Simple permission check
    case Hierarchical = 'hierarchical';  // Uses permission groups
    case Temporal = 'temporal';      // Time-based permissions
    case Contextual = 'contextual';  // Team/tenant aware
    case Abac = 'abac';              // Full ABAC evaluation
    case Composite = 'composite';     // All of the above
}
```

### 2. Policy Stubs

#### Basic Policy (Shield-compatible)
```php
// stubs/policies/basic.stub
<?php

namespace {{ namespace }};

use {{ userModel }};
use {{ model }};
use Illuminate\Auth\Access\HandlesAuthorization;

class {{ class }}
{
    use HandlesAuthorization;

{{ methods }}
}
```

#### Hierarchical Policy
```php
// stubs/policies/hierarchical.stub
<?php

namespace {{ namespace }};

use {{ userModel }};
use {{ model }};
use AIArmada\FilamentPermissions\Facades\Permissions;
use Illuminate\Auth\Access\HandlesAuthorization;

class {{ class }}
{
    use HandlesAuthorization;

    /**
     * Check permission with group hierarchy expansion.
     */
    protected function checkWithHierarchy({{ user }} $user, string $ability): bool
    {
        return Permissions::aggregator()->checkPermission($user, $ability);
    }

{{ methods }}
}
```

#### ABAC Policy
```php
// stubs/policies/abac.stub
<?php

namespace {{ namespace }};

use {{ userModel }};
use {{ model }};
use AIArmada\FilamentPermissions\Facades\Permissions;
use AIArmada\FilamentPermissions\Services\PolicyEngine;
use Illuminate\Auth\Access\HandlesAuthorization;

class {{ class }}
{
    use HandlesAuthorization;

    protected PolicyEngine $policyEngine;

    public function __construct(PolicyEngine $policyEngine)
    {
        $this->policyEngine = $policyEngine;
    }

    /**
     * Evaluate ABAC policy with full context.
     */
    protected function evaluatePolicy({{ user }} $user, string $action, ?{{ modelVariable }} ${{ modelVariable }} = null): bool
    {
        $context = [
            'user' => [
                'id' => $user->id,
                'roles' => $user->getRoleNames()->toArray(),
                'teams' => $user->teams?->pluck('id')->toArray() ?? [],
                'department' => $user->department,
                'created_at' => $user->created_at,
            ],
            'resource' => ${{ modelVariable }} ? [
                'id' => ${{ modelVariable }}->id,
                'owner_id' => ${{ modelVariable }}->user_id ?? ${{ modelVariable }}->owner_id ?? null,
                'team_id' => ${{ modelVariable }}->team_id ?? null,
                'status' => ${{ modelVariable }}->status ?? null,
                'created_at' => ${{ modelVariable }}->created_at ?? null,
            ] : null,
            'environment' => [
                'ip' => request()->ip(),
                'time' => now(),
                'day_of_week' => now()->dayOfWeek,
            ],
        ];

        return $this->policyEngine->evaluate("{{ modelName }}.{$action}", $context)->isPermitted();
    }

{{ methods }}
}
```

#### Contextual Policy
```php
// stubs/policies/contextual.stub
<?php

namespace {{ namespace }};

use {{ userModel }};
use {{ model }};
use AIArmada\FilamentPermissions\Facades\Permissions;
use Illuminate\Auth\Access\HandlesAuthorization;

class {{ class }}
{
    use HandlesAuthorization;

    /**
     * Check permission with team/tenant context.
     */
    protected function checkInContext({{ user }} $user, string $ability, ?{{ modelVariable }} ${{ modelVariable }} = null): bool
    {
        // Check team context
        if (${{ modelVariable }}?->team_id) {
            return Permissions::team(${{ modelVariable }}->team_id)
                ->checkPermission($user, $ability);
        }

        // Check tenant context
        if ($tenant = filament()->getTenant()) {
            return Permissions::tenant($tenant)
                ->checkPermission($user, $ability);
        }

        // Fallback to global check with hierarchy
        return Permissions::aggregator()->checkPermission($user, $ability);
    }

    /**
     * Check if user owns the resource.
     */
    protected function isOwner({{ user }} $user, {{ modelVariable }} ${{ modelVariable }}): bool
    {
        $ownerColumn = ${{ modelVariable }}->getOwnerColumn() ?? 'user_id';
        return ${{ modelVariable }}->{$ownerColumn} === $user->id;
    }

{{ methods }}
}
```

### 3. Method Stubs

```php
// stubs/methods/basic_single.stub
    public function {{ method }}({{ user }} $user): bool
    {
        return $user->can('{{ permission }}');
    }

// stubs/methods/basic_multi.stub
    public function {{ method }}({{ user }} $user, {{ model }} ${{ modelVariable }}): bool
    {
        return $user->can('{{ permission }}');
    }

// stubs/methods/hierarchical_single.stub
    public function {{ method }}({{ user }} $user): bool
    {
        return $this->checkWithHierarchy($user, '{{ permission }}');
    }

// stubs/methods/owner_aware.stub
    public function {{ method }}({{ user }} $user, {{ model }} ${{ modelVariable }}): bool
    {
        // Owner can always {{ methodDescription }}
        if ($this->isOwner($user, ${{ modelVariable }})) {
            return true;
        }

        return $this->checkInContext($user, '{{ permission }}', ${{ modelVariable }});
    }

// stubs/methods/temporal.stub
    public function {{ method }}({{ user }} $user{{ modelParam }}): bool
    {
        // Check for active temporal permission
        if (Permissions::temporal()->hasActiveGrant($user, '{{ permission }}')) {
            return true;
        }

        return $this->checkInContext($user, '{{ permission }}'{{ contextParam }});
    }

// stubs/methods/abac.stub
    public function {{ method }}({{ user }} $user{{ modelParam }}): bool
    {
        return $this->evaluatePolicy($user, '{{ action }}'{{ contextParam }});
    }
```

### 4. Generator Service

```php
namespace AIArmada\FilamentPermissions\Services;

class PolicyGeneratorService
{
    protected array $stubs = [];
    
    public function generate(PolicyGenerationRequest $request): GeneratedPolicy
    {
        $stub = $this->getStub($request->type);
        
        $content = $this->populateStub($stub, [
            'namespace' => $request->namespace,
            'class' => $request->className,
            'userModel' => $request->userModel,
            'user' => class_basename($request->userModel),
            'model' => $request->modelClass,
            'modelVariable' => Str::camel(class_basename($request->modelClass)),
            'modelName' => class_basename($request->modelClass),
            'methods' => $this->generateMethods($request),
        ]);
        
        return new GeneratedPolicy(
            path: $request->targetPath,
            content: $content,
            metadata: [
                'type' => $request->type->value,
                'model' => $request->modelClass,
                'permissions' => $request->permissions,
            ]
        );
    }
    
    protected function generateMethods(PolicyGenerationRequest $request): string
    {
        $methods = [];
        
        foreach ($request->methods as $method => $config) {
            $methods[] = $this->generateMethod($method, $config, $request);
        }
        
        return implode("\n\n", $methods);
    }
    
    protected function generateMethod(string $method, array $config, PolicyGenerationRequest $request): string
    {
        $isSingleParam = in_array($method, ['viewAny', 'create', 'deleteAny', 'forceDeleteAny', 'restoreAny', 'reorder']);
        
        $stubName = match ($request->type) {
            PolicyType::Basic => $isSingleParam ? 'basic_single' : 'basic_multi',
            PolicyType::Hierarchical => 'hierarchical_single',
            PolicyType::Contextual => 'owner_aware',
            PolicyType::Temporal => 'temporal',
            PolicyType::Abac => 'abac',
            PolicyType::Composite => 'abac', // Most comprehensive
        };
        
        $stub = $this->getMethodStub($stubName);
        
        return $this->populateStub($stub, [
            'method' => $method,
            'permission' => $config['permission'] ?? "{$request->permissionPrefix}.{$method}",
            'action' => $method,
            'user' => class_basename($request->userModel),
            'model' => class_basename($request->modelClass),
            'modelVariable' => Str::camel(class_basename($request->modelClass)),
            'modelParam' => $isSingleParam ? '' : ", {$request->modelClass} \$" . Str::camel(class_basename($request->modelClass)),
            'contextParam' => $isSingleParam ? '' : ", \$" . Str::camel(class_basename($request->modelClass)),
            'methodDescription' => $this->getMethodDescription($method),
        ]);
    }
}
```

### 5. CLI Command

```bash
# Generate basic policies
php artisan permissions:policies

# Generate specific type
php artisan permissions:policies --type=abac

# Generate for specific resources
php artisan permissions:policies --resource=Post,Comment

# Interactive mode
php artisan permissions:policies --interactive

# Force overwrite existing
php artisan permissions:policies --force

# Preview without writing
php artisan permissions:policies --dry-run

# With custom namespace
php artisan permissions:policies --namespace=App\\Policies\\Admin
```

```php
#[AsCommand(name: 'permissions:policies')]
class GeneratePoliciesCommand extends Command
{
    public $signature = 'permissions:policies
        {--type= : Policy type (basic, hierarchical, temporal, contextual, abac, composite)}
        {--resource=* : Specific resources to generate for}
        {--panel= : Panel to discover resources from}
        {--namespace= : Custom policy namespace}
        {--force : Overwrite existing policies}
        {--dry-run : Preview without writing}
        {--interactive : Interactive mode}';
    
    public function handle(
        EntityDiscoveryService $discovery,
        PolicyGeneratorService $generator
    ): int {
        $type = $this->option('type')
            ? PolicyType::from($this->option('type'))
            : $this->askPolicyType();
        
        $resources = $this->getResources($discovery);
        
        $this->info("🔧 Generating {$type->value} policies for " . count($resources) . " resources...\n");
        
        $bar = $this->output->createProgressBar(count($resources));
        
        foreach ($resources as $resource) {
            $request = $this->buildRequest($resource, $type);
            $policy = $generator->generate($request);
            
            if (!$this->option('dry-run')) {
                $this->writePolicy($policy);
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        $this->info("✅ Generated " . count($resources) . " policies");
        
        return Command::SUCCESS;
    }
    
    protected function askPolicyType(): PolicyType
    {
        return PolicyType::from(select(
            label: 'What type of policies do you want to generate?',
            options: [
                'basic' => 'Basic — Simple permission checks (Shield compatible)',
                'hierarchical' => 'Hierarchical — Uses permission group inheritance',
                'contextual' => 'Contextual — Team/tenant/owner aware',
                'temporal' => 'Temporal — Includes time-based permission checks',
                'abac' => 'ABAC — Full attribute-based evaluation',
                'composite' => 'Composite — All features combined (recommended)',
            ],
            default: 'composite'
        ));
    }
}
```

### 6. Configuration

```php
// config/filament-permissions.php
return [
    'policies' => [
        'enabled' => true,
        
        // Default policy type
        'default_type' => 'composite',
        
        // Policy path
        'path' => app_path('Policies'),
        
        // Methods to generate
        'methods' => [
            'viewAny',
            'view', 
            'create',
            'update',
            'delete',
            'restore',
            'forceDelete',
            'forceDeleteAny',
            'restoreAny',
            'replicate',
            'reorder',
        ],
        
        // Single-param methods (no model instance)
        'single_param_methods' => [
            'viewAny',
            'create',
            'deleteAny',
            'forceDeleteAny',
            'restoreAny',
            'reorder',
        ],
        
        // Merge with resource-specific methods
        'merge_resource_methods' => true,
        
        // Custom stubs path
        'stubs_path' => null,
        
        // Include doc blocks
        'include_docblocks' => true,
        
        // Owner-aware methods (check ownership before permission)
        'owner_aware_methods' => [
            'view',
            'update',
            'delete',
        ],
    ],
];
```

### 7. Generated Policy Example

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Post;
use AIArmada\FilamentPermissions\Facades\Permissions;
use AIArmada\FilamentPermissions\Services\PolicyEngine;
use Illuminate\Auth\Access\HandlesAuthorization;

class PostPolicy
{
    use HandlesAuthorization;

    protected PolicyEngine $policyEngine;

    public function __construct(PolicyEngine $policyEngine)
    {
        $this->policyEngine = $policyEngine;
    }

    /**
     * Determine if the user can view any posts.
     */
    public function viewAny(User $user): bool
    {
        return $this->evaluatePolicy($user, 'viewAny');
    }

    /**
     * Determine if the user can view the post.
     */
    public function view(User $user, Post $post): bool
    {
        // Owner can always view their own posts
        if ($this->isOwner($user, $post)) {
            return true;
        }

        return $this->evaluatePolicy($user, 'view', $post);
    }

    /**
     * Determine if the user can create posts.
     */
    public function create(User $user): bool
    {
        return $this->evaluatePolicy($user, 'create');
    }

    /**
     * Determine if the user can update the post.
     */
    public function update(User $user, Post $post): bool
    {
        // Owner can always update their own posts
        if ($this->isOwner($user, $post)) {
            return true;
        }

        return $this->evaluatePolicy($user, 'update', $post);
    }

    /**
     * Determine if the user can delete the post.
     */
    public function delete(User $user, Post $post): bool
    {
        // Owner can always delete their own posts
        if ($this->isOwner($user, $post)) {
            return true;
        }

        return $this->evaluatePolicy($user, 'delete', $post);
    }

    // ... more methods

    /**
     * Evaluate ABAC policy with full context.
     */
    protected function evaluatePolicy(User $user, string $action, ?Post $post = null): bool
    {
        $context = [
            'user' => [
                'id' => $user->id,
                'roles' => $user->getRoleNames()->toArray(),
                'teams' => $user->teams?->pluck('id')->toArray() ?? [],
                'department' => $user->department,
            ],
            'resource' => $post ? [
                'id' => $post->id,
                'owner_id' => $post->user_id,
                'status' => $post->status,
                'is_published' => $post->published_at !== null,
            ] : null,
            'environment' => [
                'ip' => request()->ip(),
                'time' => now(),
            ],
        ];

        return $this->policyEngine->evaluate("post.{$action}", $context)->isPermitted();
    }

    /**
     * Check if user owns the resource.
     */
    protected function isOwner(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }
}
```

## Comparison with Shield

| Feature | Shield | Our Generator |
|---------|--------|---------------|
| **Stub-based** | ✅ | ✅ |
| **Policy types** | 2 (Authenticatable, Default) | 6 types |
| **ABAC integration** | ❌ | ✅ Full integration |
| **Owner awareness** | ❌ | ✅ Auto-generated |
| **Temporal support** | ❌ | ✅ |
| **Team/tenant context** | ❌ | ✅ |
| **Custom stubs** | ✅ | ✅ with more options |
| **Interactive mode** | ❌ | ✅ |
| **Dry-run preview** | ❌ | ✅ |
| **Doc blocks** | ❌ | ✅ Optional |
