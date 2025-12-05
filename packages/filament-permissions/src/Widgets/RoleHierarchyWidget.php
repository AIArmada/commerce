<?php

declare(strict_types=1);

namespace AIArmada\FilamentPermissions\Widgets;

use AIArmada\FilamentPermissions\Services\RoleInheritanceService;
use Filament\Widgets\Widget;
use Spatie\Permission\Models\Role;

class RoleHierarchyWidget extends Widget
{
    protected string $view = 'filament-permissions::widgets.role-hierarchy';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<int, array{id: string, name: string, level: int, children: array<int, mixed>}>
     */
    public function getHierarchy(): array
    {
        $service = app(RoleInheritanceService::class);
        $rootRoles = $service->getRootRoles();
        $tree = [];

        foreach ($rootRoles as $role) {
            $tree[] = $this->buildNode($role, $service);
        }

        return $tree;
    }

    /**
     * @return array{id: string, name: string, level: int, permission_count: int, children: array<int, mixed>}
     */
    protected function buildNode(Role $role, RoleInheritanceService $service, int $level = 0): array
    {
        $children = $service->getChildren($role);

        return [
            'id' => (string) $role->id,
            'name' => $role->name,
            'level' => $level,
            'permission_count' => $role->permissions()->count(),
            'children' => $children->map(
                fn (Role $child): array => $this->buildNode($child, $service, $level + 1)
            )->toArray(),
        ];
    }
}
