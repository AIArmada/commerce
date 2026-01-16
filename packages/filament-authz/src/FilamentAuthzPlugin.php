<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz;

use AIArmada\FilamentAuthz\Resources\PermissionResource;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;

/**
 * Filament Authz Plugin with comprehensive fluent API.
 *
 * Features:
 * - Multi-panel support with per-panel configuration
 * - Tenant-scoped permissions (optional)
 * - Cleaner API design
 * - No trait dependencies for config
 */
class FilamentAuthzPlugin implements Plugin
{
    use EvaluatesClosures;

    protected ?Panel $panel = null;

    protected bool | Closure $registerRoleResource = true;

    protected bool | Closure $registerPermissionResource = true;

    protected string | Closure | null $navigationGroup = null;

    protected string | Closure | null $navigationIcon = null;

    protected int | Closure | null $navigationSort = null;

    protected bool | Closure $registerNavigation = true;

    /** @var list<class-string> | Closure | null */
    protected array | Closure | null $excludeResources = null;

    /** @var list<class-string> | Closure | null */
    protected array | Closure | null $excludePages = null;

    /** @var list<class-string> | Closure | null */
    protected array | Closure | null $excludeWidgets = null;

    protected int | Closure $gridColumns = 2;

    protected int | Closure $checkboxColumns = 3;

    protected bool | Closure $resourcesTab = true;

    protected bool | Closure $pagesTab = true;

    protected bool | Closure $widgetsTab = true;

    protected bool | Closure $customPermissionsTab = true;

    protected string | Closure | null $permissionCase = null;

    protected string | Closure | null $permissionSeparator = null;

    protected bool | Closure $scopedToTenant = false;

    protected string | Closure | null $tenantOwnershipRelationship = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'aiarmada-filament-authz';
    }

    public function register(Panel $panel): void
    {
        $this->panel = $panel;

        $resources = [];

        if ($this->evaluate($this->registerRoleResource)) {
            $resources[] = RoleResource::class;
        }

        if ($this->evaluate($this->registerPermissionResource)) {
            $resources[] = PermissionResource::class;
        }

        if ($resources !== []) {
            $panel->resources($resources);
        }

        $this->applyConfigOverrides($panel);
    }

    public function boot(Panel $panel): void
    {
        $this->panel = $panel;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Resource Registration
    // ─────────────────────────────────────────────────────────────────────────

    public function roleResource(bool | Closure $condition = true): static
    {
        $this->registerRoleResource = $condition;

        return $this;
    }

    public function permissionResource(bool | Closure $condition = true): static
    {
        $this->registerPermissionResource = $condition;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Navigation
    // ─────────────────────────────────────────────────────────────────────────

    public function navigationGroup(string | Closure | null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationIcon(string | Closure | null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function navigationSort(int | Closure | null $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function registerNavigation(bool | Closure $condition = true): static
    {
        $this->registerNavigation = $condition;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Entity Exclusions
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param  list<class-string> | Closure  $resources
     */
    public function excludeResources(array | Closure $resources): static
    {
        $this->excludeResources = $resources;

        return $this;
    }

    /**
     * @param  list<class-string> | Closure  $pages
     */
    public function excludePages(array | Closure $pages): static
    {
        $this->excludePages = $pages;

        return $this;
    }

    /**
     * @param  list<class-string> | Closure  $widgets
     */
    public function excludeWidgets(array | Closure $widgets): static
    {
        $this->excludeWidgets = $widgets;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UI Configuration
    // ─────────────────────────────────────────────────────────────────────────

    public function gridColumns(int | Closure $columns): static
    {
        $this->gridColumns = $columns;

        return $this;
    }

    public function checkboxColumns(int | Closure $columns): static
    {
        $this->checkboxColumns = $columns;

        return $this;
    }

    public function resourcesTab(bool | Closure $condition = true): static
    {
        $this->resourcesTab = $condition;

        return $this;
    }

    public function pagesTab(bool | Closure $condition = true): static
    {
        $this->pagesTab = $condition;

        return $this;
    }

    public function widgetsTab(bool | Closure $condition = true): static
    {
        $this->widgetsTab = $condition;

        return $this;
    }

    public function customPermissionsTab(bool | Closure $condition = true): static
    {
        $this->customPermissionsTab = $condition;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Permission Configuration
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Set permission key case format.
     *
     * @param  'snake'|'kebab'|'camel'|'pascal'|'upper_snake'|'lower' | Closure  $case
     */
    public function permissionCase(string | Closure $case): static
    {
        $this->permissionCase = $case;

        return $this;
    }

    public function permissionSeparator(string | Closure $separator): static
    {
        $this->permissionSeparator = $separator;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Multi-Tenancy / Panel Scoping
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Scope roles/permissions to the current tenant (team).
     */
    public function scopedToTenant(bool | Closure $condition = true): static
    {
        $this->scopedToTenant = $condition;

        return $this;
    }

    /**
     * Set the tenant ownership relationship name.
     */
    public function tenantOwnershipRelationship(string | Closure $relationship): static
    {
        $this->tenantOwnershipRelationship = $relationship;

        return $this;
    }

    /**
     * Check if plugin is scoped to tenant.
     */
    public function isScopedToTenant(): bool
    {
        return $this->evaluate($this->scopedToTenant);
    }

    /**
     * Get the tenant ownership relationship name.
     */
    public function getTenantOwnershipRelationship(): ?string
    {
        return $this->evaluate($this->tenantOwnershipRelationship);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Getters
    // ─────────────────────────────────────────────────────────────────────────

    public function getPanel(): ?Panel
    {
        return $this->panel;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->evaluate($this->navigationGroup);
    }

    public function getNavigationIcon(): ?string
    {
        return $this->evaluate($this->navigationIcon);
    }

    public function getNavigationSort(): ?int
    {
        return $this->evaluate($this->navigationSort);
    }

    public function shouldRegisterNavigation(): bool
    {
        return $this->evaluate($this->registerNavigation);
    }

    public function getGridColumns(): int
    {
        return $this->evaluate($this->gridColumns);
    }

    public function getCheckboxColumns(): int
    {
        return $this->evaluate($this->checkboxColumns);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal
    // ─────────────────────────────────────────────────────────────────────────

    protected function applyConfigOverrides(Panel $panel): void
    {
        if ($this->navigationGroup !== null) {
            config()->set('filament-authz.navigation.group', $this->evaluate($this->navigationGroup));
        }

        if ($this->navigationIcon !== null) {
            config()->set('filament-authz.navigation.icons.roles', $this->evaluate($this->navigationIcon));
        }

        if ($this->navigationSort !== null) {
            config()->set('filament-authz.navigation.sort', $this->evaluate($this->navigationSort));
        }

        if ($this->excludeResources !== null) {
            config()->set('filament-authz.resources.exclude', $this->evaluate($this->excludeResources));
        }

        if ($this->excludePages !== null) {
            config()->set('filament-authz.pages.exclude', $this->evaluate($this->excludePages));
        }

        if ($this->excludeWidgets !== null) {
            config()->set('filament-authz.widgets.exclude', $this->evaluate($this->excludeWidgets));
        }

        config()->set('filament-authz.role_resource.grid_columns', $this->evaluate($this->gridColumns));
        config()->set('filament-authz.role_resource.checkbox_columns', $this->evaluate($this->checkboxColumns));
        config()->set('filament-authz.role_resource.tabs.resources', $this->evaluate($this->resourcesTab));
        config()->set('filament-authz.role_resource.tabs.pages', $this->evaluate($this->pagesTab));
        config()->set('filament-authz.role_resource.tabs.widgets', $this->evaluate($this->widgetsTab));
        config()->set('filament-authz.role_resource.tabs.custom_permissions', $this->evaluate($this->customPermissionsTab));

        if ($this->permissionCase !== null) {
            config()->set('filament-authz.permissions.case', $this->evaluate($this->permissionCase));
        }

        if ($this->permissionSeparator !== null) {
            config()->set('filament-authz.permissions.separator', $this->evaluate($this->permissionSeparator));
        }

        config()->set('filament-authz.scoped_to_tenant', $this->evaluate($this->scopedToTenant));
    }
}
