<?php

declare(strict_types=1);

use AIArmada\Docs\Jobs\SendDocReminderJob;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocEmailService;
use AIArmada\Docs\States\Overdue;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\Sent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('sends reminder for specific doc', function (): void {
    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'customer_data' => ['email' => 'test@example.com'],
    ]);

    $service = new DocEmailService;

    $job = new SendDocReminderJob($doc->id);
    $job->handle($service);

    expect($doc->emails)->toHaveCount(1)
        ->and($doc->emails->first()->recipient_email)->toBe('test@example.com');
});

test('logs warning if doc not found for specific reminder', function (): void {
    Log::shouldReceive('warning')->once();

    $service = new DocEmailService;

    $job = new SendDocReminderJob('invalid-id');
    $job->handle($service);
});

test('sends reminders for upcoming due docs', function (): void {
    $dueDate = now()->addDays(3);
    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'due_date' => $dueDate,
        'customer_data' => ['email' => 'upcoming@example.com', 'name' => 'John Doe'],
    ]);

    // Ignored docs
    Doc::factory()->create(['status' => Paid::class, 'due_date' => $dueDate, 'customer_data' => ['email' => 'paid@example.com']]);
    Doc::factory()->create(['status' => Sent::class, 'due_date' => now()->addDays(4), 'customer_data' => ['email' => 'notyet@example.com']]);

    $service = new DocEmailService;

    $job = new SendDocReminderJob(daysBeforeDue: 3);
    $job->handle($service);

    expect($doc->emails)->toHaveCount(1)
        ->and($doc->emails->first()->recipient_email)->toBe('upcoming@example.com');
});

test('sends reminders for overdue docs', function (): void {
    $overdueDate = now()->subDays(1);
    $doc = Doc::factory()->create([
        'status' => Overdue::class,
        'due_date' => $overdueDate,
        'customer_data' => ['email' => 'overdue@example.com'],
    ]);

    // Ignored docs
    Doc::factory()->create(['status' => Paid::class, 'due_date' => $overdueDate, 'customer_data' => ['email' => 'paid@example.com']]);

    $service = new DocEmailService;

    $job = new SendDocReminderJob(daysAfterOverdue: 1);
    $job->handle($service);

    expect($doc->emails)->toHaveCount(1)
        ->and($doc->emails->first()->recipient_email)->toBe('overdue@example.com');
});

test('tags return correct array', function (): void {
    $job1 = new SendDocReminderJob('123');
    expect($job1->tags())->toContain('docs', 'reminder', 'doc:123');

    $job2 = new SendDocReminderJob;
    expect($job2->tags())->toContain('docs', 'reminder', 'batch');
});

test('owner fan-out fails fast on malformed owner tuple rows', function (): void {
    config()->set('docs.owner.enabled', true);

    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'customer_data' => ['email' => 'tuple-malformed@example.com'],
    ]);

    DB::table($doc->getTable())
        ->where('id', $doc->getKey())
        ->update([
            'owner_type' => 'App\\Models\\User',
            'owner_id' => null,
        ]);

    $service = new DocEmailService;
    $job = new SendDocReminderJob;

    expect(fn (): mixed => $job->handle($service))
        ->toThrow(InvalidArgumentException::class, 'Owner type and owner id must both be present or both be null.');
});
