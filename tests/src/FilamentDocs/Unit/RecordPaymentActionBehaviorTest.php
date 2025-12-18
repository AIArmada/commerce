<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\FilamentDocs\Actions\RecordPaymentAction;

uses(TestCase::class);

it('records a payment and updates document status', function (): void {
    $doc = Doc::factory()->create([
        'status' => DocStatus::SENT,
        'total' => 100,
        'currency' => 'MYR',
    ]);

    $method = new ReflectionMethod(RecordPaymentAction::class, 'recordPayment');
    $method->setAccessible(true);

    $method->invoke(null, $doc, [
        'amount' => 25,
        'payment_method' => 'cash',
        'reference' => 'PAY-001',
        'paid_at' => now(),
        'notes' => 'partial',
    ]);

    $doc->refresh();

    expect($doc->status)->toBe(DocStatus::PARTIALLY_PAID);
    expect(DocPayment::query()->where('doc_id', $doc->id)->count())->toBe(1);

    $method->invoke(null, $doc, [
        'amount' => 75,
        'payment_method' => 'cash',
        'reference' => 'PAY-002',
        'paid_at' => now(),
        'notes' => null,
    ]);

    $doc->refresh();

    expect($doc->status)->toBe(DocStatus::PAID);
    expect($doc->paid_at)->not()->toBeNull();
    expect(DocPayment::query()->where('doc_id', $doc->id)->count())->toBe(2);
});
