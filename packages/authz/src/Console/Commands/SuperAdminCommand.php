<?php

declare(strict_types=1);

namespace AIArmada\Authz\Console\Commands;

use AIArmada\Authz\Console\Concerns\Prohibitable;
use AIArmada\Authz\Models\Role;
use AIArmada\CommerceSupport\Support\ConnectionDriver;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\search;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * Assign super admin role to a user.
 *
 * Features:
 * - Search for existing users
 * - Create user if needed
 * - Multi-guard support
 */
class SuperAdminCommand extends Command
{
    use Prohibitable;

    protected $signature = 'authz:super-admin
        {--user= : ID of user to assign super admin role}
        {--create : Create a new user}
        {--panel= : Panel ID for guard configuration}';

    protected $description = 'Assign the super admin role to a user.';

    public function handle(): int
    {
        if (! $this->initializeProhibitable()) {
            return self::FAILURE;
        }

        $superAdminRole = (string) config('authz.super_admin_role', 'super_admin');
        $guards = (array) config('authz.guards', ['web']);
        $guard = $this->resolveGuard($guards);

        $user = $this->getOrCreateUser($guard);

        if ($user === null) {
            return self::FAILURE;
        }

        $registrar = app(PermissionRegistrar::class);
        $originalTeamId = $registrar->teams ? $registrar->getPermissionsTeamId() : null;

        try {
            if ($registrar->teams) {
                $registrar->setPermissionsTeamId(null);
            }

            foreach ($guards as $g) {
                Role::findOrCreate($superAdminRole, $g);
            }

            if (method_exists($user, 'assignRole')) {
                $user->assignRole($superAdminRole);
                info("✓ Assigned '{$superAdminRole}' role to user: {$this->getUserIdentifier($user)}");
            } else {
                warning('User model does not have HasRoles trait. Please add Spatie\\Permission\\Traits\\HasRoles to your User model.');

                return self::FAILURE;
            }
        } finally {
            if ($registrar->teams) {
                $registrar->setPermissionsTeamId($originalTeamId);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return (Authenticatable&Model)|null
     */
    protected function getOrCreateUser(string $guard): Authenticatable | Model | null
    {
        $userModel = $this->getUserModel($guard);

        if ($this->option('create')) {
            return $this->createUser($userModel);
        }

        $userId = $this->option('user');

        if ($userId !== null) {
            /** @var (Authenticatable&Model)|null $user */
            $user = $userModel::find($userId);

            if ($user === null) {
                warning("User with ID '{$userId}' not found.");

                return null;
            }

            return $user;
        }

        return $this->searchForUser($userModel);
    }

    /**
     * @param  class-string<Model&Authenticatable>  $userModel
     * @return (Authenticatable&Model)|null
     */
    protected function searchForUser(string $userModel): Authenticatable | Model | null
    {
        $emailColumn = $this->getEmailColumn($userModel);

        $userId = search(
            label: 'Search for a user by email',
            options: function (string $search) use ($userModel, $emailColumn): array {
                if (mb_strlen($search) < 2) {
                    return [];
                }

                $query = $userModel::query();
                $likeOperator = ConnectionDriver::name($query->getConnection()) === 'pgsql'
                    ? 'ILIKE'
                    : 'LIKE';

                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

                return $query
                    ->where($emailColumn, $likeOperator, "%{$escaped}%")
                    ->limit(10)
                    ->get()
                    ->mapWithKeys(fn ($user): array => [
                        $user->getKey() => $user->{$emailColumn},
                    ])
                    ->toArray();
            },
            placeholder: 'Type to search...',
        );

        if ($userId === '') {
            warning('No user selected.');

            return null;
        }

        /** @var (Authenticatable&Model)|null */
        return $userModel::find($userId);
    }

    /**
     * @param  class-string<Model&Authenticatable>  $userModel
     * @return (Authenticatable&Model)|null
     */
    protected function createUser(string $userModel): Authenticatable | Model | null
    {
        $emailColumn = $this->getEmailColumn($userModel);
        $nameColumn = $this->getNameColumn($userModel);

        $name = text(
            label: 'Name',
            required: true,
        );

        $email = text(
            label: 'Email',
            required: true,
            validate: fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ? 'Please enter a valid email address.'
                : null,
        );

        /** @var (Authenticatable&Model)|null $existingUser */
        $existingUser = $userModel::query()->where($emailColumn, $email)->first();

        if ($existingUser !== null) {
            warning("User with email '{$email}' already exists.");

            return $existingUser;
        }

        $password = password(
            label: 'Password',
            required: true,
            validate: fn (string $value): ?string => mb_strlen($value) < 8
                ? 'Password must be at least 8 characters.'
                : null,
        );

        /** @var Authenticatable&Model $user */
        $user = new $userModel;
        $user->{$nameColumn} = $name;
        $user->{$emailColumn} = $email;
        $user->setAttribute('password', Hash::make($password));

        try {
            $user->save();
        } catch (QueryException $exception) {
            /** @var (Authenticatable&Model)|null $racedUser */
            $racedUser = $userModel::query()->where($emailColumn, $email)->first();

            if ($racedUser !== null) {
                warning("User with email '{$email}' already exists.");

                return $racedUser;
            }

            throw $exception;
        }

        info("✓ Created user: {$email}");

        return $user;
    }

    /**
     * @param  list<string>  $guards
     */
    protected function resolveGuard(array $guards): string
    {
        $default = $guards[0] ?? 'web';
        $panel = $this->option('panel');

        if (! is_string($panel) || $panel === '') {
            return $default;
        }

        if (! class_exists(Filament::class)) {
            warning('Filament is not installed; ignoring --panel and using the default guard.');

            return $default;
        }

        $panelInstance = Filament::getPanel($panel, false);

        if ($panelInstance === null) {
            warning("Panel [{$panel}] not found; using the default guard.");

            return $default;
        }

        $panelGuard = $panelInstance->getAuthGuard();

        if (config("auth.guards.{$panelGuard}") === null) {
            warning("Panel [{$panel}] guard [{$panelGuard}] is not configured; using the default guard.");

            return $default;
        }

        return $panelGuard;
    }

    /**
     * @return class-string<Model&Authenticatable>
     */
    protected function getUserModel(string $guard): string
    {
        $provider = config("auth.guards.{$guard}.provider");

        /** @var class-string<Model&Authenticatable> */
        return config("auth.providers.{$provider}.model", 'App\\Models\\User');
    }

    /**
     * @param  class-string  $userModel
     */
    protected function getEmailColumn(string $userModel): string
    {
        return (string) config('authz.users.email_column', 'email');
    }

    /**
     * @param  class-string  $userModel
     */
    protected function getNameColumn(string $userModel): string
    {
        return (string) config('authz.users.name_column', 'name');
    }

    /**
     * @param  Authenticatable&Model  $user
     */
    protected function getUserIdentifier(Authenticatable $user): string
    {
        if ($user instanceof MustVerifyEmail) {
            return $user->getEmailForVerification();
        }

        return (string) $user->getAuthIdentifier();
    }
}
