<?php

declare(strict_types=1);

use AIArmada\Authz\Models\Permission;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\Enums\DocApprovalStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocApproval;
use AIArmada\FilamentDocs\Pages\AgingReportPage;
use AIArmada\FilamentDocs\Pages\PendingApprovalsPage;
use AIArmada\FilamentDocs\Resources\DocEmailTemplateResource;
use AIArmada\FilamentDocs\Resources\DocResource;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\ApprovalsRelationManager;
use AIArmada\FilamentDocs\Resources\DocSequenceResource;
use AIArmada\FilamentDocs\Resources\DocTemplateResource;

uses(TestCase::class);

beforeEach(function (): void {
    User::query()->delete();
    Permission::query()->delete();
});

function filamentDocs_userWithAbilities(array $abilities): User
{
    /** @var User $user */
    $user = User::query()->create([
        'name' => 'Docs User',
        'email' => 'docs-user-' . Str::uuid() . '@example.test',
        'password' => bcrypt('password'),
    ]);

    foreach ($abilities as $ability) {
        Permission::query()->firstOrCreate(['name' => $ability, 'guard_name' => 'web']);
        $user->givePermissionTo($ability);
    }

    return $user;
}

it('denies document writes to read-only viewers (C1)', function (): void {
    $user = filamentDocs_userWithAbilities(['document.viewAny']);
    $this->actingAs($user);

    $doc = Doc::factory()->make();

    expect(DocResource::canViewAny())->toBeTrue();
    expect(DocResource::canCreate())->toBeFalse();
    expect(DocResource::canEdit($doc))->toBeFalse();
    expect(DocResource::canDelete($doc))->toBeFalse();
});

it('gates each document action on its own ability only (C1)', function (): void {
    $doc = Doc::factory()->make();

    $user = filamentDocs_userWithAbilities(['document.create']);
    $this->actingAs($user);
    expect(DocResource::canCreate())->toBeTrue();
    expect(DocResource::canEdit($doc))->toBeFalse();
    expect(DocResource::canDelete($doc))->toBeFalse();

    $editor = filamentDocs_userWithAbilities(['document.update']);
    $this->actingAs($editor);
    expect(DocResource::canEdit($doc))->toBeTrue();
    expect(DocResource::canCreate())->toBeFalse();
    expect(DocResource::canDelete($doc))->toBeFalse();

    $deleter = filamentDocs_userWithAbilities(['document.delete']);
    $this->actingAs($deleter);
    expect(DocResource::canDelete($doc))->toBeTrue();
    expect(DocResource::canCreate())->toBeFalse();
    expect(DocResource::canEdit($doc))->toBeFalse();
});

it('uses dedicated per-resource ability namespaces (C1/H1)', function (): void {
    $user = filamentDocs_userWithAbilities(['document.viewAny']);
    $this->actingAs($user);

    $doc = Doc::factory()->make();

    // Template / sequence / email-template resources must not honor document.*.
    expect(DocTemplateResource::canViewAny())->toBeFalse();
    expect(DocTemplateResource::canCreate())->toBeFalse();
    expect(DocTemplateResource::canEdit($doc))->toBeFalse();
    expect(DocTemplateResource::canDelete($doc))->toBeFalse();
    expect(DocSequenceResource::canViewAny())->toBeFalse();
    expect(DocSequenceResource::canCreate())->toBeFalse();
    expect(DocEmailTemplateResource::canViewAny())->toBeFalse();
    expect(DocEmailTemplateResource::canCreate())->toBeFalse();

    // Each resource honors its own namespace.
    $templateUser = filamentDocs_userWithAbilities(['document_template.viewAny', 'document_template.create']);
    $this->actingAs($templateUser);
    expect(DocTemplateResource::canViewAny())->toBeTrue();
    expect(DocTemplateResource::canCreate())->toBeTrue();
    expect(DocTemplateResource::canEdit($doc))->toBeFalse();

    $sequenceUser = filamentDocs_userWithAbilities(['document_sequence.viewAny']);
    $this->actingAs($sequenceUser);
    expect(DocSequenceResource::canViewAny())->toBeTrue();
    expect(DocSequenceResource::canCreate())->toBeFalse();

    $emailUser = filamentDocs_userWithAbilities(['document_email_template.viewAny']);
    $this->actingAs($emailUser);
    expect(DocEmailTemplateResource::canViewAny())->toBeTrue();
    expect(DocEmailTemplateResource::canCreate())->toBeFalse();
});

it('does not grant document access from legacy purchase abilities (H1)', function (): void {
    $user = filamentDocs_userWithAbilities(['purchase.viewAny', 'purchase.view', 'purchase.create', 'purchase.update', 'purchase.delete']);
    $this->actingAs($user);

    $doc = Doc::factory()->make();

    expect(DocResource::canViewAny())->toBeFalse();
    expect(DocResource::canView($doc))->toBeFalse();
    expect(DocResource::canCreate())->toBeFalse();
    expect(DocResource::canEdit($doc))->toBeFalse();
    expect(DocResource::canDelete($doc))->toBeFalse();
    expect(AgingReportPage::canAccess())->toBeFalse();
    expect(PendingApprovalsPage::canAccess())->toBeFalse();
});

it('gates report pages on document abilities (H1)', function (): void {
    $user = filamentDocs_userWithAbilities(['document.viewAny']);
    $this->actingAs($user);

    expect(AgingReportPage::canAccess())->toBeTrue();
    expect(PendingApprovalsPage::canAccess())->toBeFalse();

    $approver = filamentDocs_userWithAbilities(['document_approval.viewAny']);
    $this->actingAs($approver);

    expect(PendingApprovalsPage::canAccess())->toBeTrue();
});

it('requires an explicit approval ability for unassigned approvals (M5)', function (): void {
    $method = new ReflectionMethod(ApprovalsRelationManager::class, 'userCanActOnApproval');

    $doc = Doc::factory()->create();

    $unassigned = DocApproval::query()->create([
        'doc_id' => $doc->id,
        'requested_by' => 'someone-else',
        'assigned_to' => null,
        'status' => DocApprovalStatus::Pending,
    ]);

    $viewer = filamentDocs_userWithAbilities(['document.viewAny']);
    $this->actingAs($viewer);
    expect($method->invoke(null, $unassigned))->toBeFalse();

    $approver = filamentDocs_userWithAbilities(['document_approval.approve']);
    $this->actingAs($approver);
    expect($method->invoke(null, $unassigned))->toBeTrue();

    // Guests can never act.
    auth()->logout();
    expect($method->invoke(null, $unassigned))->toBeFalse();
});

it('still lets the assigned user act without the approval ability (M5)', function (): void {
    $method = new ReflectionMethod(ApprovalsRelationManager::class, 'userCanActOnApproval');

    $user = filamentDocs_userWithAbilities(['document.viewAny']);
    $this->actingAs($user);

    $doc = Doc::factory()->create();

    $assigned = DocApproval::query()->create([
        'doc_id' => $doc->id,
        'requested_by' => 'someone-else',
        'assigned_to' => (string) $user->getKey(),
        'status' => DocApprovalStatus::Pending,
    ]);

    expect($method->invoke(null, $assigned))->toBeTrue();

    $other = filamentDocs_userWithAbilities(['document.viewAny']);
    $this->actingAs($other);

    expect($method->invoke(null, $assigned))->toBeFalse();
});
