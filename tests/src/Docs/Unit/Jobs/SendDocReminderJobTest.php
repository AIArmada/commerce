<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Features\Delivery\Actions\DeliverDocReminders;
use AIArmada\Docs\Features\Delivery\EntryPoints\Jobs\SendDocReminderJob;
use AIArmada\Docs\Features\Delivery\Queries\DueDocReminders;
use AIArmada\Docs\Features\Delivery\Models\DocEmailTemplate;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\Overdue;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\Sent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('sends reminder for specific doc', function (): void {
    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'customer_data' => ['email' => 'test@example.com'],
    ]);

    $job = new SendDocReminderJob($doc->id);
    $job->handle(app(DeliverDocReminders::class), app(DueDocReminders::class));

    expect($doc->emails)->toHaveCount(1)
        ->and($doc->emails->first()->recipient_email)->toBe('test@example.com');
});

test('logs warning if doc not found for specific reminder', function (): void {
    Log::shouldReceive('warning')->once();

    $job = new SendDocReminderJob('invalid-id');
    $job->handle(app(DeliverDocReminders::class), app(DueDocReminders::class));
});

test('sends reminders for upcoming due docs', function (): void {
    $dueDate = now()->addDays(3);
    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'due_date' => $dueDate,
        'customer_data' => ['email' => 'upcoming@example.com', 'name' => 'John Doe'],
    ]);

    $pastDue = Doc::factory()->create([
        'status' => Sent::class,
        'due_date' => now()->subDay(),
        'customer_data' => ['email' => 'pastdue@example.com'],
    ]);

    // Ignored docs
    Doc::factory()->create(['status' => Paid::class, 'due_date' => $dueDate, 'customer_data' => ['email' => 'paid@example.com']]);
    Doc::factory()->create(['status' => Sent::class, 'due_date' => now()->addDays(4), 'customer_data' => ['email' => 'notyet@example.com']]);

    $job = new SendDocReminderJob(daysBeforeDue: 3);
    $job->handle(app(DeliverDocReminders::class), app(DueDocReminders::class));

    expect($doc->emails)->toHaveCount(1)
        ->and($doc->emails->first()->recipient_email)->toBe('upcoming@example.com')
        ->and($pastDue->emails()->count())->toBe(0);
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

    $job = new SendDocReminderJob(daysAfterOverdue: 1);
    $job->handle(app(DeliverDocReminders::class), app(DueDocReminders::class));

    expect($doc->emails)->toHaveCount(1)
        ->and($doc->emails->first()->recipient_email)->toBe('overdue@example.com');
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

    $job = new SendDocReminderJob;

    expect(fn (): mixed => $job->handle(app(DeliverDocReminders::class), app(DueDocReminders::class)))
        ->toThrow(InvalidArgumentException::class, 'Owner type and owner id must both be present or both be null.');
});

test('global owner batch does not recursively fan out', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.email.queue_enabled', true);
    Queue::fake();

    $doc = OwnerContext::withOwner(null, fn (): Doc => Doc::factory()->create([
        'status' => Sent::class,
        'due_date' => now()->addDay(),
        'customer_data' => ['email' => 'global@example.test'],
    ]));

    $job = new SendDocReminderJob;
    $job->handle(app(DeliverDocReminders::class), app(DueDocReminders::class));

    Queue::assertPushed(SendDocReminderJob::class, 1);
    $batch = new SendDocReminderJob(ownerBatch: true);
    $batch->handle(app(DeliverDocReminders::class), app(DueDocReminders::class));

    Queue::assertPushed(SendDocReminderJob::class, 1);
    expect(OwnerContext::withOwner(null, fn (): int => $doc->emails()->count()))->toBe(1);
});

test('malformed customer contact data does not interrupt reminder batches', function (): void {
    Doc::factory()->create([
        'status' => Sent::class,
        'due_date' => now()->addDay(),
        'customer_data' => ['email' => ['invalid']],
    ]);

    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'due_date' => now()->addDays(2),
        'customer_data' => ['email' => 'valid@example.com', 'name' => ['invalid']],
    ]);

    $job = new SendDocReminderJob;
    $job->handle(app(DeliverDocReminders::class), app(DueDocReminders::class));

    expect($doc->emails()->count())->toBe(1);
});

test('scheduled reminders never substitute an ordinary send template for a missing reminder template', function (): void {
    DocEmailTemplate::query()->create([
        'name' => 'Ordinary send',
        'slug' => 'ordinary-send',
        'doc_type' => 'invoice',
        'trigger' => 'send',
        'subject' => 'Ordinary send subject',
        'body' => 'Ordinary send body',
        'is_active' => true,
    ]);

    $upcoming = Doc::factory()->create([
        'doc_type' => 'invoice',
        'status' => Sent::class,
        'due_date' => now()->addDay(),
        'customer_data' => ['email' => 'upcoming@example.com'],
    ]);
    $overdue = Doc::factory()->create([
        'doc_type' => 'invoice',
        'status' => Overdue::class,
        'due_date' => now()->subDays(2),
        'customer_data' => ['email' => 'overdue@example.com'],
    ]);

    (new SendDocReminderJob)->handle(app(DeliverDocReminders::class), app(DueDocReminders::class));

    foreach ([$upcoming, $overdue] as $doc) {
        $email = $doc->emails()->sole();

        expect($email->doc_email_template_id)->toBeNull()
            ->and($email->subject)->not->toBe('Ordinary send subject');
    }
});
