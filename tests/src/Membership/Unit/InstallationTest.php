<?php

declare(strict_types=1);

use AIArmada\Membership\MembershipServiceProvider;
use AIArmada\Membership\Models\MembershipApplication;
use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Tests\MembershipTestCase;

uses(MembershipTestCase::class);

it('registers the membership service provider', function (): void {
    $provider = app()->register(MembershipServiceProvider::class);

    expect($provider)->toBeInstanceOf(MembershipServiceProvider::class);
});

it('creates membership applications table', function (): void {
    expect(app('db')->getSchemaBuilder()->hasTable('membership_applications'))->toBeTrue();
});

it('creates membership invitations table', function (): void {
    expect(app('db')->getSchemaBuilder()->hasTable('membership_invitations'))->toBeTrue();
});

it('has membership application model', function (): void {
    expect(class_exists(MembershipApplication::class))->toBeTrue();
});

it('has membership invitation model', function (): void {
    expect(class_exists(MembershipInvitation::class))->toBeTrue();
});
