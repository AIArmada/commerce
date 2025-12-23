<?php

declare(strict_types=1);

namespace Tests\FilamentAuthz\Unit;

use AIArmada\FilamentAuthz\Models\Role;
use AIArmada\FilamentAuthz\Services\IdentityProviderSync;
use Illuminate\Support\Collection;
use Mockery;
use ReflectionClass;

afterEach(function (): void {
    Mockery::close();
});

describe('IdentityProviderSync', function (): void {
    describe('constructor', function (): void {
        it('initializes with default values', function (): void {
            $service = new IdentityProviderSync;

            $reflection = new ReflectionClass($service);
            $typeProperty = $reflection->getProperty('providerType');
            $nameProperty = $reflection->getProperty('providerName');

            expect($typeProperty->getValue($service))->toBe('ldap');
            expect($nameProperty->getValue($service))->toBe('default');
        });
    });

    describe('setProviderType', function (): void {
        it('sets the provider type', function (): void {
            $service = new IdentityProviderSync;
            $result = $service->setProviderType('saml');

            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('providerType');

            expect($property->getValue($service))->toBe('saml');
            expect($result)->toBe($service);
        });

        it('returns self for method chaining', function (): void {
            $service = new IdentityProviderSync;
            $result = $service->setProviderType('oauth');

            expect($result)->toBeInstanceOf(IdentityProviderSync::class);
        });
    });

    describe('setProviderName', function (): void {
        it('sets the provider name', function (): void {
            $service = new IdentityProviderSync;
            $result = $service->setProviderName('corporate-ad');

            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('providerName');

            expect($property->getValue($service))->toBe('corporate-ad');
            expect($result)->toBe($service);
        });

        it('returns self for method chaining', function (): void {
            $service = new IdentityProviderSync;
            $result = $service->setProviderName('google');

            expect($result)->toBeInstanceOf(IdentityProviderSync::class);
        });
    });

    describe('setMapping', function (): void {
        it('sets the group to role mapping', function (): void {
            $service = new IdentityProviderSync;
            $mapping = ['Admins' => 'admin', 'Users' => 'user'];

            $result = $service->setMapping($mapping);

            $reflection = new ReflectionClass($service);
            $property = $reflection->getProperty('groupToRoleMapping');

            expect($property->getValue($service))->toBe($mapping);
            expect($result)->toBe($service);
        });
    });

    describe('parseLdapGroups', function (): void {
        it('parses CN from LDAP DN strings', function (): void {
            $service = new IdentityProviderSync;
            $attributes = [
                'memberof' => [
                    'CN=Admins,OU=Groups,DC=example,DC=com',
                    'CN=Users,OU=Groups,DC=example,DC=com',
                ],
            ];

            $groups = $service->parseLdapGroups($attributes);

            expect($groups)->toBe(['Admins', 'Users']);
        });

        it('handles memberOf case variation', function (): void {
            $service = new IdentityProviderSync;
            $attributes = [
                'memberOf' => 'CN=Developers,OU=Groups,DC=example,DC=com',
            ];

            $groups = $service->parseLdapGroups($attributes);

            expect($groups)->toBe(['Developers']);
        });

        it('handles string memberof', function (): void {
            $service = new IdentityProviderSync;
            $attributes = [
                'memberof' => 'CN=SingleGroup,OU=Groups,DC=example,DC=com',
            ];

            $groups = $service->parseLdapGroups($attributes);

            expect($groups)->toBe(['SingleGroup']);
        });

        it('returns empty array when no memberof attribute', function (): void {
            $service = new IdentityProviderSync;
            $attributes = ['name' => 'John'];

            $groups = $service->parseLdapGroups($attributes);

            expect($groups)->toBe([]);
        });

        it('handles empty memberof', function (): void {
            $service = new IdentityProviderSync;
            $attributes = ['memberof' => []];

            $groups = $service->parseLdapGroups($attributes);

            expect($groups)->toBe([]);
        });
    });

    describe('parseSamlGroups', function (): void {
        it('parses groups from Microsoft schema', function (): void {
            $service = new IdentityProviderSync;
            $assertion = [
                'http://schemas.microsoft.com/ws/2008/06/identity/claims/groups' => ['Admin', 'Developer'],
            ];

            $groups = $service->parseSamlGroups($assertion);

            expect($groups)->toBe(['Admin', 'Developer']);
        });

        it('parses groups from simple groups attribute', function (): void {
            $service = new IdentityProviderSync;
            $assertion = [
                'groups' => ['Marketing', 'Sales'],
            ];

            $groups = $service->parseSamlGroups($assertion);

            expect($groups)->toBe(['Marketing', 'Sales']);
        });

        it('parses groups from memberOf attribute', function (): void {
            $service = new IdentityProviderSync;
            $assertion = [
                'memberOf' => ['Engineering'],
            ];

            $groups = $service->parseSamlGroups($assertion);

            expect($groups)->toBe(['Engineering']);
        });

        it('parses groups from Group attribute', function (): void {
            $service = new IdentityProviderSync;
            $assertion = [
                'Group' => ['Support'],
            ];

            $groups = $service->parseSamlGroups($assertion);

            expect($groups)->toBe(['Support']);
        });

        it('handles single string group value', function (): void {
            $service = new IdentityProviderSync;
            $assertion = [
                'groups' => 'SingleGroup',
            ];

            $groups = $service->parseSamlGroups($assertion);

            expect($groups)->toBe(['SingleGroup']);
        });

        it('returns empty array when no group attribute found', function (): void {
            $service = new IdentityProviderSync;
            $assertion = [
                'email' => 'user@example.com',
            ];

            $groups = $service->parseSamlGroups($assertion);

            expect($groups)->toBe([]);
        });
    });

    describe('loadMappings', function (): void {
        it('returns self when table does not exist', function (): void {
            $service = new IdentityProviderSync;

            // The table doesn't exist in test database
            $result = $service->loadMappings();

            expect($result)->toBe($service);
        });
    });

    describe('saveMapping', function (): void {
        it('attempts to save mapping', function (): void {
            $service = new IdentityProviderSync;

            // Result depends on whether table exists
            $result = $service->saveMapping('Admins', 'admin');

            expect($result)->toBeBool();
        });
    });

    describe('deleteMapping', function (): void {
        it('returns false when table does not exist', function (): void {
            $service = new IdentityProviderSync;

            $result = $service->deleteMapping('Admins');

            expect($result)->toBeFalse();
        });
    });

    describe('getAllMappings', function (): void {
        it('returns empty collection when table does not exist', function (): void {
            $service = new IdentityProviderSync;

            $result = $service->getAllMappings();

            expect($result)->toBeInstanceOf(Collection::class);
            expect($result)->toBeEmpty();
        });
    });

    describe('syncUserRoles', function (): void {
        it('skips unmapped groups', function (): void {
            $user = new class
            {
                public function hasRole(string $role): bool
                {
                    return false;
                }

                public function assignRole($role): void {}
            };

            $service = new IdentityProviderSync;
            $service->setMapping([]);

            $result = $service->syncUserRoles($user, ['UnmappedGroup1', 'UnmappedGroup2']);

            expect($result['assigned'])->toBe([]);
            expect($result['skipped'])->toBe(['UnmappedGroup1', 'UnmappedGroup2']);
        });

        it('assigns role when mapping exists and role exists', function (): void {
            // Create a role
            Role::create(['name' => 'admin-sync-test', 'guard_name' => 'web']);

            // Create a subclass that doesn't call loadMappings in syncUserRoles
            // to test the actual role assignment logic
            $service = new class extends IdentityProviderSync
            {
                public function loadMappings(): self
                {
                    // Don't overwrite manually set mappings
                    return $this;
                }
            };

            // Create a mock user with hasRole and assignRole methods
            $user = new class
            {
                public array $roles = [];

                public function hasRole(string $role): bool
                {
                    return in_array($role, $this->roles);
                }

                public function assignRole($role): void
                {
                    if (is_object($role)) {
                        $this->roles[] = $role->name;
                    } else {
                        $this->roles[] = $role;
                    }
                }
            };

            $service->setMapping(['AdminGroup' => 'admin-sync-test']);

            $result = $service->syncUserRoles($user, ['AdminGroup']);

            expect($result['assigned'])->toContain('admin-sync-test');
            expect($result['skipped'])->toBe([]);
        });

        it('skips already assigned roles', function (): void {
            Role::create(['name' => 'user', 'guard_name' => 'web']);

            $user = new class
            {
                public function hasRole(string $role): bool
                {
                    return $role === 'user'; // Already has user role
                }

                public function assignRole($role): void {}
            };

            $service = new IdentityProviderSync;
            $service->setMapping(['Users' => 'user']);

            $result = $service->syncUserRoles($user, ['Users']);

            expect($result['assigned'])->toBe([]);
        });

        it('skips groups when role does not exist', function (): void {
            $user = new class
            {
                public function hasRole(string $role): bool
                {
                    return false;
                }

                public function assignRole($role): void {}
            };

            $service = new IdentityProviderSync;
            // Mapping exists but role doesn't exist in DB
            $service->setMapping(['NonExistentGroup' => 'non-existent-role']);

            $result = $service->syncUserRoles($user, ['NonExistentGroup']);

            // Should skip because role doesn't exist
            expect($result['assigned'])->toBe([]);
        });
    });

    describe('method chaining', function (): void {
        it('supports fluent interface', function (): void {
            $service = new IdentityProviderSync;

            $result = $service
                ->setProviderType('saml')
                ->setProviderName('corporate')
                ->setMapping(['Admin' => 'admin']);

            expect($result)->toBeInstanceOf(IdentityProviderSync::class);

            $reflection = new ReflectionClass($result);
            expect($reflection->getProperty('providerType')->getValue($result))->toBe('saml');
            expect($reflection->getProperty('providerName')->getValue($result))->toBe('corporate');
            expect($reflection->getProperty('groupToRoleMapping')->getValue($result))->toBe(['Admin' => 'admin']);
        });
    });
});
