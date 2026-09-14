<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocApproval;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Overdue;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\Sent;
use AIArmada\FilamentDocs\Pages\AgingReportPage;
use AIArmada\FilamentDocs\Pages\PendingApprovalsPage;
use AIArmada\FilamentDocs\Resources\DocTemplateResource;
use AIArmada\FilamentDocs\Widgets\DocStatsWidget;
use AIArmada\FilamentDocs\Widgets\StatusBreakdownWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(TestCase::class);

function filamentDocs_countQueries(callable $callback): int
{
    DB::enableQueryLog();
    DB::flushQueryLog();

    $callback();

    $count = count(DB::getQueryLog());

    DB::flushQueryLog();
    DB::disableQueryLog();

    return $count;
}

function filamentDocs_invokeWidgetMethod(object $instance, string $methodName): mixed
{
    return (new ReflectionMethod($instance, $methodName))->invoke($instance);
}

it('serves repeat aging summaries from cache (M3)', function (): void {
    Cache::flush();

    Doc::factory()->create([
        'status' => Sent::class,
        'due_date' => now()->addDay(),
        'total_minor' => 100,
    ]);

    $page = app(AgingReportPage::class);

    $first = filamentDocs_countQueries(fn (): array => $page->getAgingSummary());
    $second = filamentDocs_countQueries(fn (): array => $page->getAgingSummary());

    expect($first)->toBeGreaterThan(0);
    expect($second)->toBe(0);
});

it('loads doc stats with a single aggregate query (L1)', function (): void {
    Doc::factory()->count(2)->create(['status' => Draft::class]);
    Doc::factory()->create(['status' => Paid::class, 'total_minor' => 100, 'paid_at' => now()]);
    Doc::factory()->create(['status' => Overdue::class, 'total_minor' => 50]);

    $widget = app(DocStatsWidget::class);

    $queries = filamentDocs_countQueries(fn (): array => filamentDocs_invokeWidgetMethod($widget, 'getStats'));

    expect($queries)->toBe(1);

    $stats = filamentDocs_invokeWidgetMethod($widget, 'getStats');

    expect($stats[0]->getValue())->toBe(4);
    expect($stats[1]->getValue())->toBe(2);
    expect($stats[3]->getValue())->toBe(1);
    expect($stats[4]->getValue())->toBe(1);
});

it('loads the status breakdown with a single grouped query (L1)', function (): void {
    Doc::factory()->create(['status' => Draft::class]);
    Doc::factory()->create(['status' => Paid::class]);

    $widget = app(StatusBreakdownWidget::class);

    $queries = filamentDocs_countQueries(fn (): array => filamentDocs_invokeWidgetMethod($widget, 'getData'));

    expect($queries)->toBe(1);
});

it('caches the template navigation badge (L1)', function (): void {
    Cache::flush();

    $queries = filamentDocs_countQueries(function (): void {
        DocTemplateResource::getNavigationBadge();
        DocTemplateResource::getNavigationBadge();
    });

    expect($queries)->toBe(1);
});

it('caches the pending approvals count across badge and blade (L1)', function (): void {
    Cache::flush();

    /** @var User $user */
    $user = User::query()->create([
        'name' => 'Badge Approver',
        'email' => 'badge-approver@example.test',
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

    $queries = filamentDocs_countQueries(function (): void {
        PendingApprovalsPage::getPendingApprovalsCount();
        PendingApprovalsPage::getNavigationBadge();
    });

    expect($queries)->toBe(1);
    expect(PendingApprovalsPage::getNavigationBadge())->toBe('1');
});
