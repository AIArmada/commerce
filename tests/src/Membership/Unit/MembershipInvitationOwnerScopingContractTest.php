<?php

declare(strict_types=1);

require_once __DIR__ . '/OwnerScopingContractsTest.php';

use AIArmada\Membership\Models\MembershipInvitation;
use AIArmada\Membership\Tests\MembershipTestCase;
use AIArmada\Membership\Tests\Unit\MembershipOwnerScopingContractTests;

trait MembershipInvitationOwnerScopingPestTests
{
    use MembershipOwnerScopingContractTests;

    protected function getModelClass(): string
    {
        return MembershipInvitation::class;
    }
}

uses(MembershipTestCase::class, MembershipInvitationOwnerScopingPestTests::class);

it('verifies the model uses the HasOwner trait', function (): void {
    $this->test_model_uses_has_owner_trait();
});

it('verifies the model can be assigned to an owner', function (): void {
    $this->test_model_can_be_assigned_owner();
});

it('verifies the model can be global', function (): void {
    $this->test_model_can_be_global();
});

it('verifies the for-owner scope filters by owner', function (): void {
    $this->test_for_owner_scope_filters_by_owner();
});

it('verifies the for-owner scope can include global records', function (): void {
    $this->test_for_owner_with_include_global_includes_global_records();
});

it('verifies the global-only scope returns only global records', function (): void {
    $this->test_global_only_scope_returns_only_global_records();
});

it('verifies owner context scopes queries', function (): void {
    $this->test_owner_context_with_owner_scopes_queries();
});

it('verifies assigning an owner to a persisted global record is rejected', function (): void {
    $this->test_assign_owner_sets_owner();
});

it('verifies removing an owner from a persisted record is rejected', function (): void {
    $this->test_remove_owner_throws_on_persisted_owned_record();
});

it('verifies removing an owner from an unsaved model is allowed', function (): void {
    $this->test_remove_owner_allowed_on_unsaved_model();
});

it('prevents cross-tenant access', function (): void {
    $this->test_cross_tenant_access_prevented();
});
