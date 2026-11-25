<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\EntryPoints\Console;

use AIArmada\Authz\Features\Authorization\Actions\AssignGlobalSuperAdmin;
use AIArmada\Authz\Contracts\AdminUserProvisioner;
use AIArmada\CommerceSupport\Support\EloquentRecordReference;
use AIArmada\Authz\EntryPoints\Console\Concerns\Prohibitable;
use AIArmada\CommerceSupport\Support\LikeSearch;
use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

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

    public function handle(AssignGlobalSuperAdmin $assign, AdminUserProvisioner $create): int
    {
        if (! $this->initializeProhibitable()) {
            return self::FAILURE;
        }

        $superAdminRole = (string) config('authz.super_admin_role', 'super_admin');
        $guards = (array) config('authz.guards', ['web']);
        $guard = $this->resolveGuard($guards);

        if ($guard === null || ($this->option('create') && $this->option('user') !== null)) {
            $this->error('Select a valid guard and use either --create or --user.');

            return self::FAILURE;
        }

        $user = $this->getOrCreateUser($guard, $create);

        if ($user === null) {
            return self::FAILURE;
        }

        if (! method_exists($user, 'assignRole')) {
            warning('User model does not support role assignments.');

            return self::FAILURE;
        }

        $assign->execute($user, $guard, $superAdminRole);
        info("Assigned '{$superAdminRole}' role to user: {$this->getUserIdentifier($user)}");

        return self::SUCCESS;
    }

    /**
     * @return (Authenticatable&Model)|null
     */
    protected function getOrCreateUser(string $guard, AdminUserProvisioner $create): (Authenticatable & Model) | null
    {
        $userModel = $this->getUserModel($guard);

        if ($this->option('create')) {
            return $this->createUser($userModel, $create);
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
    protected function searchForUser(string $userModel): (Authenticatable & Model) | null
    {
        $emailColumn = $this->getEmailColumn($userModel);

        $userId = search(
            label: 'Search for a user by email',
            options: function (string $search) use ($userModel, $emailColumn): array {
                if (mb_strlen($search) < 2) {
                    return [];
                }

                $query = $userModel::query();
                $pattern = LikeSearch::contains($search);

                return LikeSearch::whereLike($query, $emailColumn, $pattern)
                    ->select([(new $userModel)->getKeyName(), $emailColumn])
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
    protected function createUser(string $userModel, AdminUserProvisioner $create): (Authenticatable & Model) | null
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

            return null;
        }

        $password = password(
            label: 'Password',
            required: true,
            validate: fn (string $value): ?string => mb_strlen($value) < 8
                ? 'Password must be at least 8 characters.'
                : null,
        );

        try {
            $reference = $create->execute($userModel, $nameColumn, $emailColumn, $name, $email, $password);
            $user = EloquentRecordReference::resolve($reference);
            if (! $user instanceof Model || ! $user instanceof Authenticatable) {
                throw new InvalidArgumentException('The provisioned record is not an authenticatable user.');
            }
        } catch (UniqueConstraintViolationException) {
            warning("User with email '{$email}' already exists. Use --user to select an existing account.");

            return null;
        }

        info("✓ Created user: {$email}");

        return $user;
    }

    /**
     * @param  list<string>  $guards
     */
    protected function resolveGuard(array $guards): ?string
    {
        $default = $guards[0] ?? 'web';
        $panel = $this->option('panel');

        if (! is_string($panel) || $panel === '') {
            return is_array(config("auth.guards.{$default}")) ? $default : null;
        }

        if (! class_exists(Filament::class)) {
            warning('Filament is not installed; the requested panel cannot be resolved.');

            return null;
        }

        $panelInstance = Filament::getPanel($panel, false);

        if ($panelInstance === null) {
            warning("Panel [{$panel}] not found.");

            return null;
        }

        $panelGuard = $panelInstance->getAuthGuard();

        if (config("auth.guards.{$panelGuard}") === null) {
            warning("Panel [{$panel}] guard [{$panelGuard}] is not configured.");

            return null;
        }

        return $panelGuard;
    }

    /**
     * @return class-string<Model&Authenticatable>
     */
    protected function getUserModel(string $guard): string
    {
        $provider = config("auth.guards.{$guard}.provider");

        $model = config("auth.providers.{$provider}.model");

        if (! is_string($model) || ! is_a($model, Model::class, true) || ! is_a($model, Authenticatable::class, true)) {
            throw new InvalidArgumentException("Auth guard [{$guard}] must use a configured Eloquent authenticatable model.");
        }

        return $model;
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
