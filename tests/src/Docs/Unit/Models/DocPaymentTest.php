<?php

declare(strict_types=1);

use AIArmada\Docs\Enums\DocPaymentStatus;
use AIArmada\Docs\Features\Payments\Models\DocPayment;
use AIArmada\Docs\Models\Doc;

it('casts payment status to the DocPaymentStatus enum', function (): void {
    $doc = Doc::factory()->create();

    $payment = DocPayment::query()->create([
        'doc_id' => $doc->id,
        'status' => DocPaymentStatus::Paid,
        'amount_minor' => 100,
        'currency' => 'MYR',
        'payment_method' => 'cash',
        'paid_at' => now(),
    ]);

    expect($payment->fresh()->status)->toBe(DocPaymentStatus::Paid);
});
