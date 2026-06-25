<?php

declare(strict_types=1);

use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Membership\Tests\MembershipTestCase;

uses(MembershipTestCase::class);

it('maps admin role via spatieRoleName', function (): void {
    config()->set('membership.role_mapping', [
        'admin' => 'admin',
        'editor' => 'editor',
        'viewer' => 'viewer',
    ]);

    expect(MemberRole::Admin->spatieRoleName())->toBe('admin');
});

it('maps editor role via spatieRoleName', function (): void {
    expect(MemberRole::Editor->spatieRoleName())->toBe('editor');
});

it('maps viewer role via spatieRoleName', function (): void {
    expect(MemberRole::Viewer->spatieRoleName())->toBe('viewer');
});

it('maps back from spatie role name', function (): void {
    expect(MemberRole::fromSpatieRoleName('admin'))->toBe(MemberRole::Admin);
    expect(MemberRole::fromSpatieRoleName('editor'))->toBe(MemberRole::Editor);
    expect(MemberRole::fromSpatieRoleName('viewer'))->toBe(MemberRole::Viewer);
});

it('returns null for unknown spatie role name', function (): void {
    expect(MemberRole::fromSpatieRoleName('super_admin'))->toBeNull();
});

it('supports custom role mapping', function (): void {
    config()->set('membership.role_mapping', [
        'admin' => 'super_admin',
        'editor' => 'content_editor',
        'viewer' => 'read_only',
    ]);

    expect(MemberRole::Admin->spatieRoleName())->toBe('super_admin');
    expect(MemberRole::Editor->spatieRoleName())->toBe('content_editor');
    expect(MemberRole::Viewer->spatieRoleName())->toBe('read_only');
});

it('maps back from custom spatie role name', function (): void {
    config()->set('membership.role_mapping', [
        'admin' => 'super_admin',
        'editor' => 'content_editor',
        'viewer' => 'read_only',
    ]);

    expect(MemberRole::fromSpatieRoleName('super_admin'))->toBe(MemberRole::Admin);
    expect(MemberRole::fromSpatieRoleName('content_editor'))->toBe(MemberRole::Editor);
    expect(MemberRole::fromSpatieRoleName('read_only'))->toBe(MemberRole::Viewer);
});
