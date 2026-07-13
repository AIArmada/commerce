<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocApproval;
use AIArmada\Docs\States\Overdue;
use AIArmada\Docs\States\Pending;
use AIArmada\Docs\States\Sent;
use AIArmada\FilamentDocs\Pages\AgingReportPage;
use AIArmada\FilamentDocs\Pages\PendingApprovalsPage;

uses(TestCase::class);

it('computes an aging summary across buckets', function (): void {
    Doc::factory()->create([
        'status' => Sent::class,
        'due_date' => now()->addDays(1),
        'total_minor' => 100,
    ]);

    Doc::factory()->create([
        'status' => Overdue::class,
        'due_date' => now()->subDays(10),
        'total_minor' => 200,
    ]);

    Doc::factory()->create([
        'status' => Pending::class,
        'due_date' => now()->subDays(40),
        'total_minor' => 300,
    ]);

    $page = app(AgingReportPage::class);
    $summary = $page->getAgingSummary();

    expect($summary['current']['count'])->toBe(1);
    expect($summary['current']['amount_minor'])->toBe(100);
    expect($summary['1-30']['count'])->toBe(1);
    expect($summary['1-30']['amount_minor'])->toBe(200);
    expect($summary['31-60']['count'])->toBe(1);
    expect($summary['31-60']['amount_minor'])->toBe(300);
});

it('scopes the aging summary to the current owner', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Aging Owner A',
        'email' => 'aging-owner-a@example.test',
        'password' => bcrypt('password'),
    ]);

    $ownerB = User::query()->create([
        'name' => 'Aging Owner B',
        'email' => 'aging-owner-b@example.test',
        'password' => bcrypt('password'),
    ]);

    OwnerContext::withOwner($ownerA, fn (): Doc => Doc::factory()->create([
        'status' => Sent::class,
        'due_date' => now()->addDay(),
        'total_minor' => 100,
    ]));

    OwnerContext::withOwner($ownerB, fn (): Doc => Doc::factory()->create([
        'status' => Sent::class,
        'due_date' => now()->addDay(),
        'total_minor' => 900,
    ]));

    $summary = OwnerContext::withOwner($ownerA, fn (): array => app(AgingReportPage::class)->getAgingSummary());

    expect($summary['current']['count'])->toBe(1);
    expect($summary['current']['amount_minor'])->toBe(100);
});

it('counts pending approvals for the current user', function (): void {
    expect(PendingApprovalsPage::getPendingApprovalsCount())->toBe(0);

    /** @var User $user */
    $user = User::query()->create([
        'name' => 'Approver',
        'email' => 'approver@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $doc = Doc::factory()->create();

    DocApproval::query()->create([
        'doc_id' => $doc->id,
        'requested_by' => (string) $user->id,
        'assigned_to' => (string) $user->id,
        'status' => 'pending',
        'comments' => null,
        'expires_at' => now()->addDays(7),
    ]);

    DocApproval::query()->create([
        'doc_id' => $doc->id,
        'requested_by' => (string) $user->id,
        'assigned_to' => (string) $user->id,
        'status' => 'approved',
        'comments' => null,
        'expires_at' => now()->addDays(7),
    ]);

    expect(PendingApprovalsPage::getPendingApprovalsCount())->toBe(1);
    expect(PendingApprovalsPage::getNavigationBadge())->toBe('1');
});

it('scopes pending approval counts to the current owner', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);

    /** @var User $ownerA */
    $ownerA = User::query()->create([
        'name' => 'Approval Owner A',
        'email' => 'approval-owner-a@example.test',
        'password' => bcrypt('password'),
    ]);

    /** @var User $ownerB */
    $ownerB = User::query()->create([
        'name' => 'Approval Owner B',
        'email' => 'approval-owner-b@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($ownerA);

    OwnerContext::withOwner($ownerA, function () use ($ownerA): void {
        $doc = Doc::factory()->create();

        DocApproval::query()->create([
            'doc_id' => $doc->id,
            'requested_by' => (string) $ownerA->id,
            'assigned_to' => (string) $ownerA->id,
            'status' => 'pending',
            'comments' => null,
            'expires_at' => now()->addDays(7),
        ]);
    });

    OwnerContext::withOwner($ownerB, function () use ($ownerA): void {
        $doc = Doc::factory()->create();

        DocApproval::query()->create([
            'doc_id' => $doc->id,
            'requested_by' => (string) $ownerA->id,
            'assigned_to' => (string) $ownerA->id,
            'status' => 'pending',
            'comments' => null,
            'expires_at' => now()->addDays(7),
        ]);
    });

    $count = OwnerContext::withOwner($ownerA, fn (): int => PendingApprovalsPage::getPendingApprovalsCount());

    expect($count)->toBe(1);
});
