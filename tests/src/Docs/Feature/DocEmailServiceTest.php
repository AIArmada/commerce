<?php

declare(strict_types=1);

use AIArmada\Docs\Data\DocType;
use AIArmada\Docs\Enums\EmailStatus;
use AIArmada\Docs\Features\Delivery\Actions\DocEmailService;
use AIArmada\Docs\Features\Delivery\Actions\TrackDocEmail;
use AIArmada\Docs\Features\Delivery\EntryPoints\Jobs\SendDocEmailJob;
use AIArmada\Docs\Features\Delivery\Models\DocEmail;
use AIArmada\Docs\Features\Delivery\Models\DocEmailTemplate;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\Overdue;
use AIArmada\Docs\States\Pending;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use AIArmada\Docs\Features\Delivery\Mail\DocMail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Doc::query()->delete();
    DocEmail::query()->delete();
    DocEmailTemplate::query()->delete();

    // Disable email queueing for tests
    config()->set('docs.email.queue_enabled', false);
    config()->set('docs.email.attach_pdf', false);

    // Register tracking routes for tests
    Route::get('/docs/track/open/{token}', fn () => response()->noContent())->name('docs.track.open');
    Route::get('/docs/track/click/{token}', fn () => response()->noContent())->name('docs.track.click');
});

test('it can send document email', function (): void {
    $emailService = app(DocEmailService::class);

    $doc = Doc::factory()->create([
        'doc_type' => DocType::Invoice->value,
        'doc_number' => 'INV-2024-001',
        'status' => Pending::class,
        'total_minor' => 1000,
        'currency' => 'MYR',
    ]);

    $email = $emailService->send(
        doc: $doc,
        recipientEmail: 'customer@example.com',
        recipientName: 'John Doe',
    );

    expect($email)
        ->toBeInstanceOf(DocEmail::class)
        ->and($email->recipient_email)->toBe('customer@example.com')
        ->and($email->recipient_name)->toBe('John Doe')
        ->and($email->status)->toBe(EmailStatus::Sent);
});

test('it uses template when available', function (): void {
    DocEmailTemplate::create([
        'name' => 'Invoice Template',
        'slug' => 'invoice-template',
        'doc_type' => DocType::Invoice->value,
        'trigger' => 'send',
        'subject' => 'Invoice #{{ doc_number }}',
        'body' => 'Dear {{ customer_name }}, please find invoice {{ doc_number }}.',
        'is_active' => true,
    ]);

    $emailService = app(DocEmailService::class);

    $doc = Doc::factory()->create([
        'doc_type' => DocType::Invoice->value,
        'doc_number' => 'INV-2024-002',
        'customer_data' => ['name' => 'Jane Doe'],
    ]);

    $email = $emailService->send(
        doc: $doc,
        recipientEmail: 'jane@example.com',
    );

    expect($email->subject)->toContain('INV-2024-002');
});

test('it can send reminder for overdue document', function (): void {
    $emailService = app(DocEmailService::class);

    $doc = Doc::factory()->create([
        'doc_type' => DocType::Invoice->value,
        'doc_number' => 'INV-2024-003',
        'status' => Overdue::class,
        'due_date' => now()->subDays(5),
    ]);

    $email = $emailService->sendReminder(
        doc: $doc,
        recipientEmail: 'overdue@example.com',
    );

    expect($email)
        ->toBeInstanceOf(DocEmail::class)
        ->and($email->recipient_email)->toBe('overdue@example.com');
});

test('it finds correct template for doc type and trigger', function (): void {
    DocEmailTemplate::create([
        'name' => 'Invoice Send',
        'slug' => 'invoice-send',
        'doc_type' => DocType::Invoice->value,
        'trigger' => 'send',
        'subject' => 'Invoice',
        'body' => 'Body',
        'is_active' => true,
    ]);

    DocEmailTemplate::create([
        'name' => 'Invoice Reminder',
        'slug' => 'invoice-reminder',
        'doc_type' => DocType::Invoice->value,
        'trigger' => 'reminder',
        'subject' => 'Reminder',
        'body' => 'Body',
        'is_active' => true,
    ]);

    $emailService = app(DocEmailService::class);

    $doc = Doc::factory()->create([
        'doc_type' => DocType::Invoice->value,
    ]);

    $sendTemplate = $emailService->findTemplate($doc, 'send');
    $reminderTemplate = $emailService->findTemplate($doc, 'reminder');

    expect($sendTemplate->name)->toBe('Invoice Send')
        ->and($reminderTemplate->name)->toBe('Invoice Reminder');
});

test('it returns null for non-existent template', function (): void {
    $emailService = app(DocEmailService::class);

    $doc = Doc::factory()->create([
        'doc_type' => 'non_existent',
    ]);

    $template = $emailService->findTemplate($doc, 'send');

    expect($template)->toBeNull();
});

test('it ignores inactive templates', function (): void {
    DocEmailTemplate::create([
        'name' => 'Inactive Template',
        'slug' => 'inactive-template',
        'doc_type' => DocType::Invoice->value,
        'trigger' => 'send',
        'subject' => 'Subject',
        'body' => 'Body',
        'is_active' => false,
    ]);

    $emailService = app(DocEmailService::class);

    $doc = Doc::factory()->create([
        'doc_type' => DocType::Invoice->value,
    ]);

    $template = $emailService->findTemplate($doc, 'send');

    expect($template)->toBeNull();
});

test('it generates tracking pixel url', function (): void {
    $doc = Doc::factory()->create();

    $email = DocEmail::create([
        'doc_id' => $doc->id,
        'recipient_email' => 'track@example.com',
        'subject' => 'Test',
        'body' => 'Test body',
        'status' => EmailStatus::Sent,
    ]);

    $emailService = app(TrackDocEmail::class);
    $url = $emailService->getTrackingPixelUrl($email);

    expect($url)->toBeString()->toContain('docs/track/open');
});

test('it generates tracked link url', function (): void {
    $doc = Doc::factory()->create();

    $email = DocEmail::create([
        'doc_id' => $doc->id,
        'recipient_email' => 'track@example.com',
        'subject' => 'Test',
        'body' => 'Test body',
        'status' => EmailStatus::Sent,
    ]);

    $emailService = app(TrackDocEmail::class);
    $originalUrl = 'https://example.com/document';
    $trackedUrl = $emailService->getTrackedLinkUrl($email, $originalUrl);

    expect($trackedUrl)->toBeString()->toContain('docs/track/click');
});

test('doc can have multiple emails', function (): void {
    $doc = Doc::factory()->create();

    DocEmail::create([
        'doc_id' => $doc->id,
        'recipient_email' => 'first@example.com',
        'subject' => 'First',
        'body' => 'Body',
        'status' => EmailStatus::Sent,
    ]);

    DocEmail::create([
        'doc_id' => $doc->id,
        'recipient_email' => 'second@example.com',
        'subject' => 'Second',
        'body' => 'Body',
        'status' => EmailStatus::Sent,
    ]);

    $doc->refresh();

    expect($doc->emails)->toHaveCount(2);
});

test('email tracks sent timestamp', function (): void {
    $doc = Doc::factory()->create();

    $email = DocEmail::create([
        'doc_id' => $doc->id,
        'recipient_email' => 'test@example.com',
        'subject' => 'Test',
        'body' => 'Body',
        'status' => EmailStatus::Queued,
    ]);

    expect($email->sent_at)->toBeNull();

    $email->update([
        'status' => EmailStatus::Sent,
        'sent_at' => now(),
    ]);

    expect($email->sent_at)->not->toBeNull();
});

test('email tracks opened timestamp', function (): void {
    $doc = Doc::factory()->create();

    $email = DocEmail::create([
        'doc_id' => $doc->id,
        'recipient_email' => 'test@example.com',
        'subject' => 'Test',
        'body' => 'Body',
        'status' => EmailStatus::Sent,
        'sent_at' => now(),
    ]);

    expect($email->opened_at)->toBeNull();

    $token = Crypt::encryptString(json_encode([
        'email_id' => $email->getKey(),
        'type' => 'open',
        'issued_at' => now()->timestamp,
    ], JSON_THROW_ON_ERROR));

    expect(app(TrackDocEmail::class)->trackOpen($token))->toBeTrue();
    $firstOpenedAt = $email->refresh()->opened_at;

    expect($firstOpenedAt)->not->toBeNull()
        ->and($email->open_count)->toBe(1);

    expect(app(TrackDocEmail::class)->trackOpen($token))->toBeTrue();

    expect($email->refresh()->open_count)->toBe(2)
        ->and($email->opened_at?->toDateTimeString())->toBe($firstOpenedAt?->toDateTimeString());
});

it('rejects malformed and future email tracking tokens', function (): void {
    $service = app(TrackDocEmail::class);
    $token = Crypt::encryptString(json_encode([
        'email_id' => ['not-a-scalar'],
        'type' => 'open',
        'issued_at' => now()->timestamp,
    ], JSON_THROW_ON_ERROR));

    expect($service->trackOpen($token))->toBeFalse();

    $token = Crypt::encryptString(json_encode([
        'email_id' => (string) Str::uuid(),
        'type' => 'click',
        'url' => 'https://example.com',
        'issued_at' => now()->addDay()->timestamp,
    ], JSON_THROW_ON_ERROR));

    expect($service->trackClick($token))->toBeNull();
});

test('queued document email delivery waits for the database commit', function (): void {
    Bus::fake();
    config()->set('docs.email.queue_enabled', true);

    $doc = Doc::factory()->create();

    $email = app(DocEmailService::class)->send(
        doc: $doc,
        recipientEmail: 'customer@example.com',
    );

    expect($email->status)->toBe(EmailStatus::Queued);

    Bus::assertDispatched(SendDocEmailJob::class, fn (SendDocEmailJob $job): bool => $job->afterCommit === true
        && $job->docEmailId === (string) $email->getKey()
        && $job->docId === (string) $doc->getKey());
});

it('rejects unsafe rendered email headers before persisting an email', function (): void {
    $doc = Doc::factory()->create();
    $service = app(DocEmailService::class);

    expect(fn () => $service->send($doc, 'customer@example.com', subjectOverride: "Invoice\r\nBcc: other@example.com"))
        ->toThrow(InvalidArgumentException::class)
        ->and($doc->emails()->count())->toBe(0);
});

it('normalizes recipient details and handles malformed document names', function (): void {
    $doc = Doc::factory()->create([
        'company_data' => ['name' => ['unexpected']],
        'customer_data' => ['name' => ['unexpected']],
    ]);

    $email = app(DocEmailService::class)->send($doc, ' customer@example.com ', ' Customer ');

    expect($email->recipient_email)->toBe('customer@example.com')
        ->and($email->recipient_name)->toBe('Customer')
        ->and($email->status)->toBe(EmailStatus::Sent);
});

it('does not send a synchronous email before the surrounding transaction commits', function (): void {
    Mail::fake();
    $doc = Doc::factory()->create();
    $service = app(DocEmailService::class);

    expect(fn () => $doc->getConnection()->transaction(function () use ($doc, $service): void {
        $email = $service->send($doc, 'customer@example.com');

        expect($email->status)->toBe(EmailStatus::Queued);
        Mail::assertNothingSent();

        throw new RuntimeException('Rollback email');
    }))->toThrow(RuntimeException::class, 'Rollback email');

    Mail::assertNothingSent();
    expect($doc->emails()->count())->toBe(0);

    $email = $service->send($doc, 'customer@example.com');
    Mail::assertSent(DocMail::class, 1);
    expect($email->status)->toBe(EmailStatus::Sent);
});
