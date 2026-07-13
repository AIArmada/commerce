<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\PartiallyPaid;
use AIArmada\Docs\States\Sent;

it('formats minor units using each currency exponent', function (): void {
    expect(MoneyFormatter::decimalFromMinor(1_234, 'JPY'))->toBe('1,234')
        ->and(MoneyFormatter::decimalFromMinor(1_234, 'USD'))->toBe('12.34')
        ->and(MoneyFormatter::decimalFromMinor(1_234, 'KWD'))->toBe('1.234');
});

it('calculates large totals with exact integer arithmetic', function (): void {
    $totals = app(DocService::class)->calculateTotals([
        ['quantity' => 3, 'unit_price_minor' => 9_000_000_000],
        ['quantity' => 2, 'unit_price_minor' => 7_000_000_000, 'tax_amount_minor' => 17],
    ], 11, 'KWD');

    expect($totals)->toBe([
        'subtotal_minor' => 41_000_000_000,
        'tax_amount_minor' => 17,
        'total_minor' => 41_000_000_006,
    ]);
});

it('rejects removed major-unit aliases and non-integer minor units', function (): void {
    expect(fn (): DocData => DocData::from(['total' => 10.50]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn (): DocData => DocData::from(['total_minor' => 1.5]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn (): array => app(DocService::class)->calculateTotals([
            ['quantity' => 1, 'price' => 10.50],
        ]))
        ->toThrow(InvalidArgumentException::class);
});

it('uses exact integer payment comparisons and rejects overpayment and currency mismatch', function (): void {
    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'currency' => 'KWD',
        'subtotal_minor' => 1_001,
        'tax_amount_minor' => 0,
        'discount_amount_minor' => 0,
        'total_minor' => 1_001,
    ]);

    $service = app(DocService::class);
    $service->recordPayment($doc, [
        'amount_minor' => 1_000,
        'currency' => 'KWD',
        'payment_method' => 'bank_transfer',
    ]);

    expect($doc->fresh()->status->equals(PartiallyPaid::class))->toBeTrue();

    expect(fn () => $service->recordPayment($doc, [
        'amount_minor' => 2,
        'currency' => 'KWD',
        'payment_method' => 'bank_transfer',
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => $service->recordPayment($doc, [
        'amount_minor' => 1,
        'currency' => 'USD',
        'payment_method' => 'bank_transfer',
    ]))->toThrow(InvalidArgumentException::class);

    $service->recordPayment($doc, [
        'amount_minor' => 1,
        'currency' => 'KWD',
        'payment_method' => 'bank_transfer',
    ]);

    expect($doc->fresh()->status->equals(Paid::class))->toBeTrue();
});
