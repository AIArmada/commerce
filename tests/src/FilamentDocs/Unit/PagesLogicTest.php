<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
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
        'total' => 100,
    ]);

    Doc::factory()->create([
        'status' => Overdue::class,
        'due_date' => now()->subDays(10),
        'total' => 200,
    ]);

    Doc::factory()->create([
        'status' => Pending::class,
        'due_date' => now()->subDays(40),
        'total' => 300,
    ]);

    $page = app(AgingReportPage::class);
    $summary = $page->getAgingSummary();

    expect($summary['current']['count'])->toBe(1);
    expect((float) $summary['current']['amount'])->toBe(100.0);
    expect($summary['1-30']['count'])->toBe(1);
    expect((float) $summary['1-30']['amount'])->toBe(200.0);
    expect($summary['31-60']['count'])->toBe(1);
    expect((float) $summary['31-60']['amount'])->toBe(300.0);
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
