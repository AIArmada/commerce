<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Organizations\OrganizationsTestCase;
use AIArmada\Membership\Actions\AddMemberAction;
use AIArmada\Membership\Enums\MemberRole;
use AIArmada\Organizations\Actions\CreateOrganizationAction;
use AIArmada\Organizations\Resolvers\CurrentOrganizationResolver;
use AIArmada\Organizations\Resolvers\RequestCurrentOrganizationResolver;
use Illuminate\Http\Request;

uses(OrganizationsTestCase::class);

test('request current organization resolver resolves membership-verified organization from header and query', function (): void {
    $creator = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();

    $organization = CreateOrganizationAction::make()->handle($creator, ['name' => 'Request Org']);
    AddMemberAction::make()->handle($organization, $member, MemberRole::Viewer);

    config(['organizations.resolver' => RequestCurrentOrganizationResolver::class]);
    app()->forgetInstance(CurrentOrganizationResolver::class);

    $resolver = app(CurrentOrganizationResolver::class);
    expect($resolver)->toBeInstanceOf(RequestCurrentOrganizationResolver::class);

    $request = Request::create('/org', 'GET');
    $request->headers->set('X-Organization-Id', (string) $organization->getKey());
    app()->instance('request', $request);

    $this->actingAs($member);
    expect($resolver->resolve()?->getKey())->toBe($organization->getKey());

    $this->actingAs($outsider);
    expect($resolver->resolve())->toBeNull();

    $queryRequest = Request::create('/org?organization_id=' . $organization->getKey(), 'GET');
    app()->instance('request', $queryRequest);
    $this->actingAs($creator);
    expect($resolver->resolve()?->getKey())->toBe($organization->getKey());
});
