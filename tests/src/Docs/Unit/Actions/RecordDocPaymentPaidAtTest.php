<?php

declare(strict_types=1);

use AIArmada\Docs\Contracts\DocumentPaymentRecorder;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Features\Payments\Models\DocPayment;
use AIArmada\Docs\States\Sent;
use Carbon\CarbonImmutable;

it('records the submitted payment date instead of always stamping now', function (): void {
    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'total_minor' => 100,
        'currency' => 'MYR',
    ]);

    $paidAt = CarbonImmutable::now()->subDays(3)->startOfDay();

    $paymentId = app(DocumentPaymentRecorder::class)->record((string) $doc->getKey(), [
        'amount_minor' => 100,
        'currency' => 'MYR',
        'payment_method' => 'cash',
        'paid_at' => $paidAt->toDateTimeString(),
    ]);

    $payment = DocPayment::query()->findOrFail($paymentId);

    expect($payment->paid_at->equalTo($paidAt))->toBeTrue();
});

it('stamps now when no payment date is submitted', function (): void {
    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'total_minor' => 100,
        'currency' => 'MYR',
    ]);

    $paymentId = app(DocumentPaymentRecorder::class)->record((string) $doc->getKey(), [
        'amount_minor' => 100,
        'currency' => 'MYR',
        'payment_method' => 'cash',
    ]);

    $payment = DocPayment::query()->findOrFail($paymentId);

    expect($payment->paid_at->diffInSeconds(CarbonImmutable::now()))->toBeLessThan(10);
});

it('rejects an unparseable payment date', function (): void {
    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'total_minor' => 100,
        'currency' => 'MYR',
    ]);

    expect(fn (): mixed => app(DocumentPaymentRecorder::class)->record((string) $doc->getKey(), [
        'amount_minor' => 100,
        'currency' => 'MYR',
        'payment_method' => 'cash',
        'paid_at' => 'not-a-date',
    ]))->toThrow(InvalidArgumentException::class, 'paid_at');
});

it('rejects malformed payment details without recording financial evidence', function (array $details, string $field): void {
    $doc = Doc::factory()->create(['status' => Sent::class, 'total_minor' => 100, 'currency' => 'MYR']);
    $data = array_replace(['amount_minor' => 25, 'payment_method' => 'cash'], $details);

    expect(fn () => app(DocumentPaymentRecorder::class)->record((string) $doc->getKey(), $data))
        ->toThrow(InvalidArgumentException::class, $field)
        ->and($doc->payments()->count())->toBe(0)
        ->and($doc->refresh()->status->equals(Sent::class))->toBeTrue();
})->with([
    'array currency' => [['currency' => ['MYR']], 'currency'],
    'blank date' => [['paid_at' => '   '], 'paid_at'],
    'empty date' => [['paid_at' => ''], 'paid_at'],
    'oversized reference' => [['reference' => str_repeat('x', 256)], 'reference'],
    'oversized notes' => [['notes' => str_repeat('x', 501)], 'notes'],
]);

it('validates payment details even when the transaction id already exists', function (): void {
    $doc = Doc::factory()->create(['status' => Sent::class, 'total_minor' => 100, 'currency' => 'MYR']);
    $recorder = app(DocumentPaymentRecorder::class);
    $data = ['amount_minor' => 25, 'payment_method' => 'cash', 'transaction_id' => 'payment-retry-validation'];

    $id = $recorder->record((string) $doc->getKey(), $data);

    expect(fn () => $recorder->record((string) $doc->getKey(), [...$data, 'paid_at' => ' ']))
        ->toThrow(InvalidArgumentException::class, 'paid_at')
        ->and($recorder->record((string) $doc->getKey(), $data))->toBe($id)
        ->and($doc->payments()->count())->toBe(1);
});
