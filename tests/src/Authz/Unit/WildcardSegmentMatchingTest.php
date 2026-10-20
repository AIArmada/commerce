<?php

declare(strict_types=1);

use AIArmada\Authz\Models\Permission;
use AIArmada\Authz\Services\WildcardPermissionResolver;
use AIArmada\Commerce\Tests\Fixtures\Models\User;

/**
 * Regression coverage for AZ-03: every wildcard segment participates in the
 * match, literal regex characters are quoted, and the configured permission
 * separator is honored.
 */
function az03User(string $email): User
{
    return User::query()->create([
        'name' => 'AZ-03 User',
        'email' => $email,
        'password' => 'secret',
    ]);
}

it('matches exact permissions and the universal wildcard', function (): void {
    $resolver = new WildcardPermissionResolver;

    expect($resolver->matches('orders.view', 'orders.view'))->toBeTrue()
        ->and($resolver->matches('orders.view', 'orders.create'))->toBeFalse()
        ->and($resolver->matches('*', 'orders.view'))->toBeTrue()
        ->and($resolver->matches('*', 'orders'))->toBeTrue()
        ->and($resolver->matches('orders.view', 'orders.view.own'))->toBeFalse();
});

it('evaluates wildcards in every segment position', function (): void {
    $resolver = new WildcardPermissionResolver;

    expect($resolver->matches('orders.*', 'orders.view'))->toBeTrue()
        ->and($resolver->matches('orders.*', 'orders.view.own'))->toBeTrue()
        ->and($resolver->matches('orders.*', 'orders'))->toBeFalse()
        ->and($resolver->matches('orders.*', 'users.view'))->toBeFalse();

    expect($resolver->matches('*.view', 'orders.view'))->toBeTrue()
        ->and($resolver->matches('*.view', 'products.view'))->toBeTrue()
        ->and($resolver->matches('*.view', 'orders.create'))->toBeFalse()
        ->and($resolver->matches('*.view', 'orders.view.own'))->toBeFalse();

    expect($resolver->matches('orders.*.own', 'orders.view.own'))->toBeTrue()
        ->and($resolver->matches('orders.*.own', 'orders.create.own'))->toBeTrue()
        ->and($resolver->matches('orders.*.own', 'orders.view.read.own'))->toBeFalse()
        ->and($resolver->matches('orders.*.own', 'users.view.own'))->toBeFalse();
});

it('evaluates multiple wildcard segments together', function (): void {
    $resolver = new WildcardPermissionResolver;

    expect($resolver->matches('*.view.*', 'orders.view.own'))->toBeTrue()
        ->and($resolver->matches('*.view.*', 'orders.view.own.deep'))->toBeTrue()
        ->and($resolver->matches('*.view.*', 'products.view.own'))->toBeTrue()
        ->and($resolver->matches('*.view.*', 'orders.view'))->toBeFalse()
        ->and($resolver->matches('*.view.*', 'orders.edit.own'))->toBeFalse();

    expect($resolver->matches('*.*', 'orders.view'))->toBeTrue()
        ->and($resolver->matches('*.*', 'orders.view.own'))->toBeTrue()
        ->and($resolver->matches('*.*', 'orders'))->toBeFalse();
});

it('quotes literal regex punctuation in patterns', function (): void {
    $resolver = new WildcardPermissionResolver;

    expect($resolver->matches('reports.q1-2026.*', 'reports.q1-2026.view'))->toBeTrue()
        ->and($resolver->matches('reports.(final).*', 'reports.(final).view'))->toBeTrue()
        ->and($resolver->matches('reports.(final).*', 'reports.final.view'))->toBeFalse()
        ->and($resolver->matches('orders.view+own.*', 'orders.view+own.deep'))->toBeTrue()
        ->and($resolver->matches('orders.view+own.*', 'orders.viewXown.deep'))->toBeFalse();
});

it('honors the configured permission separator', function (): void {
    config()->set('authz.permissions.separator', ':');

    $resolver = new WildcardPermissionResolver;

    expect($resolver->matches('orders:*', 'orders:view:own'))->toBeTrue()
        ->and($resolver->matches('orders:*', 'orders:view'))->toBeTrue()
        ->and($resolver->matches('orders:*', 'orders'))->toBeFalse()
        ->and($resolver->matches('*:view', 'orders:view'))->toBeTrue()
        ->and($resolver->matches('orders:*', 'orders.view'))->toBeFalse()
        ->and($resolver->matches('*:view:*', 'orders:view:own'))->toBeTrue()
        ->and($resolver->matches('*:view:*', 'orders:view'))->toBeFalse();
});

it('authorizes gate checks through multi-segment patterns', function (): void {
    $user = az03User('az03-gate@example.com');

    Permission::findOrCreate('orders.view.own', 'web');

    $user->givePermissionTo(Permission::findOrCreate('*.view.*', 'web'));

    expect($user->can('orders.view.own'))->toBeTrue();
});

it('rejects gate near-misses for multi-segment patterns', function (): void {
    $user = az03User('az03-near-miss@example.com');

    Permission::findOrCreate('orders.view.own', 'web');
    Permission::findOrCreate('orders.view.deep.own', 'web');
    Permission::findOrCreate('orders.edit', 'web');

    $user->givePermissionTo(Permission::findOrCreate('orders.*.own', 'web'));

    expect($user->can('orders.view.own'))->toBeTrue()
        ->and($user->can('orders.view.deep.own'))->toBeFalse()
        ->and($user->can('orders.edit'))->toBeFalse();
});
