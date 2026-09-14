<?php

declare(strict_types=1);

use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocPaymentRecorder;
use AIArmada\Docs\States\Sent;
use Carbon\CarbonImmutable;

it('records the submitted payment date instead of always stamping now', function (): void {
    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'total_minor' => 100,
        'currency' => 'MYR',
    ]);

    $paidAt = CarbonImmutable::now()->subDays(3)->startOfDay();

    $payment = app(DocPaymentRecorder::class)->record($doc, [
        'amount_minor' => 100,
        'currency' => 'MYR',
        'payment_method' => 'cash',
        'paid_at' => $paidAt->toDateTimeString(),
    ]);

    expect($payment->paid_at->equalTo($paidAt))->toBeTrue();
});

it('stamps now when no payment date is submitted', function (): void {
    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'total_minor' => 100,
        'currency' => 'MYR',
    ]);

    $payment = app(DocPaymentRecorder::class)->record($doc, [
        'amount_minor' => 100,
        'currency' => 'MYR',
        'payment_method' => 'cash',
    ]);

    expect($payment->paid_at->diffInSeconds(CarbonImmutable::now()))->toBeLessThan(10);
});

it('rejects an unparseable payment date', function (): void {
    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'total_minor' => 100,
        'currency' => 'MYR',
    ]);

    expect(fn (): mixed => app(DocPaymentRecorder::class)->record($doc, [
        'amount_minor' => 100,
        'currency' => 'MYR',
        'payment_method' => 'cash',
        'paid_at' => 'not-a-date',
    ]))->toThrow(InvalidArgumentException::class, 'paid_at');
});
