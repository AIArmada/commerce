<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Contracts\DocumentEInvoiceSubmissions;
use AIArmada\Docs\Enums\DocEInvoiceSubmissionStatus;
use AIArmada\Docs\Enums\DocEInvoiceValidationStatus;
use AIArmada\Docs\Features\Lifecycle\Models\DocEInvoiceSubmission;
use AIArmada\Docs\Models\Doc;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('einvoice submission status helpers', function (): void {
    $sub = DocEInvoiceSubmission::factory()->create([
        'status' => DocEInvoiceSubmissionStatus::Pending,
        'validation_status' => null,
    ]);

    expect($sub->isPending())->toBeTrue();
    expect($sub->isSubmitted())->toBeFalse();

    app(DocumentEInvoiceSubmissions::class)->update((string) $sub->doc_id, (string) $sub->getKey(), ['status' => DocEInvoiceSubmissionStatus::Submitted]);
    expect($sub->refresh()->isSubmitted())->toBeTrue();
});

test('einvoice submission validation helpers', function (): void {
    $sub = DocEInvoiceSubmission::factory()->create([
        'validation_status' => DocEInvoiceValidationStatus::Valid,
        'errors' => null,
        'warnings' => [],
    ]);

    expect($sub->isValid())->toBeTrue();
    expect($sub->isRejected())->toBeFalse();
    expect($sub->hasErrors())->toBeFalse();
    expect($sub->hasWarnings())->toBeFalse();

    app(DocumentEInvoiceSubmissions::class)->update((string) $sub->doc_id, (string) $sub->getKey(), [
        'validation_status' => DocEInvoiceValidationStatus::Invalid,
        'errors' => ['field' => 'error'],
        'warnings' => ['field' => 'warning'],
    ]);

    expect($sub->refresh()->isValid())->toBeFalse();
    expect($sub->isRejected())->toBeTrue();
    expect($sub->hasErrors())->toBeTrue();
    expect($sub->hasWarnings())->toBeTrue();
});

test('einvoice portal url generation', function (): void {
    // Sandbox default
    config(['docs.einvoice.sandbox' => true]);

    $sub = DocEInvoiceSubmission::factory()->create(['long_id' => '123456']);
    expect($sub->getPortalUrl())->toBe('https://preprod.myinvois.hasil.gov.my/document/123456');

    // Production
    config(['docs.einvoice.sandbox' => false]);
    expect($sub->getPortalUrl())->toBe('https://myinvois.hasil.gov.my/document/123456');

    // No long id
    app(DocumentEInvoiceSubmissions::class)->update((string) $sub->doc_id, (string) $sub->getKey(), ['long_id' => null]);
    expect($sub->refresh()->getPortalUrl())->toBeNull();
});

test('einvoice portal URL encodes the submission identifier as one path segment', function (): void {
    config(['docs.einvoice.sandbox' => true]);

    $submission = new DocEInvoiceSubmission(['long_id' => 'id?redirect=https://example.test/#fragment']);

    expect($submission->getPortalUrl())->toBe('https://preprod.myinvois.hasil.gov.my/document/id%3Fredirect%3Dhttps%3A%2F%2Fexample.test%2F%23fragment');
});

test('einvoice writes are tenant scoped and submission identity is idempotent', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);
    config()->set('docs.owner.auto_assign_on_create', true);

    $owner = User::factory()->create();
    $other = User::factory()->create();
    $doc = OwnerContext::withOwner($owner, fn (): Doc => Doc::factory()->create());
    $anotherDoc = OwnerContext::withOwner($owner, fn (): Doc => Doc::factory()->create());
    $submissions = app(DocumentEInvoiceSubmissions::class);
    $docId = (string) $doc->getKey();

    $id = OwnerContext::withOwner($owner, function () use ($submissions, $docId, $anotherDoc, $owner): string {
        $id = $submissions->record($docId, ' batch-reference ', ['status' => 'submitted']);

        expect($submissions->record($docId, 'batch-reference'))->toBe($id)
            ->and(DocEInvoiceSubmission::query()->findOrFail($id)->belongsToOwner($owner))->toBeTrue()
            ->and(DocEInvoiceSubmission::query()->where('doc_id', $docId)->count())->toBe(1);

        expect(fn () => $submissions->update((string) $anotherDoc->getKey(), $id, ['status' => 'failed']))
            ->toThrow(ModelNotFoundException::class);
        expect(fn () => $submissions->update($docId, $id, ['submission_uid' => 'different']))
            ->toThrow(ValidationException::class);

        $submissions->update($docId, $id, ['status' => 'completed', 'validation_status' => 'valid']);

        expect(DocEInvoiceSubmission::query()->findOrFail($id)->isValid())->toBeTrue();

        return $id;
    });

    OwnerContext::withOwner($other, function () use ($submissions, $docId, $id): void {
        expect(fn () => $submissions->record($docId, 'other-submission'))->toThrow(AuthorizationException::class)
            ->and(fn () => $submissions->update($docId, $id, ['status' => 'failed']))->toThrow(AuthorizationException::class);
    });
});
