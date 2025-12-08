<?php

declare(strict_types=1);

namespace AIArmada\FilamentAuthz\Pages;

use AIArmada\FilamentAuthz\Services\RoleInheritanceService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

class RoleHierarchyPage extends Page
{
    /** @var Collection<int, Role> */
    public Collection $rootRoles;

    /** @var array<string, array<string, mixed>> */
    public array $hierarchyTree = [];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected string $view = 'filament-authz::pages.role-hierarchy';

    protected static ?string $title = 'Role Hierarchy';

    protected static ?string $navigationLabel = 'Role Hierarchy';

    protected static ?int $navigationSort = 11;

    public static function getNavigationGroup(): ?string
    {
        return config('filament-authz.navigation.group', 'Administration');
    }

    public function mount(RoleInheritanceService $service): void
    {
        $this->rootRoles = $service->getRootRoles();
        $this->buildTree($service);
    }

    public function setParent(string $roleId, ?string $parentId, RoleInheritanceService $service): void
    {
        /** @var Role|null $role */
        $role = Role::find($roleId);
        /** @var Role|null $parent */
        $parent = $parentId !== null ? Role::find($parentId) : null;

        if ($role === null) {
            return;
        }

        try {
            $service->setParent($role, $parent);

            Notification::make()
                ->title('Hierarchy Updated')
                ->body("Parent of '{$role->name}' has been updated.")
                ->success()
                ->send();

            $this->buildTree($service);
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function detachRole(string $roleId, RoleInheritanceService $service): void
    {
        $role = Role::find($roleId);

        if ($role === null) {
            return;
        }

        $service->detachFromParent($role);

        Notification::make()
            ->title('Role Detached')
            ->body("Role '{$role->name}' is now a root role.")
            ->success()
            ->send();

        $this->buildTree($service);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createRole')
                ->label('Create Role')
                ->form([
                    TextInput::make('name')
                        ->label('Role Name')
                        ->required()
                        ->maxLength(255),
                    Select::make('parent_id')
                        ->label('Parent Role')
                        ->options(Role::pluck('name', 'id'))
                        ->searchable()
                        ->placeholder('No parent (root role)'),
                    Select::make('guard_name')
                        ->label('Guard')
                        ->options([
                            'web' => 'Web',
                            'api' => 'API',
                        ])
                        ->default('web'),
                ])
                ->action(function (array $data, RoleInheritanceService $service): void {
                    $role = Role::create([
                        'name' => $data['name'],
                        'guard_name' => $data['guard_name'],
                    ]);

                    if (isset($data['parent_id'])) {
                        $parent = Role::find($data['parent_id']);
                        $service->setParent($role, $parent);
                    }

                    Notification::make()
                        ->title('Role Created')
                        ->body("Role '{$role->name}' has been created.")
                        ->success()
                        ->send();

                    $this->buildTree($service);
                }),
            Action::make('refresh')
                ->label('Refresh')
                ->action(fn (RoleInheritanceService $service) => $this->buildTree($service))
                ->icon('heroicon-o-arrow-path'),
        ];
    }

    protected function buildTree(RoleInheritanceService $service): void
    {
        $this->rootRoles = $service->getRootRoles();
        $this->hierarchyTree = [];

        foreach ($this->rootRoles as $role) {
            $this->hierarchyTree[] = $this->buildRoleNode($role, $service);
        }
    }

    /**
     * @return array{id: string, name: string, level: int, permission_count: int, children: array<int, mixed>}
     */
    protected function buildRoleNode(Role $role, RoleInheritanceService $service, int $level = 0): array
    {
        $children = $service->getChildren($role);

        return [
            'id' => $role->id,
            'name' => $role->name,
            'level' => $level,
            'permission_count' => $role->permissions()->count(),
            'children' => $children->map(
                fn (Role $child): array => $this->buildRoleNode($child, $service, $level + 1)
            )->toArray(),
        ];
    }
}
