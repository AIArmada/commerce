# Filament Enhancements

> **Document:** 9 of 10  
> **Package:** `aiarmada/filament-authz`  
> **Status:** Vision

---

## Overview

Elevate the Filament admin experience with **visual permission builders**, **role hierarchy diagrams**, **access matrices**, **audit log viewers**, and **real-time permission testing**.

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                 FILAMENT UI ENHANCEMENTS                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                    RESOURCES                              │   │
│  │                                                          │   │
│  │  • PermissionGroupResource (tree editor)                 │   │
│  │  • RoleTemplateResource (inheritance builder)            │   │
│  │  • AccessPolicyResource (ABAC policy builder)            │   │
│  │  • AuditLogResource (compliance viewer)                  │   │
│  │                                                          │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                    PAGES                                  │   │
│  │                                                          │   │
│  │  • Permission Matrix (grid view)                         │   │
│  │  • Role Hierarchy (visual diagram)                       │   │
│  │  • Permission Simulator (testing tool)                   │   │
│  │  • Compliance Dashboard                                  │   │
│  │                                                          │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                    WIDGETS                                │   │
│  │                                                          │   │
│  │  • Permission Stats Widget                               │   │
│  │  • Recent Audit Activity Widget                          │   │
│  │  • Expiring Permissions Widget                           │   │
│  │  • Role Distribution Widget                              │   │
│  │                                                          │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Permission Matrix Page

### PermissionMatrixPage

```php
final class PermissionMatrixPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static string $view = 'filament-authz::pages.permission-matrix';

    public array $roles = [];
    public array $permissions = [];
    public array $matrix = [];

    public function mount(): void
    {
        $this->roles = Role::orderBy('name')->pluck('name', 'id')->toArray();
        $this->permissions = $this->getGroupedPermissions();
        $this->matrix = $this->buildMatrix();
    }

    private function getGroupedPermissions(): array
    {
        return Permission::all()
            ->groupBy(fn ($p) => explode('.', $p->name)[0])
            ->map(fn ($perms) => $perms->pluck('name', 'id')->toArray())
            ->toArray();
    }

    private function buildMatrix(): array
    {
        $matrix = [];

        foreach (Role::with('permissions')->get() as $role) {
            $matrix[$role->id] = $role->permissions->pluck('id')->toArray();
        }

        return $matrix;
    }

    public function togglePermission(string $roleId, string $permissionId): void
    {
        $role = Role::findById($roleId);
        $permission = Permission::findById($permissionId);

        if ($role->hasPermissionTo($permission)) {
            $role->revokePermissionTo($permission);
            $this->dispatch('permission-revoked');
        } else {
            $role->givePermissionTo($permission);
            $this->dispatch('permission-granted');
        }

        $this->matrix = $this->buildMatrix();
    }

    public function grantAll(string $roleId, string $group): void
    {
        $role = Role::findById($roleId);
        $permissions = Permission::query()
            ->where('name', 'like', "{$group}.%")
            ->get();

        $role->givePermissionTo($permissions);
        $this->matrix = $this->buildMatrix();
    }

    public function revokeAll(string $roleId, string $group): void
    {
        $role = Role::findById($roleId);
        $permissions = Permission::query()
            ->where('name', 'like', "{$group}.%")
            ->get();

        $role->revokePermissionTo($permissions);
        $this->matrix = $this->buildMatrix();
    }
}
```

### Matrix Blade View

```blade
{{-- permission-matrix.blade.php --}}
<x-filament-panels::page>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead>
                <tr>
                    <th class="sticky left-0 bg-white dark:bg-gray-900 px-4 py-2">Permission</th>
                    @foreach($roles as $roleId => $roleName)
                        <th class="px-4 py-2 text-center">{{ $roleName }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($permissions as $group => $perms)
                    <tr class="bg-gray-50 dark:bg-gray-800">
                        <td colspan="{{ count($roles) + 1 }}" class="px-4 py-2 font-semibold">
                            {{ Str::title($group) }}
                        </td>
                    </tr>
                    @foreach($perms as $permId => $permName)
                        <tr>
                            <td class="sticky left-0 bg-white dark:bg-gray-900 px-4 py-2">
                                {{ $permName }}
                            </td>
                            @foreach($roles as $roleId => $roleName)
                                <td class="px-4 py-2 text-center">
                                    <button
                                        wire:click="togglePermission('{{ $roleId }}', '{{ $permId }}')"
                                        class="w-6 h-6 rounded {{ in_array($permId, $matrix[$roleId] ?? []) ? 'bg-success-500' : 'bg-gray-200 dark:bg-gray-700' }}"
                                    >
                                        @if(in_array($permId, $matrix[$roleId] ?? []))
                                            <x-heroicon-s-check class="w-4 h-4 text-white mx-auto" />
                                        @endif
                                    </button>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
```

---

## Role Hierarchy Page

### RoleHierarchyPage

```php
final class RoleHierarchyPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-share';
    protected static string $view = 'filament-authz::pages.role-hierarchy';

    public array $hierarchyData = [];

    public function mount(): void
    {
        $this->hierarchyData = $this->buildHierarchyTree();
    }

    private function buildHierarchyTree(): array
    {
        $roles = Role::with('permissions')
            ->orderBy('level')
            ->get();

        return $this->buildTree($roles, null);
    }

    private function buildTree(Collection $roles, ?string $parentId): array
    {
        return $roles
            ->filter(fn ($role) => $role->parent_role_id === $parentId)
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'level' => $role->level,
                'permissions_count' => $role->permissions->count(),
                'users_count' => $role->users()->count(),
                'children' => $this->buildTree($roles, $role->id),
            ])
            ->values()
            ->toArray();
    }

    public function setParent(string $roleId, ?string $parentId): void
    {
        $role = Role::findById($roleId);

        if ($parentId) {
            $parent = Role::findById($parentId);
            $role->update([
                'parent_role_id' => $parentId,
                'level' => $parent->level + 1,
            ]);
        } else {
            $role->update([
                'parent_role_id' => null,
                'level' => 0,
            ]);
        }

        $this->hierarchyData = $this->buildHierarchyTree();
    }
}
```

---

## Permission Simulator Page

### PermissionSimulatorPage

```php
final class PermissionSimulatorPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';
    protected static string $view = 'filament-authz::pages.permission-simulator';

    public ?string $userId = null;
    public ?string $permission = null;
    public ?string $addRole = null;
    public ?string $removeRole = null;

    public ?array $testResult = null;
    public ?array $simulationResult = null;

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Test User Permissions')
                ->schema([
                    Select::make('userId')
                        ->label('Select User')
                        ->options(User::pluck('email', 'id'))
                        ->searchable()
                        ->required(),

                    Select::make('permission')
                        ->label('Permission to Test')
                        ->options(Permission::pluck('name', 'name'))
                        ->searchable(),
                ]),

            Section::make('Simulate Role Change')
                ->schema([
                    Select::make('addRole')
                        ->label('Add Role')
                        ->options(Role::pluck('name', 'id'))
                        ->searchable(),

                    Select::make('removeRole')
                        ->label('Remove Role')
                        ->options(Role::pluck('name', 'id'))
                        ->searchable(),
                ]),
        ]);
    }

    public function testPermission(): void
    {
        if (! $this->userId) {
            return;
        }

        $user = User::find($this->userId);
        $tester = app(PermissionTester::class);

        if ($this->permission) {
            $result = $tester->testPermission($user, $this->permission);
            $this->testResult = $result->toArray();
        } else {
            $report = $tester->testUser($user);
            $this->testResult = [
                'total' => count($report->results),
                'granted' => count(array_filter($report->results, fn ($r) => $r->granted)),
                'denied' => count(array_filter($report->results, fn ($r) => ! $r->granted)),
            ];
        }
    }

    public function simulate(): void
    {
        if (! $this->userId) {
            return;
        }

        $user = User::find($this->userId);
        $tester = app(PermissionTester::class);

        if ($this->addRole) {
            $role = Role::find($this->addRole);
            $result = $tester->simulateRoleAssignment($user, $role);
        } elseif ($this->removeRole) {
            $role = Role::find($this->removeRole);
            $result = $tester->simulateRoleRemoval($user, $role);
        } else {
            return;
        }

        $this->simulationResult = $result->toArray();
    }
}
```

---

## Access Policy Builder

### AccessPolicyResource Form

```php
public static function form(Form $form): Form
{
    return $form->schema([
        Section::make('Policy Details')
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->rows(2),

                Select::make('effect')
                    ->options([
                        'allow' => 'Allow',
                        'deny' => 'Deny',
                    ])
                    ->required(),

                TextInput::make('priority')
                    ->numeric()
                    ->default(0)
                    ->helperText('Higher priority policies are evaluated first'),

                Toggle::make('is_active')
                    ->default(true),
            ])->columns(2),

        Section::make('Target')
            ->schema([
                Select::make('target_action')
                    ->options(fn () => Permission::pluck('name', 'name')
                        ->prepend('All Actions', '*'))
                    ->searchable()
                    ->required(),

                TextInput::make('target_resource')
                    ->placeholder('e.g., Order, Product, or * for all'),
            ])->columns(2),

        Section::make('Validity Period')
            ->schema([
                DateTimePicker::make('valid_from')
                    ->label('Valid From'),

                DateTimePicker::make('valid_until')
                    ->label('Valid Until'),
            ])->columns(2),

        Section::make('Conditions')
            ->schema([
                Repeater::make('conditions')
                    ->schema([
                        Select::make('source')
                            ->options([
                                'subject' => 'Subject (User)',
                                'resource' => 'Resource',
                                'environment' => 'Environment',
                            ])
                            ->default('subject')
                            ->required(),

                        TextInput::make('attribute')
                            ->required()
                            ->placeholder('e.g., roles, department, hour'),

                        Select::make('operator')
                            ->options([
                                'eq' => 'Equals',
                                'neq' => 'Not Equals',
                                'gt' => 'Greater Than',
                                'lt' => 'Less Than',
                                'contains' => 'Contains',
                                'in' => 'In Array',
                                'between' => 'Between',
                            ])
                            ->required(),

                        TextInput::make('value')
                            ->required()
                            ->helperText('For arrays, use JSON: ["value1", "value2"]'),
                    ])
                    ->columns(4)
                    ->itemLabel(fn (array $state) => "{$state['source']}.{$state['attribute']} {$state['operator']} {$state['value']}")
                    ->collapsible(),
            ]),
    ]);
}
```

---

## Audit Log Viewer

### AuditLogResource

```php
final class AuditLogResource extends Resource
{
    protected static ?string $model = PermissionAuditLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('event_type')
                    ->badge()
                    ->color(fn ($state) => match ($state->severity()) {
                        AuditSeverity::Critical => 'danger',
                        AuditSeverity::High => 'warning',
                        AuditSeverity::Medium => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('actor.email')
                    ->label('Actor')
                    ->searchable(),

                TextColumn::make('subject.email')
                    ->label('Subject')
                    ->searchable(),

                TextColumn::make('target_name')
                    ->label('Target'),

                TextColumn::make('ip_address')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->filters([
                SelectFilter::make('event_type')
                    ->options(AuditEventType::class),

                SelectFilter::make('severity')
                    ->options(AuditSeverity::class),

                Filter::make('occurred_at')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('occurred_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('occurred_at', '<=', $date));
                    }),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Grid::make(2)->schema([
                TextEntry::make('event_type'),
                TextEntry::make('severity'),
                TextEntry::make('occurred_at')->dateTime(),
                TextEntry::make('actor.email'),
                TextEntry::make('subject.email'),
                TextEntry::make('target_name'),
                TextEntry::make('ip_address'),
                TextEntry::make('user_agent'),
            ]),

            Section::make('Changes')->schema([
                KeyValueEntry::make('old_value'),
                KeyValueEntry::make('new_value'),
            ])->columns(2),

            Section::make('Context')->schema([
                KeyValueEntry::make('context'),
            ]),
        ]);
    }
}
```

---

## Widgets

### ExpiringPermissionsWidget

```php
final class ExpiringPermissionsWidget extends Widget
{
    protected static string $view = 'filament-authz::widgets.expiring-permissions';

    public function getExpiringPermissions(): Collection
    {
        return ScopedPermission::query()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(7)])
            ->with(['permission', 'permissionable'])
            ->orderBy('expires_at')
            ->limit(5)
            ->get();
    }
}
```

### RecentAuditActivityWidget

```php
final class RecentAuditActivityWidget extends Widget
{
    protected static string $view = 'filament-authz::widgets.recent-audit-activity';
    protected int | string | array $columnSpan = 'full';

    public function getRecentActivity(): Collection
    {
        return PermissionAuditLog::query()
            ->with(['actor', 'subject'])
            ->latest('occurred_at')
            ->limit(10)
            ->get();
    }
}
```

### RoleDistributionWidget

```php
final class RoleDistributionWidget extends ChartWidget
{
    protected static ?string $heading = 'User Role Distribution';

    protected function getData(): array
    {
        $roles = Role::withCount('users')
            ->orderByDesc('users_count')
            ->limit(8)
            ->get();

        return [
            'datasets' => [
                [
                    'data' => $roles->pluck('users_count')->toArray(),
                    'backgroundColor' => [
                        '#3B82F6', '#10B981', '#F59E0B', '#EF4444',
                        '#8B5CF6', '#EC4899', '#06B6D4', '#6B7280',
                    ],
                ],
            ],
            'labels' => $roles->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
```

---

## Navigation

**Previous:** [08-database-evolution.md](08-database-evolution.md)  
**Next:** [10-implementation-roadmap.md](10-implementation-roadmap.md)
