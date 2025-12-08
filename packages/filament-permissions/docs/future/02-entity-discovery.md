# Future: Advanced Entity Discovery Engine

> **Automatic discovery and permission generation for all Filament entities**

## Overview

Inspired by Shield's entity discovery but significantly enhanced with filtering, caching, and advanced permission mapping.

## Analysis of Shield's Approach

Shield uses `HasEntityDiscovery` trait to discover:

```php
// Shield's discovery pattern
public function discoverResources(): Collection
{
    return Utils::getConfig()->discovery->discover_all_resources
        ? collect(Filament::getPanels())->flatMap(fn ($panel) => $panel->getResources())->unique()
        : collect(Filament::getResources());
}
```

**Limitations in Shield:**
- No filtering beyond exclusions
- No caching of discovered entities
- No namespace awareness
- Limited metadata extraction

## Our Enhanced Implementation

### 1. Entity Discovery Service

```php
namespace AIArmada\FilamentPermissions\Services;

class EntityDiscoveryService
{
    protected array $resourceCache = [];
    protected array $pageCache = [];
    protected array $widgetCache = [];
    
    /**
     * Discover all resources with advanced filtering
     */
    public function discoverResources(array $options = []): Collection
    {
        $cacheKey = 'fp_resources_' . md5(json_encode($options));
        
        return Cache::remember($cacheKey, $this->getCacheTtl(), function () use ($options) {
            $resources = $this->collectResources($options);
            
            return $resources->map(fn ($resource) => $this->transformResource($resource))
                ->filter(fn ($resource) => $this->shouldInclude($resource, $options));
        });
    }
    
    protected function transformResource(string $resourceClass): array
    {
        $resource = resolve($resourceClass);
        
        return [
            'fqcn' => $resourceClass,
            'model' => $resource::getModel(),
            'modelFqcn' => $resource::getModel(),
            'modelBasename' => class_basename($resource::getModel()),
            'navigationGroup' => $resource::getNavigationGroup(),
            'navigationLabel' => $resource::getNavigationLabel(),
            'slug' => $resource::getSlug(),
            'panel' => $this->detectPanel($resourceClass),
            'cluster' => $resource::getCluster(),
            'permissions' => $this->resolveResourcePermissions($resource),
            'existingPolicy' => $this->detectExistingPolicy($resource::getModel()),
            'hasRelations' => $this->detectRelationManagers($resource),
            'hasBulkActions' => $this->detectBulkActions($resource),
            'metadata' => $this->extractMetadata($resource),
        ];
    }
    
    /**
     * Resolve intelligent permissions based on resource capabilities
     */
    protected function resolveResourcePermissions($resource): array
    {
        $base = $this->getBasePermissions();
        
        // Add bulk action permissions if present
        if ($this->detectBulkActions($resource)) {
            $base = array_merge($base, ['bulkDelete', 'bulkUpdate', 'bulkExport']);
        }
        
        // Add relation permissions
        foreach ($this->detectRelationManagers($resource) as $relation) {
            $base[] = "manage{$relation}";
        }
        
        // Add custom actions
        foreach ($this->detectCustomActions($resource) as $action) {
            $base[] = $action;
        }
        
        return $base;
    }
}
```

### 2. Enhanced Configuration

```php
// config/filament-permissions.php
return [
    'discovery' => [
        'enabled' => true,
        
        // Multi-panel discovery
        'discover_all_panels' => false,
        'panels' => ['admin'], // Specific panels to discover
        
        // Entity types to discover
        'entities' => [
            'resources' => true,
            'pages' => true,
            'widgets' => true,
            'clusters' => true,        // NEW: Cluster discovery
            'relation_managers' => true, // NEW: Relation manager discovery
            'actions' => true,          // NEW: Custom action discovery
        ],
        
        // Namespace filtering
        'namespaces' => [
            'include' => [
                'App\\Filament\\*',
            ],
            'exclude' => [
                'App\\Filament\\Testing\\*',
            ],
        ],
        
        // Pattern-based exclusions
        'exclude_patterns' => [
            '*TestResource',
            '*DebugWidget',
        ],
        
        // Explicit exclusions
        'exclude' => [
            \Filament\Pages\Dashboard::class,
            \Filament\Widgets\AccountWidget::class,
        ],
        
        // Caching
        'cache' => [
            'enabled' => true,
            'ttl' => 3600,
            'warmup_on_boot' => false,
        ],
    ],
];
```

### 3. Entity Transformers

```php
namespace AIArmada\FilamentPermissions\Services\Discovery;

class ResourceTransformer
{
    public function transform(string $resourceClass): DiscoveredResource
    {
        return new DiscoveredResource(
            fqcn: $resourceClass,
            model: $this->extractModel($resourceClass),
            permissions: $this->generatePermissions($resourceClass),
            metadata: $this->extractMetadata($resourceClass),
        );
    }
    
    protected function generatePermissions(string $resourceClass): array
    {
        $resource = resolve($resourceClass);
        $permissions = [];
        
        // Standard CRUD
        $permissions = array_merge($permissions, [
            'viewAny', 'view', 'create', 'update', 'delete',
            'restore', 'forceDelete',
        ]);
        
        // Detect table bulk actions
        foreach ($resource::getTableBulkActions() as $action) {
            $permissions[] = 'bulk' . Str::studly($action->getName());
        }
        
        // Detect header/table actions
        foreach ($resource::getTableActions() as $action) {
            if ($this->isCustomAction($action)) {
                $permissions[] = Str::camel($action->getName());
            }
        }
        
        // Detect relation managers
        foreach ($resource::getRelations() as $relation) {
            $relationName = Str::studly(class_basename($relation));
            $permissions[] = "view{$relationName}";
            $permissions[] = "create{$relationName}";
            $permissions[] = "update{$relationName}";
            $permissions[] = "delete{$relationName}";
        }
        
        return array_unique($permissions);
    }
}

class PageTransformer
{
    public function transform(string $pageClass): DiscoveredPage
    {
        $page = resolve($pageClass);
        
        return new DiscoveredPage(
            fqcn: $pageClass,
            title: $page::getTitle(),
            slug: $page::getSlug(),
            cluster: $page::getCluster(),
            permissions: ['view' . class_basename($pageClass)],
            metadata: [
                'hasForm' => method_exists($page, 'form'),
                'hasTable' => method_exists($page, 'table'),
                'isWizard' => is_subclass_of($page, Wizard::class),
            ],
        );
    }
}

class WidgetTransformer
{
    public function transform(string $widgetClass): DiscoveredWidget
    {
        return new DiscoveredWidget(
            fqcn: $widgetClass,
            name: class_basename($widgetClass),
            type: $this->detectWidgetType($widgetClass),
            permissions: ['view' . class_basename($widgetClass)],
            metadata: [
                'isChart' => is_subclass_of($widgetClass, ChartWidget::class),
                'isStats' => is_subclass_of($widgetClass, StatsOverviewWidget::class),
                'isLivewire' => is_subclass_of($widgetClass, Widget::class),
            ],
        );
    }
}
```

### 4. Value Objects

```php
namespace AIArmada\FilamentPermissions\ValueObjects;

readonly class DiscoveredResource
{
    public function __construct(
        public string $fqcn,
        public string $model,
        public array $permissions,
        public array $metadata,
    ) {}
    
    public function toPermissionKeys(string $separator = '.'): array
    {
        $subject = Str::snake(class_basename($this->model));
        
        return collect($this->permissions)
            ->map(fn ($perm) => "{$subject}{$separator}{$perm}")
            ->toArray();
    }
    
    public function hasExistingPolicy(): bool
    {
        return class_exists($this->getPolicyClass());
    }
    
    public function getPolicyClass(): string
    {
        return Str::of($this->model)
            ->replace('Models', 'Policies')
            ->append('Policy')
            ->toString();
    }
}
```

### 5. CLI Commands

```bash
# Discover and display all entities
php artisan permissions:discover

# Discover with options
php artisan permissions:discover --panel=admin --type=resources

# Discover and generate permissions
php artisan permissions:discover --generate

# Discover and output as JSON
php artisan permissions:discover --format=json > entities.json

# Warm up discovery cache
php artisan permissions:discover:warmup

# Clear discovery cache
php artisan permissions:discover:clear
```

```php
#[AsCommand(name: 'permissions:discover')]
class DiscoverCommand extends Command
{
    public function handle(EntityDiscoveryService $discovery): int
    {
        $this->info("🔍 Discovering Filament Entities...\n");
        
        $resources = $discovery->discoverResources();
        $pages = $discovery->discoverPages();
        $widgets = $discovery->discoverWidgets();
        
        $this->displayTable('Resources', $resources);
        $this->displayTable('Pages', $pages);
        $this->displayTable('Widgets', $widgets);
        
        $this->newLine();
        $this->info('📊 Summary:');
        $this->table(['Entity Type', 'Count', 'Permissions'], [
            ['Resources', $resources->count(), $resources->sum(fn ($r) => count($r['permissions']))],
            ['Pages', $pages->count(), $pages->count()],
            ['Widgets', $widgets->count(), $widgets->count()],
        ]);
        
        if ($this->option('generate')) {
            $this->generatePermissions($resources, $pages, $widgets);
        }
        
        return Command::SUCCESS;
    }
}
```

### 6. Integration with Permission Registry

```php
class PermissionRegistry
{
    public function syncFromDiscovery(): void
    {
        $discovery = app(EntityDiscoveryService::class);
        
        // Sync resources
        foreach ($discovery->discoverResources() as $resource) {
            foreach ($resource['permissions'] as $permission) {
                $this->register(
                    name: "{$resource['modelBasename']}.{$permission}",
                    group: $resource['navigationGroup'] ?? 'Resources',
                    metadata: [
                        'source' => 'discovery',
                        'entity' => $resource['fqcn'],
                        'description' => $this->generateDescription($resource, $permission),
                    ]
                );
            }
        }
        
        // Sync pages
        foreach ($discovery->discoverPages() as $page) {
            $this->register(
                name: "page.{$page['slug']}",
                group: 'Pages',
                metadata: ['source' => 'discovery', 'entity' => $page['fqcn']]
            );
        }
        
        // Sync widgets
        foreach ($discovery->discoverWidgets() as $widget) {
            $this->register(
                name: "widget.{$widget['name']}",
                group: 'Widgets',
                metadata: ['source' => 'discovery', 'entity' => $widget['fqcn']]
            );
        }
    }
}
```

## Differences from Shield

| Aspect | Shield | Our Enhancement |
|--------|--------|-----------------|
| **Caching** | None | Redis/cache-backed discovery |
| **Metadata** | Basic (model, class) | Rich (relations, actions, clusters) |
| **Filtering** | Exclusion list only | Namespace patterns, wildcards |
| **Permission Generation** | Fixed abilities | Dynamic based on resource capabilities |
| **Relation Managers** | Not included | Full discovery with permissions |
| **Custom Actions** | Not included | Auto-detected and permissioned |
| **Clusters** | Not included | Cluster-aware discovery |

## API Reference

```php
// Facade usage
FilamentPermissions::discoverResources();
FilamentPermissions::discoverPages();
FilamentPermissions::discoverWidgets();
FilamentPermissions::discoverAll();

// Service usage
$discovery = app(EntityDiscoveryService::class);
$discovery->discoverResources(['panel' => 'admin']);
$discovery->warmCache();
$discovery->clearCache();
$discovery->getDiscoveredPermissions();
```

## Implementation Priority

- **Phase 1**: Basic discovery matching Shield
- **Phase 2**: Caching and metadata enrichment
- **Phase 3**: Custom action/relation detection
- **Phase 4**: Full CLI tooling
