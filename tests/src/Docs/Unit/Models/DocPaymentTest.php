<?php

declare(strict_types=1);

use AIArmada\Docs\Enums\DocPaymentStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocPayment;

it('casts payment status to the DocPaymentStatus enum', function (): void {
    $doc = Doc::factory()->create();

    $payment = DocPayment::query()->create([
        'doc_id' => $doc->id,
        'status' => DocPaymentStatus::Paid,
        'amount' => 100,
        'currency' => 'MYR',
        'payment_method' => 'cash',
        'paid_at' => now(),
    ]);

    expect($payment->fresh()->status)->toBe(DocPaymentStatus::Paid);
});

it('rejects unsupported payment statuses', function (): void {
    $doc = Doc::factory()->create();

    expect(fn (): DocPayment => DocPayment::query()->create([
        'doc_id' => $doc->id,
        'status' => 'settled_elsewhere',
        'amount' => 100,
        'currency' => 'MYR',
        'payment_method' => 'cash',
        'paid_at' => now(),
    ]))->toThrow(ValueError::class);
});
