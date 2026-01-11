<?php

use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\FilamentAuthz\Models\PermissionRequest;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAuthz\Resources\PermissionRequestResource;
use AIArmada\FilamentDocs\Resources\DocResource;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->owner1 = User::create(['name' => 'Owner 1', 'email' => 'owner1@example.com', 'password' => 'password']);
    $this->owner2 = User::create(['name' => 'Owner 2', 'email' => 'owner2@example.com', 'password' => 'password']);
});

test('authz and docs navigation badges are owner-scoped', function () {
    // 1. Setup data for Owner 1
    OwnerContext::withOwner($this->owner1, function () {
        // Permission Request
        PermissionRequest::create([
            'requester_id' => $this->owner1->id,
            'justification' => 'Need access',
            'status' => 'pending',
        ]);

        // Doc
        Doc::create([
            'doc_number' => 'DOC-1',
            'doc_type' => 'invoice',
            'status' => DocStatus::DRAFT,
            'issue_date' => now(),
            'subtotal' => '100.00',
            'tax_amount' => '0.00',
            'discount_amount' => '0.00',
            'total' => '100.00',
            'currency' => 'MYR',
            'owner_type' => $this->owner1->getMorphClass(),
            'owner_id' => $this->owner1->id,
        ]);
    });

    // 2. Setup data for Owner 2
    OwnerContext::withOwner($this->owner2, function () {
        // 2 Permission Requests
        PermissionRequest::create([
            'requester_id' => $this->owner2->id,
            'justification' => 'Need access 1',
            'status' => 'pending',
        ]);
        PermissionRequest::create([
            'requester_id' => $this->owner2->id,
            'justification' => 'Need access 2',
            'status' => 'pending',
        ]);

        // 2 Docs
        Doc::create([
            'doc_number' => 'DOC-2',
            'doc_type' => 'invoice',
            'status' => DocStatus::DRAFT,
            'issue_date' => now(),
            'subtotal' => '200.00',
            'tax_amount' => '0.00',
            'discount_amount' => '0.00',
            'total' => '200.00',
            'currency' => 'MYR',
            'owner_type' => $this->owner2->getMorphClass(),
            'owner_id' => $this->owner2->id,
        ]);
        Doc::create([
            'doc_number' => 'DOC-3',
            'doc_type' => 'invoice',
            'status' => DocStatus::DRAFT,
            'issue_date' => now(),
            'subtotal' => '300.00',
            'tax_amount' => '0.00',
            'discount_amount' => '0.00',
            'total' => '300.00',
            'currency' => 'MYR',
            'owner_type' => $this->owner2->getMorphClass(),
            'owner_id' => $this->owner2->id,
        ]);
    });

    // 3. Assert for Owner 1
    OwnerContext::withOwner($this->owner1, function () {
        expect(PermissionRequestResource::getNavigationBadge())->toBe('1');
        expect(DocResource::getNavigationBadge())->toBe('1');
    });

    // 4. Assert for Owner 2
    OwnerContext::withOwner($this->owner2, function () {
        expect(PermissionRequestResource::getNavigationBadge())->toBe('2');
        expect(DocResource::getNavigationBadge())->toBe('2');
    });
});
