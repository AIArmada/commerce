<?php

declare(strict_types=1);

use AIArmada\Authz\Models\AuthzScope;
use AIArmada\Authz\Models\Permission;
use AIArmada\Authz\Models\Role;
use AIArmada\Authz\Services\ImpersonateManager;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAuthz\Actions\ImpersonateAction;
use AIArmada\FilamentAuthz\Concerns\HasPanelAuthz;
use AIArmada\FilamentAuthz\Console\GeneratePoliciesCommand;
use AIArmada\FilamentAuthz\Console\SeederCommand;
use AIArmada\FilamentAuthz\FilamentAuthzPlugin;
use AIArmada\FilamentAuthz\Forms\Components\PermissionTabFactory;
use AIArmada\FilamentAuthz\Middleware\SyncAuthzTenant;
use AIArmada\FilamentAuthz\Resources\PermissionResource;
use AIArmada\FilamentAuthz\Resources\RoleResource;
use AIArmada\FilamentAuthz\Resources\RoleResource\Schemas\RoleForm;
use AIArmada\FilamentAuthz\Resources\UserResource;
use AIArmada\FilamentAuthz\Services\EntityDiscoveryService;
use AIArmada\FilamentAuthz\Support\ImpersonationActorAuth;
use AIArmada\FilamentAuthz\Support\PanelExclusions;
use AIArmada\FilamentAuthz\Support\UserAuthzForm;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Panel;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;
use Spatie\Permission\PermissionRegistrar;

class RepairPlainUser extends Authenticatable
{
    use HasUuids;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    public function getTable(): string
    {
        return 'users';
    }
}

class RepairPanelAuthzUser extends User
{
    use HasPanelAuthz;
}

class RepairSchemaHost extends LivewireComponent implements HasSchemas
{
    use InteractsWithSchemas;

    public function render(): string
    {
        return '';
    }
}

function filamentAuthzRepairFindField(array $components, string $name): mixed
{
    foreach ($components as $component) {
        if (method_exists($component, 'getName') && $component->getName() === $name) {
            return $component;
        }

        if (method_exists($component, 'getChildComponents')) {
            $found = filamentAuthzRepairFindField($component->getChildComponents(), $name);

            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

beforeEach(function (): void {
    config()->set('authz.scopes.enforce', false);
    config()->set('authz.impersonate.guard', 'web');
    setPermissionsTeamId(null);
});

describe('page impersonate action', function (): void {
    it('redirects after a successful take', function (): void {
        $role = Role::findOrCreate((string) config('authz.super_admin_role'), 'web');

        $actor = User::query()->create([
            'name' => 'Page Actor',
            'email' => 'page-actor-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);
        $actor->assignRole($role);

        $target = User::query()->create([
            'name' => 'Page Target',
            'email' => 'page-target-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);

        $this->actingAs($actor);
        request()->headers->set('referer', '/admin');

        // Action::redirect() requires a Livewire host, so capture it.
        $action = Mockery::mock(ImpersonateAction::class)->makePartial();
        $targetProperty = new ReflectionProperty(ImpersonateAction::class, 'targetRecord');
        $targetProperty->setAccessible(true);
        $targetProperty->setValue($action, $target);

        $redirected = null;
        $action->shouldReceive('redirect')->withAnyArgs()->andReturnUsing(
            function (string $url) use (&$redirected): void {
                $redirected = $url;
            }
        );

        $method = new ReflectionMethod(ImpersonateAction::class, 'impersonate');
        $method->setAccessible(true);

        expect($method->invoke($action))->toBeTrue()
            ->and($redirected)->toBe('/admin')
            ->and(session(ImpersonateManager::SESSION_IMPERSONATOR_ID))->toBe($actor->getAuthIdentifier())
            ->and(Auth::guard('web')->id())->toBe($target->getAuthIdentifier());
    });

    it('notifies and returns false when take fails', function (): void {
        config()->set('authz.impersonate.guard', 'missing-guard');

        $role = Role::findOrCreate((string) config('authz.super_admin_role'), 'web');

        $actor = User::query()->create([
            'name' => 'Fail Actor',
            'email' => 'fail-actor-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);
        $actor->assignRole($role);

        $target = User::query()->create([
            'name' => 'Fail Target',
            'email' => 'fail-target-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);

        $this->actingAs($actor);
        request()->headers->set('referer', '/admin');

        $action = ImpersonateAction::make()->record($target);
        $method = new ReflectionMethod(ImpersonateAction::class, 'impersonate');
        $method->setAccessible(true);

        expect($method->invoke($action))->toBeFalse()
            ->and(session()->has(ImpersonateManager::SESSION_IMPERSONATOR_ID))->toBeFalse()
            ->and(session('filament.notifications'))->not->toBeEmpty();
    });

    it('returns false without an authenticated user', function (): void {
        $target = User::query()->create([
            'name' => 'No Auth Target',
            'email' => 'no-auth-target-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);

        $action = ImpersonateAction::make()->record($target);
        $method = new ReflectionMethod(ImpersonateAction::class, 'impersonate');
        $method->setAccessible(true);

        expect($method->invoke($action))->toBeFalse();
    });
});

describe('tenant sync middleware', function (): void {
    it('restores the team context when downstream throws', function (): void {
        $tenant = User::query()->create([
            'name' => 'Tenant Owner',
            'email' => 'tenant-owner-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);

        $panel = Panel::make()->id('tenant-test')->tenant(User::class);
        Filament::setCurrentPanel($panel);
        Filament::setTenant($tenant, true);

        expect(Filament::hasTenancy())->toBeTrue();

        setPermissionsTeamId('original-team');

        $middleware = new SyncAuthzTenant;

        expect(fn () => $middleware->handle(Request::create('/'), function (): void {
            throw new RuntimeException('boom');
        }))->toThrow(RuntimeException::class, 'boom');

        expect(getPermissionsTeamId())->toBe('original-team');
    });

    it('passes through without tenancy', function (): void {
        $panel = Panel::make()->id('plain-test');
        Filament::setCurrentPanel($panel);

        $middleware = new SyncAuthzTenant;
        $response = $middleware->handle(Request::create('/'), fn () => response('ok'));

        expect($response->getContent())->toBe('ok');
    });
});

describe('panel permission key', function (): void {
    it('uses the key builder for hyphenated panel ids', function (): void {
        config()->set('authz.permissions.case', 'camel');
        config()->set('authz.permissions.separator', '.');
        config()->set('filament-authz.panels.prefix', 'panel');

        $user = RepairPanelAuthzUser::query()->create([
            'name' => 'Panel User',
            'email' => 'panel-user-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);

        $checked = null;
        Gate::before(function ($actor, string $ability) use (&$checked) {
            $checked = $ability;

            return true;
        });

        $panel = Panel::make()->id('app-admin');

        expect($user->canAccessPanel($panel))->toBeTrue()
            ->and($checked)->toBe('panel.appAdmin');
    });

    it('honors custom separators and prefixes', function (): void {
        config()->set('authz.permissions.case', 'camel');
        config()->set('authz.permissions.separator', ':');
        config()->set('filament-authz.panels.prefix', 'access');

        $user = RepairPanelAuthzUser::query()->create([
            'name' => 'Panel User Two',
            'email' => 'panel-user-two-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);

        $checked = null;
        Gate::before(function ($actor, string $ability) use (&$checked) {
            $checked = $ability;

            return true;
        });

        $panel = Panel::make()->id('app-admin');

        expect($user->canAccessPanel($panel))->toBeTrue()
            ->and($checked)->toBe('access:appAdmin');
    });
});

describe('seeder escaping', function (): void {
    it('escapes hostile names and stays valid PHP', function (): void {
        $command = app(SeederCommand::class);

        $evil = "o'brien\\x'); Role::query()->delete(); //";

        $permission = new Permission;
        $permission->name = $evil;
        $permission->guard_name = 'web';

        $role = new Role;
        $role->name = $evil;
        $role->guard_name = 'web';
        $role->setRelation('permissions', collect([$permission]));

        $method = new ReflectionMethod($command, 'generateSeederContent');
        $method->setAccessible(true);
        $content = $method->invoke($command, collect([$permission]), collect([$role]), 'all');

        expect($content)->toContain(var_export($evil, true));

        $path = tempnam(sys_get_temp_dir(), 'seeder') . '.php';
        file_put_contents($path, $content);

        try {
            expect(Process::run([PHP_BINARY, '-l', $path])->successful())->toBeTrue();
        } finally {
            unlink($path);
        }
    });
});

describe('per-panel settings', function (): void {
    it('returns independent instances from make()', function (): void {
        $first = FilamentAuthzPlugin::make()->navigationGroup('A');
        $second = FilamentAuthzPlugin::make();

        expect($second)->not->toBe($first)
            ->and($second->getNavigationGroup())->toBeNull()
            ->and($first->getNavigationGroup())->toBe('A');
    });

    it('preserves global config when fluent calls are absent', function (): void {
        config()->set('filament-authz.role_resource.tabs.resources', false);
        config()->set('filament-authz.resources.exclude', ['App\\Foo']);
        config()->set('filament-authz.scoped_to_tenant', false);
        config()->set('authz.scopes.enforce', false);

        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('resources')->andReturn($panel);

        FilamentAuthzPlugin::make()->register($panel);

        expect(config('filament-authz.role_resource.tabs.resources'))->toBeFalse()
            ->and(config('filament-authz.resources.exclude'))->toBe(['App\\Foo'])
            ->and(config('filament-authz.scoped_to_tenant'))->toBeFalse()
            ->and(config('authz.scopes.enforce'))->toBeFalse();
    });

    it('writes explicit fluent scoping to global config', function (): void {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('resources')->andReturn($panel);

        FilamentAuthzPlugin::make()->scopeToTenant(false)->register($panel);

        expect(config('filament-authz.scoped_to_tenant'))->toBeFalse()
            ->and(config('authz.scopes.enforce'))->toBeFalse();
    });

    it('derives scopes.enforce from effective values when only centralApp is set', function (): void {
        $panel = Mockery::mock(Panel::class);
        $panel->shouldReceive('resources')->andReturn($panel);

        FilamentAuthzPlugin::make()->centralApp()->register($panel);

        expect(config('filament-authz.central_app'))->toBeTrue()
            ->and(config('authz.scopes.enforce'))->toBeFalse();
    });

    it('distinguishes unset overrides from explicit values', function (): void {
        $plugin = FilamentAuthzPlugin::make();

        expect($plugin->getExcludedResourcesOverride())->toBeNull()
            ->and($plugin->getResourcesTabOverride())->toBeNull()
            ->and($plugin->getGridColumnsOverride())->toBeNull()
            ->and($plugin->getExcludedResources())->toBe([])
            ->and($plugin->shouldShowResourcesTab())->toBeTrue()
            ->and($plugin->getGridColumns())->toBe(2)
            ->and($plugin->isScopedToTenant())->toBeTrue()
            ->and($plugin->isCentralApp())->toBeFalse();

        $plugin->excludeResources([RoleResource::class])->resourcesTab(false)->gridColumns(4);

        expect($plugin->getExcludedResourcesOverride())->toBe([RoleResource::class])
            ->and($plugin->getResourcesTabOverride())->toBeFalse()
            ->and($plugin->getGridColumnsOverride())->toBe(4)
            ->and($plugin->getExcludedResources())->toBe([RoleResource::class])
            ->and($plugin->shouldShowResourcesTab())->toBeFalse()
            ->and($plugin->getGridColumns())->toBe(4);
    });

    it('isolates discovery exclusions per panel', function (): void {
        $service = app(EntityDiscoveryService::class);

        $panelA = Panel::make()->id('panel-a');
        $panelA->plugin(FilamentAuthzPlugin::make()->excludeResources([RoleResource::class]));
        $panelA->resources([RoleResource::class, PermissionResource::class]);

        $panelB = Panel::make()->id('panel-b');
        $panelB->plugin(FilamentAuthzPlugin::make());
        $panelB->resources([RoleResource::class, PermissionResource::class]);

        $namesA = $service->discoverResources($panelA)->pluck('class')->all();
        $namesB = $service->discoverResources($panelB)->pluck('class')->all();

        expect($namesA)->not->toContain(RoleResource::class)
            ->and($namesA)->toContain(PermissionResource::class)
            ->and($namesB)->toContain(RoleResource::class, PermissionResource::class)
            ->and(config('filament-authz.resources.exclude'))->toBe([]);
    });

    it('falls back to global config without a plugin', function (): void {
        config()->set('filament-authz.resources.exclude', ['App\\Foo']);
        config()->set('filament-authz.pages.exclude', ['App\\Bar']);

        expect(PanelExclusions::resolve(null, 'resources'))->toBe(['App\\Foo'])
            ->and(PanelExclusions::resolve(null, 'pages'))->toBe(['App\\Bar']);
    });

    it('honors tab config when fluent calls are absent', function (): void {
        config()->set('filament-authz.role_resource.tabs.resources', false);

        $panel = Panel::make()->id('tabs-config');
        $panel->plugin(FilamentAuthzPlugin::make());
        Filament::setCurrentPanel($panel);

        expect(PermissionTabFactory::getPermissionTabs())->toHaveCount(5);
    });

    it('honors per-panel fluent tab overrides without touching config', function (): void {
        $panel = Panel::make()->id('tabs-fluent');
        $panel->plugin(FilamentAuthzPlugin::make()->resourcesTab(false));
        Filament::setCurrentPanel($panel);

        expect(PermissionTabFactory::getPermissionTabs())->toHaveCount(5)
            ->and(config('filament-authz.role_resource.tabs.resources'))->toBeTrue();
    });
});

describe('direct permission validation', function (): void {
    it('syncs valid permission ids', function (): void {
        $user = User::query()->create([
            'name' => 'Perm User',
            'email' => 'perm-user-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);
        $permission = Permission::findOrCreate('orders.view', 'web');

        $method = new ReflectionMethod(UserAuthzForm::class, 'syncDirectPermissions');
        $method->setAccessible(true);
        $method->invoke(null, $user, [$permission->getKey()]);

        expect($user->refresh()->hasDirectPermission('orders.view'))->toBeTrue();
    });

    it('rejects forged permission ids', function (): void {
        $user = User::query()->create([
            'name' => 'Forged User',
            'email' => 'forged-user-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);

        $method = new ReflectionMethod(UserAuthzForm::class, 'syncDirectPermissions');
        $method->setAccessible(true);

        expect(fn () => $method->invoke(null, $user, [(string) Str::uuid()]))
            ->toThrow(AuthorizationException::class);
    });

    it('rejects permissions from another guard', function (): void {
        $user = User::query()->create([
            'name' => 'Guard User',
            'email' => 'guard-user-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);
        $apiPermission = Permission::findOrCreate('orders.view', 'api');

        $method = new ReflectionMethod(UserAuthzForm::class, 'syncDirectPermissions');
        $method->setAccessible(true);

        expect(fn () => $method->invoke(null, $user, [$apiPermission->getKey()]))
            ->toThrow(AuthorizationException::class);
    });
});

describe('actor authorization memoization', function (): void {
    it('resolves once per actor per team', function (): void {
        $auth = app(ImpersonationActorAuth::class);

        $actor = User::query()->create([
            'name' => 'Memo Actor',
            'email' => 'memo-actor-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);
        $other = User::query()->create([
            'name' => 'Memo Other',
            'email' => 'memo-other-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);

        $calls = 0;
        $resolver = function () use (&$calls): bool {
            $calls++;

            return true;
        };

        expect($auth->isAuthorized($actor, $resolver))->toBeTrue()
            ->and($auth->isAuthorized($actor, $resolver))->toBeTrue()
            ->and($calls)->toBe(1);

        $auth->isAuthorized($other, $resolver);

        expect($calls)->toBe(2);

        setPermissionsTeamId('team-a');
        $auth->isAuthorized($actor, $resolver);

        expect($calls)->toBe(3);

        setPermissionsTeamId(null);
        $auth->isAuthorized($actor, $resolver);

        expect($calls)->toBe(3);

        $auth->clear();
        $auth->isAuthorized($actor, $resolver);

        expect($calls)->toBe(4);
    });
});

describe('permission state hydration', function (): void {
    it('queries once across sections and filters to valid options', function (): void {
        $role = Role::findOrCreate('editor', 'web');
        $role->givePermissionTo([
            Permission::findOrCreate('orders.view', 'web'),
            Permission::findOrCreate('orders.create', 'web'),
        ]);

        $role = Role::query()->find($role->getKey());

        $components = [];
        $schema = Schema::make(new RepairSchemaHost);

        foreach (['section-a', 'section-b', 'section-c'] as $key) {
            $components[] = CheckboxList::make($key)
                ->options(['orders.view' => 'View', 'orders.create' => 'Create', 'other' => 'Other'])
                ->container($schema);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        foreach ($components as $component) {
            PermissionTabFactory::setPermissionStateForRecord($component, $role);

            expect($component->getRawState())->toBe(['orders.view', 'orders.create']);
        }

        $permissionQueries = array_filter(
            DB::getQueryLog(),
            fn (array $query): bool => str_contains($query['query'], 'permissions')
        );

        expect($permissionQueries)->toHaveCount(1);
    });

    it('ignores null records', function (): void {
        $component = CheckboxList::make('null-record')->options(['a' => 'A']);
        PermissionTabFactory::setPermissionStateForRecord($component, null);

        expect(true)->toBeTrue();
    });
});

describe('unknown panel handling', function (): void {
    it('fails gracefully for unknown panels', function (): void {
        // Would previously throw a TypeError passing null to getTargetResources().
        $this->artisan('authz:policies', ['--panel' => 'missing-panel'])
            ->assertFailed();
    });
});

describe('central-app scope validation', function (): void {
    it('rejects forged scopes and accepts real ones', function (): void {
        config()->set('authz.scopes.enabled', true);
        config()->set('filament-authz.central_app', true);

        $scope = AuthzScope::query()->create([
            'scopeable_type' => User::class,
            'scopeable_id' => (string) Str::uuid(),
            'label' => 'Tenant A',
        ]);

        $teamsKey = app(PermissionRegistrar::class)->teamsKey;

        $schema = RoleForm::configure(Schema::make(new RepairSchemaHost), Role::class, fn () => Tabs::make('Permissions'));
        $select = filamentAuthzRepairFindField($schema->getComponents(), $teamsKey);

        expect($select)->toBeInstanceOf(Select::class);

        $rules = $select->getValidationRules();

        expect(Validator::make([$teamsKey => (string) Str::uuid()], [$teamsKey => $rules])->fails())->toBeTrue()
            ->and(Validator::make([$teamsKey => $scope->getKey()], [$teamsKey => $rules])->passes())->toBeTrue()
            ->and(Validator::make([$teamsKey => null], [$teamsKey => $rules])->passes())->toBeTrue();
    });

    it('enforces configured scope options', function (): void {
        config()->set('authz.scopes.enabled', true);
        config()->set('filament-authz.central_app', true);

        $allowed = AuthzScope::query()->create([
            'scopeable_type' => User::class,
            'scopeable_id' => (string) Str::uuid(),
            'label' => 'Tenant A',
        ]);
        $other = AuthzScope::query()->create([
            'scopeable_type' => User::class,
            'scopeable_id' => (string) Str::uuid(),
            'label' => 'Tenant B',
        ]);

        config()->set('filament-authz.role_resource.scope_options', [$allowed->getKey() => 'Tenant A']);

        $teamsKey = app(PermissionRegistrar::class)->teamsKey;

        $schema = RoleForm::configure(Schema::make(new RepairSchemaHost), Role::class, fn () => Tabs::make('Permissions'));
        $select = filamentAuthzRepairFindField($schema->getComponents(), $teamsKey);

        expect($select)->toBeInstanceOf(Select::class);

        $rules = $select->getValidationRules();

        expect(Validator::make([$teamsKey => $other->getKey()], [$teamsKey => $rules])->fails())->toBeTrue()
            ->and(Validator::make([$teamsKey => $allowed->getKey()], [$teamsKey => $rules])->passes())->toBeTrue();
    });
});

describe('user password rule', function (): void {
    it('enforces password strength on create and skips empty on edit', function (): void {
        $createSchema = UserResource::form(Schema::make(new RepairSchemaHost)->operation('create'));
        $createPassword = filamentAuthzRepairFindField($createSchema->getComponents(), 'password');

        expect($createPassword)->not->toBeNull();

        $createRules = $createPassword->getValidationRules();

        expect(Validator::make(['password' => 'short'], ['password' => $createRules])->fails())->toBeTrue()
            ->and(Validator::make(['password' => 'long-enough-password'], ['password' => $createRules])->passes())->toBeTrue();

        $editSchema = UserResource::form(Schema::make(new RepairSchemaHost)->operation('edit'));
        $editPassword = filamentAuthzRepairFindField($editSchema->getComponents(), 'password');

        expect($editPassword)->not->toBeNull();

        $editRules = $editPassword->getValidationRules();

        expect(Validator::make(['password' => null], ['password' => $editRules])->passes())->toBeTrue()
            ->and(Validator::make(['password' => 'short'], ['password' => $editRules])->fails())->toBeTrue()
            ->and(Validator::make(['password' => 'long-enough-password'], ['password' => $editRules])->passes())->toBeTrue();
    });
});

describe('visibleJs escaping', function (): void {
    it('escapes quotes and backslashes', function (): void {
        expect(PermissionTabFactory::escapeForVisibleJs("l'ami"))->toBe("l\\'ami")
            ->and(PermissionTabFactory::escapeForVisibleJs('a\\b'))->toBe('a\\\\b')
            ->and(PermissionTabFactory::escapeForVisibleJs('plain'))->toBe('plain');
    });

    it('keeps apostrophes out of raw search expressions', function (): void {
        $method = new ReflectionMethod(PermissionTabFactory::class, 'getGroupedResourceSections');
        $method->setAccessible(true);

        $grouped = collect([
            'Package' => collect([
                ['class' => RoleResource::class, 'permissions' => ['a.b' => 'B'], 'label' => "L'ami"],
            ]),
        ]);

        $sections = $method->invoke(null, $grouped, null);
        $expected = PermissionTabFactory::escapeForVisibleJs(Str::lower(PermissionTabFactory::normalizeLabel("L'ami")));

        expect($expected)->toContain("\\'")
            ->and($sections)->toHaveCount(1)
            ->and($sections[0]->getVisibleJs())->toContain("'{$expected}'");
    });
});

describe('impersonation oracle', function (): void {
    it('returns 403 for unauthorized actors even when the target is missing', function (): void {
        config()->set('auth.providers.users.model', RepairPlainUser::class);

        $actor = RepairPlainUser::query()->create([
            'name' => 'Oracle Actor',
            'email' => 'oracle-actor-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);

        $this->actingAs($actor)
            ->post(route('filament-authz.impersonate', ['userId' => (string) Str::uuid()]), [
                'redirect_to' => '/',
            ])
            ->assertForbidden();
    });

    it('returns 404 for authorized actors when the target is missing', function (): void {
        $role = Role::findOrCreate((string) config('authz.super_admin_role'), 'web');

        $actor = User::query()->create([
            'name' => 'Oracle Admin',
            'email' => 'oracle-admin-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);
        $actor->assignRole($role);

        $this->actingAs($actor)
            ->post(route('filament-authz.impersonate', ['userId' => (string) Str::uuid()]), [
                'redirect_to' => '/',
            ])
            ->assertNotFound();
    });
});

describe('direct user columns and option bounds', function (): void {
    it('selects display columns without password hashes', function (): void {
        $user = User::query()->create([
            'name' => 'Direct Dana',
            'email' => 'direct-dana-' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        $permission = Permission::findOrCreate('reports.view', 'web');
        $user->givePermissionTo($permission);

        $method = new ReflectionMethod(PermissionResource::class, 'renderDirectUsers');
        $method->setAccessible(true);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $rendered = (string) $method->invoke(null, $permission);

        $userQueries = array_values(array_filter(
            DB::getQueryLog(),
            fn (array $query): bool => str_contains($query['query'], 'users')
        ));

        expect($rendered)->toContain('Direct Dana')
            ->and($userQueries)->not->toBeEmpty()
            ->and(collect($userQueries)->every(fn (array $query): bool => ! str_contains($query['query'], 'password')))->toBeTrue();
    });

    it('bounds direct permission options', function (): void {
        $rows = [];

        for ($i = 0; $i < 505; $i++) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'name' => sprintf('bulk.perm.%04d', $i),
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('permissions')->insert($rows);

        expect(PermissionTabFactory::getDirectPermissionOptions('web'))
            ->toHaveCount(PermissionTabFactory::MAX_DIRECT_PERMISSION_OPTIONS);
    });
});

describe('policy escaping', function (): void {
    it('escapes hostile permission names and stays valid PHP', function (): void {
        $command = app(GeneratePoliciesCommand::class);

        // Quote in the subject segment; discovery-derived actions are fixed
        // identifiers, while subject names can contain quotes.
        $evil = "o'brien.view";

        $method = new ReflectionMethod($command, 'generatePolicyMethods');
        $method->setAccessible(true);
        $methods = $method->invoke($command, 'App\\Models\\Order', [$evil => 'Evil']);

        expect($methods)->toContain('can(' . var_export($evil, true) . ')');

        $path = tempnam(sys_get_temp_dir(), 'policy') . '.php';
        $generate = new ReflectionMethod($command, 'generatePolicy');
        $generate->setAccessible(true);
        $generate->invoke($command, 'App\\Models\\Order', [$evil => 'Evil'], $path);

        try {
            expect(Process::run([PHP_BINARY, '-l', $path])->successful())->toBeTrue();
        } finally {
            unlink($path);
        }
    });
});

describe('AUD#B2 config-only navigation', function (): void {
    it('reads the badge from config', function (): void {
        config()->set('filament-authz.navigation.badge', '5');

        expect(RoleResource::getNavigationBadge())->toBe('5');

        config()->set('filament-authz.navigation.badge', null);

        expect(RoleResource::getNavigationBadge())->toBeNull();
    });

    it('ignores fluent plugin navigation on the resource', function (): void {
        $panel = Panel::make()->id('badge-test');
        $panel->plugin(FilamentAuthzPlugin::make()->navigationBadge('X'));
        Filament::setCurrentPanel($panel);

        // applyConfigOverrides writes fluent navigation to config; reset it to
        // prove the resource never consults the plugin instance.
        config()->set('filament-authz.navigation.badge', null);

        expect(RoleResource::getNavigationBadge())->toBeNull();
    });
});
