<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Enums\DocPaymentStatus;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Enums\EmailStatus;
use AIArmada\Docs\Http\Controllers\DocPreviewController;
use AIArmada\Docs\Jobs\SendDocEmailJob;
use AIArmada\Docs\Mail\DocMail;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocEmail;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\Docs\Models\DocSequence;
use AIArmada\Docs\Models\DocTemplate;
use AIArmada\Docs\Rendering\TiptapJsonRenderer;
use AIArmada\Docs\Services\DocEmailService;
use AIArmada\Docs\Services\DocRenderService;
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\Services\DueDocReminders;
use AIArmada\Docs\Services\SequenceManager;
use AIArmada\Docs\States\DocStatus;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\PartiallyPaid;
use AIArmada\Docs\States\Sent;
use AIArmada\Docs\Support\DocTypeKey;
use AIArmada\Docs\Support\TemplateBlockRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Mockery::close();
    CarbonImmutable::setTestNow();
});

function regressionOwner(string $name): User
{
    return User::query()->create([
        'name' => $name,
        'email' => str()->slug($name) . '-' . uniqid() . '@example.test',
        'password' => bcrypt('password'),
    ]);
}

function regressionItems(): array
{
    return [['name' => 'Item', 'quantity' => 1, 'unit_price_minor' => 100]];
}

// --- per-owner doc numbers no longer collide ---

test('two owners can hold identically formatted sequence numbers', function (): void {
    config()->set('docs.owner.enabled', true);

    $ownerA = regressionOwner('Repair Owner A');
    $ownerB = regressionOwner('Repair Owner B');

    $docA = app(DocService::class)->createFromType(DocType::Invoice, [], $ownerA);
    $docB = app(DocService::class)->createFromType(DocType::Invoice, [], $ownerB);

    expect($docA->doc_number)->toBe($docB->doc_number)
        ->and((string) $docA->owner_id)->toBe((string) $ownerA->getKey())
        ->and((string) $docB->owner_id)->toBe((string) $ownerB->getKey());
});

test('duplicate explicit doc numbers are rejected with a domain error', function (): void {
    app(DocService::class)->create(DocData::from([
        'doc_number' => 'REPAIR-DUP-1',
        'items' => regressionItems(),
    ]));

    expect(fn (): Doc => app(DocService::class)->create(DocData::from([
        'doc_number' => 'REPAIR-DUP-1',
        'items' => regressionItems(),
    ])))->toThrow(InvalidArgumentException::class, 'already in use');
});

// --- pdf_path is no longer mass-assignable ---

test('pdf_path cannot be mass-assigned but update ignores it safely', function (): void {
    $doc = Doc::query()->create([
        'doc_number' => 'REPAIR-FILL-1',
        'doc_type' => 'invoice',
        'status' => Draft::class,
        'issue_date' => CarbonImmutable::now(),
        'pdf_path' => 'docs/evil.pdf',
    ]);

    expect($doc->fresh()->pdf_path)->toBeNull();

    $updated = app(DocService::class)->update($doc, [
        'pdf_path' => 'docs/evil.pdf',
        'notes' => 'updated',
    ]);

    expect($updated->fresh()->pdf_path)->toBeNull()
        ->and($updated->fresh()->notes)->toBe('updated');
});

// --- doc_type config key traversal ---

test('dotted doc types fall back to global storage defaults', function (): void {
    config()->set('docs.storage.disk', 'local');
    config()->set('docs.types.invoice.storage.disk', 'invoices');

    expect(app(DocService::class)->resolveStorageDiskForDocType('invoice.storage.disk'))->toBe('local')
        ->and(app(DocService::class)->resolveStorageDiskForDocType('..'))->toBe('local')
        ->and(app(DocService::class)->resolveStorageDiskForDocType('invoice'))->toBe('invoices')
        ->and(DocTypeKey::sanitize('invoice.evil'))->toBeNull()
        ->and(DocTypeKey::sanitize('invoice'))->toBe('invoice')
        ->and(DocTypeKey::sanitize('custom_type-1'))->toBe('custom_type-1');
});

// --- SSRF embeds blocked ---

test('link-local embed urls are stripped while public ips render', function (): void {
    $renderer = app(TiptapJsonRenderer::class);

    $evil = $renderer->render(['type' => 'doc', 'content' => [
        ['type' => 'image', 'attrs' => ['src' => 'http://169.254.169.254/latest/meta-data']],
    ]])->toHtml();

    $public = $renderer->render(['type' => 'doc', 'content' => [
        ['type' => 'image', 'attrs' => ['src' => 'https://93.184.216.34/logo.png', 'alt' => 'x']],
    ]])->toHtml();

    expect($evil)->not->toContain('<img')
        ->and($public)->toContain('https://93.184.216.34/logo.png');
});

// --- queued emails transition via job ---

test('queued sends dispatch a job that marks the email sent', function (): void {
    config()->set('docs.email.queue_enabled', true);
    Queue::fake();

    $doc = Doc::factory()->create();

    $email = app(DocEmailService::class)->send($doc, 'queued@example.test');

    expect($email->status)->toBe(EmailStatus::Queued);

    Queue::assertPushed(SendDocEmailJob::class, fn (SendDocEmailJob $job): bool => $job->docEmailId === $email->id);

    Mail::fake();
    (new SendDocEmailJob((string) $email->id, (string) $doc->id, attachPdf: false))->handle();

    expect($email->fresh()->status)->toBe(EmailStatus::Sent)
        ->and($email->fresh()->sent_at)->not->toBeNull();

    // Idempotent redelivery does not resend.
    (new SendDocEmailJob((string) $email->id, (string) $doc->id, attachPdf: false))->handle();
    Mail::assertSent(DocMail::class, 1);
});

// --- sequence first-use race ---

test('duplicate default sequences for one owner are rejected', function (): void {
    config()->set('docs.owner.enabled', true);

    $owner = regressionOwner('Repair Sequence Owner');
    $manager = app(SequenceManager::class);

    $manager->createDefaultSequence('invoice', $owner);

    expect(fn (): DocSequence => $manager->createDefaultSequence('invoice', $owner))
        ->toThrow(QueryException::class);
});

// --- single atomic numbering path, unpredictable suffixes ---

test('create numbers sequentially from the atomic sequence', function (): void {
    $service = app(DocService::class);

    $first = $service->create(DocData::from(['items' => regressionItems()]));
    $second = $service->create(DocData::from(['items' => regressionItems()]));

    expect($first->doc_number)->toMatch('/^INV-\d{4}-000001$/')
        ->and($second->doc_number)->toMatch('/^INV-\d{4}-000002$/');
});

test('strategy suffixes are dot-free crypto random values', function (): void {
    $numbers = collect(range(1, 10))->map(fn (): string => app(DocService::class)->generateNumber('invoice'));

    expect($numbers->unique())->toHaveCount(10)
        ->and($numbers->first())->toMatch('/^INV\d{2}-[A-Z0-9]{6}$/')
        ->and($numbers->first())->not->toContain('.');
});

// --- update allowlist + state machine + owner check ---

test('update ignores immutable keys and recalculates totals', function (): void {
    $doc = Doc::factory()->create([
        'doc_number' => 'REPAIR-IMM-1',
        'doc_type' => 'invoice',
        'status' => Draft::class,
        'subtotal_minor' => 100,
        'tax_amount_minor' => 0,
        'discount_amount_minor' => 0,
        'total_minor' => 100,
    ]);

    $updated = app(DocService::class)->update($doc, [
        'doc_number' => 'HACKED',
        'doc_type' => 'receipt',
        'pdf_path' => 'docs/evil.pdf',
        'total_minor' => 999999,
        'paid_at' => CarbonImmutable::now()->toDateTimeString(),
        'notes' => 'allowed',
        'items' => [['name' => 'New', 'quantity' => 2, 'unit_price_minor' => 50]],
    ]);

    $fresh = $updated->fresh();

    expect($fresh->doc_number)->toBe('REPAIR-IMM-1')
        ->and($fresh->doc_type)->toBe('invoice')
        ->and($fresh->pdf_path)->toBeNull()
        ->and($fresh->notes)->toBe('allowed')
        ->and($fresh->subtotal_minor)->toBe(100)
        ->and($fresh->total_minor)->toBe(100)
        ->and($fresh->paid_at)->toBeNull();
});

test('update routes status changes through the state machine', function (): void {
    $doc = Doc::factory()->create(['status' => Draft::class]);

    app(DocService::class)->update($doc, ['status' => Sent::class]);

    expect($doc->fresh()->status->equals(Sent::class))->toBeTrue()
        ->and($doc->statusHistories()->count())->toBe(1);

    $paid = Doc::factory()->create(['status' => Paid::class]);

    expect(fn (): Doc => app(DocService::class)->update($paid, ['status' => Draft::class]))
        ->toThrow(CouldNotPerformTransition::class);

    expect(fn (): Doc => app(DocService::class)->update($doc, ['status' => 'bogus-status']))
        ->toThrow(InvalidArgumentException::class);
});

test('update re-derives the total when only the discount changes', function (): void {
    $doc = Doc::factory()->create([
        'status' => Draft::class,
        'subtotal_minor' => 100,
        'tax_amount_minor' => 10,
        'discount_amount_minor' => 0,
        'total_minor' => 110,
    ]);

    $updated = app(DocService::class)->update($doc, ['discount_amount_minor' => 20]);

    expect($updated->fresh()->total_minor)->toBe(90);
});

test('update by template slug persists the resolved template', function (): void {
    $template = DocTemplate::query()->create([
        'name' => 'Repair Slug Template',
        'slug' => 'repair-slug-template',
        'doc_type' => 'invoice',
        'is_default' => false,
        'layout' => TemplateBlockRegistry::defaultLayout(),
    ]);

    $doc = Doc::factory()->create(['doc_type' => 'invoice', 'doc_template_id' => null]);

    $updated = app(DocService::class)->update($doc, ['template_slug' => 'repair-slug-template']);

    expect($updated->fresh()->doc_template_id)->toBe($template->id);
});

test('cross-owner updates are rejected', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);

    $ownerA = regressionOwner('Repair Update A');
    $ownerB = regressionOwner('Repair Update B');

    $doc = OwnerContext::withOwner($ownerA, fn (): Doc => Doc::factory()->create());

    expect(fn (): mixed => OwnerContext::withOwner($ownerB, fn (): Doc => app(DocService::class)->update($doc, ['notes' => 'x'])))
        ->toThrow(AuthorizationException::class);
});

// --- unvalidated caller totals ---

test('item-less creation validates stored monetary fields', function (): void {
    expect(fn (): Doc => app(DocService::class)->createFromType(DocType::Invoice, ['total_minor' => -5]))
        ->toThrow(InvalidArgumentException::class);

    expect(fn (): Doc => app(DocService::class)->createFromType(DocType::Invoice, ['total_minor' => '100']))
        ->toThrow(InvalidArgumentException::class);

    expect(fn (): Doc => app(DocService::class)->createFromType(DocType::Invoice, ['total' => 10.5]))
        ->toThrow(InvalidArgumentException::class);
});

test('explicit totals contradicting items are rejected', function (): void {
    expect(fn (): Doc => app(DocService::class)->create(DocData::from([
        'items' => regressionItems(),
        'total_minor' => 999999,
    ])))->toThrow(InvalidArgumentException::class, 'total_minor');
});

// --- version restore through the service ---

test('restore applies the snapshot through the service and records a version', function (): void {
    $service = app(DocService::class);

    $doc = $service->create(DocData::from(['items' => regressionItems(), 'notes' => 'v1 notes']));
    $service->update($doc, ['notes' => 'v2 notes']);

    $firstVersion = $doc->versions()->orderBy('version_number')->firstOrFail();
    $firstVersion->restore();

    $fresh = $doc->fresh();

    expect($fresh->notes)->toBe('v1 notes')
        ->and($fresh->versions()->count())->toBe(3)
        ->and($fresh->versions()->latest('version_number')->first()->change_summary)->toBe('Restored version 1');
});

test('restore ignores immutable snapshot keys', function (): void {
    $doc = Doc::factory()->create(['notes' => 'current', 'doc_number' => 'REPAIR-RST-1']);

    $snapshot = $doc->toArray();
    $snapshot['notes'] = 'restored';
    $snapshot['doc_number'] = 'HACKED';
    $snapshot['pdf_path'] = 'docs/evil.pdf';

    $version = $doc->versions()->create([
        'version_number' => 1,
        'snapshot' => $snapshot,
        'change_summary' => 'seed',
    ]);

    $version->restore();

    $fresh = $doc->fresh();

    expect($fresh->notes)->toBe('restored')
        ->and($fresh->doc_number)->toBe('REPAIR-RST-1')
        ->and($fresh->pdf_path)->toBeNull();
});

// --- paid-total filtering + method validation + server timestamps ---

test('refunded payments do not reduce the outstanding balance', function (): void {
    $doc = Doc::factory()->create(['status' => Sent::class, 'total_minor' => 100]);

    DocPayment::query()->create([
        'doc_id' => $doc->id,
        'status' => DocPaymentStatus::Refunded,
        'amount_minor' => 100,
        'currency' => 'MYR',
        'payment_method' => 'cash',
        'paid_at' => CarbonImmutable::now()->subDay(),
    ]);

    $payment = app(DocService::class)->recordPayment($doc, [
        'amount_minor' => 60,
        'payment_method' => 'cash',
    ]);

    expect($payment->status)->toBe(DocPaymentStatus::Paid)
        ->and($doc->fresh()->status->equals(PartiallyPaid::class))->toBeTrue();
});

test('payment method is validated, status is forced paid, and the submitted date is honored', function (): void {
    $doc = Doc::factory()->create(['status' => Sent::class, 'total_minor' => 100]);

    expect(fn (): DocPayment => app(DocService::class)->recordPayment($doc, [
        'amount_minor' => 10,
        'payment_method' => 'barter',
    ]))->toThrow(InvalidArgumentException::class, 'payment_method');

    $submittedPaidAt = CarbonImmutable::now()->subYear()->startOfDay();

    $payment = app(DocService::class)->recordPayment($doc, [
        'amount_minor' => 10,
        'payment_method' => 'cash',
        'status' => DocPaymentStatus::Voided,
        'paid_at' => $submittedPaidAt->toDateTimeString(),
    ]);

    expect($payment->status)->toBe(DocPaymentStatus::Paid)
        ->and($payment->paid_at->equalTo($submittedPaidAt))->toBeTrue();
});

// --- unknown statuses throw ---

test('unresolvable statuses throw instead of becoming draft', function (): void {
    expect(fn (): string => DocStatus::resolveStateClassFor('bogus-status'))
        ->toThrow(InvalidArgumentException::class, 'Unknown document status');

    expect(fn (): DocData => DocData::from(['status' => 'bogus-status']))
        ->toThrow(InvalidArgumentException::class);
});

// --- bounded reminder scans ---

test('reminder scans honor the batch limit', function (): void {
    foreach (range(1, 3) as $i) {
        Doc::factory()->create([
            'status' => Sent::class,
            'due_date' => CarbonImmutable::now()->addDay(),
            'customer_data' => ['email' => "due{$i}@example.test"],
        ]);
    }

    expect(app(DueDocReminders::class)->dueSoon(30, 2))->toHaveCount(2)
        ->and(app(DueDocReminders::class)->dueSoon(30))->toHaveCount(3);
});

// --- mail reuses fresh PDFs ---

test('mail attachments reuse an existing stored pdf', function (): void {
    Storage::fake('local');

    $doc = Doc::factory()->create(['doc_type' => 'invoice', 'doc_number' => 'INV-REUSE-1']);
    $doc->forceFill(['pdf_path' => 'docs/reused.pdf'])->save();
    Storage::disk('local')->put('docs/reused.pdf', 'pdf-bytes');

    $docEmail = DocEmail::query()->create([
        'doc_id' => $doc->id,
        'recipient_email' => 'reuse@example.test',
        'subject' => 'Invoice',
        'body' => 'Body',
        'status' => EmailStatus::Queued,
    ]);

    Pdf::shouldReceive('html')->never();

    $attachments = (new DocMail($docEmail, $doc, attachPdf: true))->attachments();

    expect($attachments)->toHaveCount(1);
});

// --- CC validation ---

test('invalid cc metadata is dropped without breaking the envelope', function (): void {
    $doc = Doc::factory()->create();

    $bad = DocEmail::query()->create([
        'doc_id' => $doc->id,
        'recipient_email' => 'to@example.test',
        'subject' => 'Subject',
        'body' => 'Body',
        'status' => EmailStatus::Queued,
        'metadata' => ['cc' => 'not-an-email'],
    ]);

    $mixed = DocEmail::query()->create([
        'doc_id' => $doc->id,
        'recipient_email' => 'to@example.test',
        'subject' => 'Subject',
        'body' => 'Body',
        'status' => EmailStatus::Queued,
        'metadata' => ['cc' => 'good@example.test, bad-value, other@example.test'],
    ]);

    expect((new DocMail($bad, $doc))->envelope()->cc)->toBe([])
        ->and((new DocMail($mixed, $doc))->envelope()->cc)->toHaveCount(2);
});

// --- recipient validation ---

test('sends with invalid recipients are rejected before persisting', function (): void {
    config()->set('docs.email.queue_enabled', false);

    $doc = Doc::factory()->create();

    expect(fn (): DocEmail => app(DocEmailService::class)->send($doc, 'not-an-email'))
        ->toThrow(InvalidArgumentException::class, 'valid email address');

    expect($doc->emails()->count())->toBe(0);
});

// --- throttled public routes + expiring tracking tokens ---

test('public tracking and share routes are throttled', function (): void {
    foreach (['docs.track.open', 'docs.track.click', 'docs.share.show', 'docs.share.pdf'] as $name) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull();

        $middleware = collect($route->gatherMiddleware())->implode(',');

        expect($middleware)->toContain('throttle:60,1');
    }
});

test('expired tracking tokens stop working', function (): void {
    Route::middleware(['web', 'throttle:60,1'])
        ->get('/docs/track/open/{token}', fn () => response()->noContent())
        ->name('docs.track.open');

    $doc = Doc::factory()->create();
    $email = DocEmail::query()->create([
        'doc_id' => $doc->id,
        'recipient_email' => 'ttl@example.test',
        'subject' => 'Subject',
        'body' => 'Body',
        'status' => EmailStatus::Sent,
    ]);

    $url = app(DocEmailService::class)->getTrackingPixelUrl($email);
    $token = basename((string) parse_url($url, PHP_URL_PATH));

    expect(app(DocEmailService::class)->trackOpen($token))->toBeTrue();

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addDays(181));

    expect(app(DocEmailService::class)->trackOpen($token))->toBeFalse();
});

// --- stored PDFs are removed with the doc ---

test('deleting a doc removes its stored pdf', function (): void {
    Storage::fake('local');

    $doc = Doc::factory()->create(['doc_type' => 'invoice']);
    $doc->forceFill(['pdf_path' => 'docs/orphan.pdf'])->save();
    Storage::disk('local')->put('docs/orphan.pdf', 'pdf-bytes');

    $doc->delete();

    Storage::disk('local')->assertMissing('docs/orphan.pdf');
});

// --- sequential version numbers ---

test('versions increment sequentially', function (): void {
    $doc = Doc::factory()->create();

    $first = app(DocService::class)->createVersion($doc, 'one');
    $second = app(DocService::class)->createVersion($doc, 'two');

    expect($first->version_number)->toBe(1)
        ->and($second->version_number)->toBe(2);
});

// --- preview re-checks bound docs ---

test('preview of a cross-owner bound doc returns not found', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);

    $ownerA = regressionOwner('Repair Preview A');
    $ownerB = regressionOwner('Repair Preview B');

    $doc = OwnerContext::withOwner($ownerA, fn (): Doc => Doc::factory()->create());

    expect(fn (): mixed => OwnerContext::withOwner(
        $ownerB,
        fn (): mixed => app(DocPreviewController::class)($doc, app(DocRenderService::class))
    ))->toThrow(NotFoundHttpException::class);
});

// --- capped rich content rendering ---

test('over-deep and over-wide rich content is rejected', function (): void {
    $deep = ['type' => 'text', 'text' => 'x'];

    foreach (range(1, 40) as $i) {
        $deep = ['type' => 'blockquote', 'content' => [$deep]];
    }

    expect(fn (): string => app(TiptapJsonRenderer::class)->render($deep)->toHtml())
        ->toThrow(InvalidArgumentException::class, 'nesting depth');

    $wide = ['type' => 'doc', 'content' => array_map(
        static fn (int $i): array => ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => "p{$i}"]]],
        range(1, 1100),
    )];

    expect(fn (): string => app(TiptapJsonRenderer::class)->render($wide)->toHtml())
        ->toThrow(InvalidArgumentException::class, 'node count');
});

// --- preview without locks ---

test('preview does not create sequences or require a transaction', function (): void {
    $preview = app(SequenceManager::class)->preview('invoice');

    expect($preview)->toBe(app(SequenceManager::class)->preview('invoice'))
        ->and(DocSequence::query()->count())->toBe(0);
});

// --- doc types are validated ---

test('unknown doc types are rejected at the boundary', function (): void {
    expect(fn (): DocData => DocData::from(['doc_type' => 'bogus']))
        ->toThrow(InvalidArgumentException::class, 'Unknown document type');

    expect(fn (): Doc => app(DocService::class)->create(new DocData(docType: 'bogus')))
        ->toThrow(InvalidArgumentException::class, 'Unknown document type');
});

// --- atomic creation with an initial version ---

test('create records an initial version and rolls back on failure', function (): void {
    $doc = app(DocService::class)->create(DocData::from(['items' => regressionItems()]));

    expect($doc->versions()->count())->toBe(1)
        ->and($doc->versions()->first()->change_summary)->toBe('Initial creation');

    expect(fn (): Doc => app(DocService::class)->create(DocData::from([
        'items' => [['name' => 'Bad', 'quantity' => 0, 'unit_price_minor' => 100]],
    ])))->toThrow(InvalidArgumentException::class);

    expect(Doc::query()->count())->toBe(1);
});

// --- constrained pdf options ---

test('caller pdf options are constrained to safe values', function (): void {
    Storage::fake('local');
    config()->set('docs.storage.disk', 'local');

    $doc = Doc::factory()->create([
        'doc_type' => 'invoice',
        'doc_number' => 'INV-PDFOPT-1',
        'metadata' => ['pdf' => ['format' => 'huge-poster', 'orientation' => 'diagonal', 'margin' => ['top' => -50]]],
    ]);

    $pdfBuilder = Mockery::mock(PdfBuilder::class);
    $pdfBuilder->shouldReceive('format')->once()->with('a4')->andReturnSelf();
    $pdfBuilder->shouldReceive('orientation')->once()->with('portrait')->andReturnSelf();
    $pdfBuilder->shouldReceive('margins')->once()->with(0, 10, 10, 10)->andReturnSelf();
    $pdfBuilder->shouldReceive('withBrowsershot')->once()->andReturnSelf();
    $pdfBuilder->shouldReceive('generatePdfContent')->once()->andReturn('PDF CONTENT');

    Pdf::shouldReceive('html')->once()->andReturn($pdfBuilder);

    expect(app(DocService::class)->generatePdf($doc))->toBe('docs/INV-PDFOPT-1.pdf');
});

// --- Cache sanity for the sequence lock path ---

test('sequence generation works under the default test cache driver', function (): void {
    Cache::flush();

    $first = app(SequenceManager::class)->generate('receipt');
    $second = app(SequenceManager::class)->generate('receipt');

    expect($first)->not->toBe($second);
});
