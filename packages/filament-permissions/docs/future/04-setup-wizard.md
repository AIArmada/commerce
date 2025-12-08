# Future: Interactive Setup Wizard

> **One command to fully configure permissions — inspired by Shield's `shield:setup`**

## Overview

Shield's interactive setup command is one of its most user-friendly features. Our wizard goes further with guided configuration, automatic detection, and intelligent defaults.

## Shield's Setup Analysis

```bash
php artisan shield:setup [--fresh] [--tenant=] [--force] [--starred]
```

Shield's setup:
1. Publishes config and migrations
2. Runs migrations
3. Creates super admin role
4. Optionally configures tenancy
5. Generates initial permissions

## Our Enhanced Implementation

### 1. Setup Stages

```php
namespace AIArmada\FilamentPermissions\Enums;

enum SetupStage: string
{
    case Welcome = 'welcome';
    case Detection = 'detection';
    case Configuration = 'configuration';
    case Database = 'database';
    case Roles = 'roles';
    case Permissions = 'permissions';
    case Policies = 'policies';
    case UserSetup = 'user_setup';
    case Verification = 'verification';
    case Complete = 'complete';
}
```

### 2. Setup Wizard Command

```php
#[AsCommand(name: 'permissions:setup')]
class SetupCommand extends Command
{
    public $signature = 'permissions:setup
        {--fresh : Start completely fresh (drops tables)}
        {--force : Force setup without confirmations}
        {--minimal : Minimal setup without interactive prompts}
        {--tenant= : Configure for specific tenant model}
        {--panel= : Configure for specific panel}
        {--skip-policies : Skip policy generation}
        {--skip-permissions : Skip permission generation}';

    protected array $state = [];

    public function handle(): int
    {
        if ($this->isProhibited()) {
            return Command::FAILURE;
        }

        $this->welcome();
        
        // Detection phase
        $this->detectEnvironment();
        
        // Configuration phase
        $this->configurePackage();
        
        // Database phase
        $this->setupDatabase();
        
        // Roles phase
        $this->setupRoles();
        
        // Permissions phase
        $this->setupPermissions();
        
        // Policies phase
        $this->setupPolicies();
        
        // User setup phase
        $this->setupSuperAdmin();
        
        // Verification phase
        $this->verify();
        
        // Complete
        $this->complete();

        return Command::SUCCESS;
    }

    protected function welcome(): void
    {
        $this->newLine();
        $this->line('╔═══════════════════════════════════════════════════════════╗');
        $this->line('║                                                           ║');
        $this->line('║   🔐 Filament Permissions Setup Wizard                    ║');
        $this->line('║                                                           ║');
        $this->line('║   Enterprise-grade authorization for Filament             ║');
        $this->line('║                                                           ║');
        $this->line('╚═══════════════════════════════════════════════════════════╝');
        $this->newLine();
    }

    protected function detectEnvironment(): void
    {
        $this->info('🔍 Detecting environment...');
        $this->newLine();

        // Detect Spatie Permission
        $spatieInstalled = class_exists(\Spatie\Permission\PermissionServiceProvider::class);
        $this->displayDetection('Spatie Permission', $spatieInstalled, 'Required');
        
        if (!$spatieInstalled) {
            $this->error('Spatie Permission is required. Run: composer require spatie/laravel-permission');
            exit(1);
        }

        // Detect Filament panels
        $panels = collect(Filament::getPanels());
        $this->displayDetection('Filament Panels', $panels->isNotEmpty(), $panels->count() . ' found');
        $this->state['panels'] = $panels->keys()->toArray();

        // Detect existing config
        $hasConfig = file_exists(config_path('filament-permissions.php'));
        $this->displayDetection('Existing Config', $hasConfig, $hasConfig ? 'Will be updated' : 'Will be created');

        // Detect User model
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');
        $hasRoles = class_exists($userModel) && in_array(HasRoles::class, class_uses_recursive($userModel));
        $this->displayDetection('User Model HasRoles', $hasRoles, $userModel);
        $this->state['userModel'] = $userModel;
        $this->state['hasRoles'] = $hasRoles;

        // Detect tenancy
        $hasTenancy = $panels->some(fn ($panel) => $panel->hasTenancy());
        $this->displayDetection('Multi-Tenancy', $hasTenancy, $hasTenancy ? 'Enabled' : 'Not configured');
        $this->state['tenancy'] = $hasTenancy;

        // Detect guards
        $guards = array_keys(config('auth.guards', []));
        $this->displayDetection('Auth Guards', true, implode(', ', $guards));
        $this->state['guards'] = $guards;

        $this->newLine();
    }

    protected function configurePackage(): void
    {
        if ($this->option('minimal')) {
            $this->publishConfig();
            return;
        }

        $this->info('⚙️ Configuration');
        $this->newLine();

        // Super Admin Role
        $superAdminRole = text(
            label: 'What should the Super Admin role be called?',
            default: 'Super Admin',
            hint: 'This role bypasses all permission checks'
        );
        $this->state['superAdminRole'] = $superAdminRole;

        // Default Panel User Role
        $createPanelUser = confirm(
            label: 'Create a default Panel User role?',
            default: true,
            hint: 'Automatically assigned to new users for basic panel access'
        );
        $this->state['panelUserRole'] = $createPanelUser ? 'Panel User' : null;

        // Permission format
        $permissionFormat = select(
            label: 'Permission naming format:',
            options: [
                'dot' => 'Dot notation (user.viewAny)',
                'colon' => 'Colon notation (User:viewAny)',
                'underscore' => 'Underscore notation (user_viewAny)',
            ],
            default: 'dot'
        );
        $this->state['permissionFormat'] = $permissionFormat;

        // Features to enable
        $features = multiselect(
            label: 'Enable features:',
            options: [
                'hierarchies' => 'Permission Hierarchies',
                'temporal' => 'Temporal Permissions',
                'abac' => 'ABAC Policy Engine',
                'audit' => 'Audit Trail',
                'discovery' => 'Entity Discovery',
            ],
            default: ['hierarchies', 'audit', 'discovery']
        );
        $this->state['features'] = $features;

        // Panel configuration
        if (count($this->state['panels']) > 1) {
            $panelGuards = [];
            $panelRoles = [];
            
            foreach ($this->state['panels'] as $panelId) {
                $guard = select(
                    label: "Guard for '{$panelId}' panel:",
                    options: $this->state['guards']
                );
                $panelGuards[$panelId] = $guard;
                
                $roles = text(
                    label: "Roles allowed in '{$panelId}' panel (comma-separated):",
                    default: $superAdminRole
                );
                $panelRoles[$panelId] = array_map('trim', explode(',', $roles));
            }
            
            $this->state['panelGuards'] = $panelGuards;
            $this->state['panelRoles'] = $panelRoles;
        }

        $this->publishConfig();
        $this->newLine();
    }

    protected function setupDatabase(): void
    {
        $this->info('📦 Database Setup');
        $this->newLine();

        // Check for existing tables
        $hasExistingTables = Schema::hasTable('permissions');

        if ($hasExistingTables && !$this->option('fresh')) {
            $action = select(
                label: 'Permission tables already exist. What would you like to do?',
                options: [
                    'migrate' => 'Run new migrations only',
                    'fresh' => 'Drop and recreate all tables (DATA LOSS)',
                    'skip' => 'Skip database setup',
                ]
            );

            if ($action === 'skip') {
                $this->line('Database setup skipped.');
                return;
            }

            if ($action === 'fresh') {
                $this->call('migrate:fresh', ['--path' => 'vendor/spatie/laravel-permission/database/migrations']);
            }
        }

        // Publish and run Spatie migrations
        $this->call('vendor:publish', [
            '--provider' => 'Spatie\Permission\PermissionServiceProvider',
            '--tag' => 'permission-migrations',
        ]);

        // Publish and run our migrations
        $this->call('vendor:publish', [
            '--tag' => 'filament-permissions-migrations',
        ]);

        $this->call('migrate');

        $this->line('✓ Database migrations complete');
        $this->newLine();
    }

    protected function setupRoles(): void
    {
        $this->info('👥 Role Setup');
        $this->newLine();

        // Create Super Admin
        $superAdmin = Role::findOrCreate($this->state['superAdminRole']);
        $this->line("✓ Created role: {$superAdmin->name}");

        // Create Panel User if enabled
        if ($this->state['panelUserRole']) {
            $panelUser = Role::findOrCreate($this->state['panelUserRole']);
            $this->line("✓ Created role: {$panelUser->name}");
        }

        // Ask for additional roles
        if (!$this->option('minimal')) {
            $additionalRoles = text(
                label: 'Additional roles to create (comma-separated, leave empty to skip):',
                hint: 'e.g., Admin, Editor, Viewer'
            );

            if ($additionalRoles) {
                foreach (explode(',', $additionalRoles) as $roleName) {
                    $role = Role::findOrCreate(trim($roleName));
                    $this->line("✓ Created role: {$role->name}");
                }
            }
        }

        $this->newLine();
    }

    protected function setupPermissions(): void
    {
        if ($this->option('skip-permissions')) {
            return;
        }

        $this->info('🔑 Permission Discovery & Generation');
        $this->newLine();

        $discovery = app(EntityDiscoveryService::class);
        
        $resources = $discovery->discoverResources();
        $pages = $discovery->discoverPages();
        $widgets = $discovery->discoverWidgets();

        $this->line("Found {$resources->count()} resources, {$pages->count()} pages, {$widgets->count()} widgets");

        if (!$this->option('minimal') && !$this->option('force')) {
            $proceed = confirm('Generate permissions for discovered entities?', true);
            if (!$proceed) {
                return;
            }
        }

        $bar = $this->output->createProgressBar($resources->count() + $pages->count() + $widgets->count());

        // Generate resource permissions
        foreach ($resources as $resource) {
            $this->generateResourcePermissions($resource);
            $bar->advance();
        }

        // Generate page permissions
        foreach ($pages as $page) {
            Permission::findOrCreate($page['permission']);
            $bar->advance();
        }

        // Generate widget permissions
        foreach ($widgets as $widget) {
            Permission::findOrCreate($widget['permission']);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
    }

    protected function setupPolicies(): void
    {
        if ($this->option('skip-policies')) {
            return;
        }

        $this->info('📋 Policy Generation');
        $this->newLine();

        if (!$this->option('minimal')) {
            $policyType = select(
                label: 'Policy type to generate:',
                options: [
                    'composite' => 'Composite (Full features)',
                    'contextual' => 'Contextual (Team/Owner aware)',
                    'hierarchical' => 'Hierarchical (Permission groups)',
                    'basic' => 'Basic (Simple checks)',
                    'skip' => 'Skip policy generation',
                ]
            );

            if ($policyType === 'skip') {
                return;
            }

            $this->state['policyType'] = $policyType;
        }

        $this->call('permissions:policies', [
            '--type' => $this->state['policyType'] ?? 'composite',
            '--force' => $this->option('force'),
        ]);
    }

    protected function setupSuperAdmin(): void
    {
        $this->info('👑 Super Admin Assignment');
        $this->newLine();

        $existingUsers = User::count();

        if ($existingUsers === 0) {
            $this->line('No users exist yet. Super Admin can be assigned later.');
            return;
        }

        if ($this->option('minimal')) {
            return;
        }

        $assignNow = confirm('Assign Super Admin role to an existing user?', true);

        if (!$assignNow) {
            $this->line('Run `php artisan permissions:super-admin` later to assign.');
            return;
        }

        // Show user selection
        $users = User::limit(10)->get()->mapWithKeys(fn ($user) => [
            $user->id => "{$user->name} ({$user->email})"
        ])->toArray();

        $userId = select(
            label: 'Select user to become Super Admin:',
            options: $users
        );

        $user = User::find($userId);
        $user->assignRole($this->state['superAdminRole']);

        $this->line("✓ Assigned {$this->state['superAdminRole']} to {$user->name}");
        $this->newLine();
    }

    protected function verify(): void
    {
        $this->info('✔️ Verification');
        $this->newLine();

        $checks = [
            'Config published' => file_exists(config_path('filament-permissions.php')),
            'Migrations run' => Schema::hasTable('permissions'),
            'Super Admin role exists' => Role::where('name', $this->state['superAdminRole'])->exists(),
            'Permissions generated' => Permission::count() > 0,
        ];

        $allPassed = true;
        foreach ($checks as $check => $passed) {
            $icon = $passed ? '✓' : '✗';
            $color = $passed ? 'green' : 'red';
            $this->line("<fg={$color}>{$icon}</> {$check}");
            if (!$passed) {
                $allPassed = false;
            }
        }

        $this->newLine();

        if (!$allPassed) {
            $this->warn('Some checks failed. Review the output above.');
        }
    }

    protected function complete(): void
    {
        $this->newLine();
        $this->line('╔═══════════════════════════════════════════════════════════╗');
        $this->line('║                                                           ║');
        $this->line('║   ✅ Setup Complete!                                      ║');
        $this->line('║                                                           ║');
        $this->line('╚═══════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->info('Next steps:');
        $this->line('  1. Add HasRoles trait to your User model (if not already done)');
        $this->line('  2. Register FilamentPermissionsPlugin in your panel providers');
        $this->line('  3. Run `php artisan permissions:discover` to view all permissions');
        $this->line('  4. Run `php artisan permissions:doctor` to diagnose any issues');
        $this->newLine();

        $this->line('Useful commands:');
        $this->line('  • permissions:sync         — Sync permissions from config');
        $this->line('  • permissions:policies     — Generate Laravel policies');
        $this->line('  • permissions:super-admin  — Assign Super Admin role');
        $this->line('  • permissions:export       — Export permissions to JSON');
        $this->newLine();
    }

    protected function displayDetection(string $item, bool $status, string $detail): void
    {
        $icon = $status ? '✓' : '✗';
        $color = $status ? 'green' : 'yellow';
        $this->line("  <fg={$color}>{$icon}</> {$item}: {$detail}");
    }
}
```

### 3. Quick Setup Mode

```bash
# Full interactive setup
php artisan permissions:setup

# Minimal setup (no prompts)
php artisan permissions:setup --minimal

# Fresh setup (drops existing tables)
php artisan permissions:setup --fresh

# Force (no confirmations)
php artisan permissions:setup --force

# Quick setup for specific panel
php artisan permissions:setup --panel=admin --minimal
```

### 4. Configuration File Generation

The wizard generates a customized config based on user choices:

```php
protected function publishConfig(): void
{
    $config = [
        'guards' => $this->state['guards'],
        'user_model' => $this->state['userModel'],
        'super_admin_role' => $this->state['superAdminRole'],
        'panel_user_role' => $this->state['panelUserRole'],
        'permission_separator' => match ($this->state['permissionFormat']) {
            'dot' => '.',
            'colon' => ':',
            'underscore' => '_',
        },
        'features' => array_fill_keys($this->state['features'], true),
        'panel_guard_map' => $this->state['panelGuards'] ?? [],
        'panel_roles' => $this->state['panelRoles'] ?? [],
    ];

    $content = "<?php\n\nreturn " . var_export($config, true) . ";\n";
    
    file_put_contents(config_path('filament-permissions.php'), $content);
}
```

## Comparison with Shield

| Feature | Shield | Our Wizard |
|---------|--------|------------|
| **Interactive prompts** | Basic | Rich (multiselect, text, confirm) |
| **Environment detection** | Minimal | Comprehensive |
| **Multi-panel config** | Manual | Guided per-panel |
| **Feature selection** | All or nothing | Granular |
| **Policy generation** | Separate command | Integrated |
| **User assignment** | Separate command | Integrated |
| **Verification** | None | Built-in checks |
| **Resume capability** | No | Planned |
| **Undo/rollback** | No | Planned |
