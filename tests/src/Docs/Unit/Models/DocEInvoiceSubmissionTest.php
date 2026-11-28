<?php

declare(strict_types=1);

use AIArmada\Docs\Enums\DocEInvoiceSubmissionStatus;
use AIArmada\Docs\Enums\DocEInvoiceValidationStatus;
use AIArmada\Docs\Features\Lifecycle\Models\DocEInvoiceSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('einvoice submission status helpers', function (): void {
    $sub = DocEInvoiceSubmission::factory()->create([
        'status' => DocEInvoiceSubmissionStatus::Pending,
        'validation_status' => null,
    ]);

    expect($sub->isPending())->toBeTrue();
    expect($sub->isSubmitted())->toBeFalse();

    $sub->update(['status' => DocEInvoiceSubmissionStatus::Submitted]);
    expect($sub->isSubmitted())->toBeTrue();
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

    $sub->update([
        'validation_status' => DocEInvoiceValidationStatus::Invalid,
        'errors' => ['field' => 'error'],
        'warnings' => ['field' => 'warning'],
    ]);

    expect($sub->isValid())->toBeFalse();
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
    $sub->update(['long_id' => null]);
    expect($sub->getPortalUrl())->toBeNull();
});

test('einvoice portal URL encodes the submission identifier as one path segment', function (): void {
    config(['docs.einvoice.sandbox' => true]);

    $submission = new DocEInvoiceSubmission(['long_id' => 'id?redirect=https://example.test/#fragment']);

    expect($submission->getPortalUrl())->toBe('https://preprod.myinvois.hasil.gov.my/document/id%3Fredirect%3Dhttps%3A%2F%2Fexample.test%2F%23fragment');
});
