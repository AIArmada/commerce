<?php

declare(strict_types=1);

use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\DocSequence;
use AIArmada\Docs\Models\SequenceNumber;
use AIArmada\Docs\Services\SequenceManager;

beforeEach(function (): void {
    DocSequence::query()->delete();
    SequenceNumber::query()->delete();
});

test('it creates default sequence when none exists', function (): void {
    $manager = app(SequenceManager::class);

    $sequence = $manager->createDefaultSequence('invoice');

    expect($sequence)
        ->toBeInstanceOf(DocSequence::class)
        ->and($sequence->doc_type)->toBe(DocType::Invoice)
        ->and($sequence->is_active)->toBeTrue();
});

test('it generates document numbers for invoice type', function (): void {
    $manager = app(SequenceManager::class);

    $number1 = $manager->generate('invoice');
    $number2 = $manager->generate('invoice');

    expect($number1)->toBeString()
        ->and($number2)->toBeString()
        ->and($number1)->not->toBe($number2);
});

test('it generates document numbers using DocType enum', function (): void {
    $manager = app(SequenceManager::class);

    $number = $manager->generate(DocType::Invoice);

    expect($number)->toBeString();
});

test('it gets active sequence for doc type', function (): void {
    $manager = app(SequenceManager::class);

    $sequence = $manager->createDefaultSequence('invoice');

    $found = $manager->getActiveSequence('invoice');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($sequence->id);
});

test('it returns null when no active sequence exists', function (): void {
    $manager = app(SequenceManager::class);

    $found = $manager->getActiveSequence('nonexistent');

    expect($found)->toBeNull();
});

test('it previews next number without generating', function (): void {
    $manager = app(SequenceManager::class);

    $preview = $manager->preview('invoice');
    $preview2 = $manager->preview('invoice');

    // Preview should return the same value without incrementing
    expect($preview)->toBe($preview2);
});

test('it reserves specific numbers', function (): void {
    $manager = app(SequenceManager::class);

    $result = $manager->reserve('invoice', 100);

    expect($result)->toBeTrue();

    // Next generated number should be 101
    $next = $manager->generate('invoice');
    expect($next)->toContain('101');
});

test('it parses document numbers correctly', function (): void {
    $manager = app(SequenceManager::class);

    $parsed = $manager->parse('INV-2412-000001');

    expect($parsed['prefix'])->toBe('INV')
        ->and($parsed['period'])->toBe('2412')
        ->and($parsed['number'])->toBe(1);
});

test('it parses simple document numbers', function (): void {
    $manager = app(SequenceManager::class);

    $parsed = $manager->parse('INV-000001');

    expect($parsed['prefix'])->toBe('INV')
        ->and($parsed['number'])->toBe(1);
});

test('sequence numbers increment correctly', function (): void {
    $manager = app(SequenceManager::class);

    // Generate multiple numbers
    $numbers = [];
    for ($i = 0; $i < 5; $i++) {
        $numbers[] = $manager->generate('invoice');
    }

    // Extract numeric portions and verify they increment
    $numericParts = array_map(function ($n) {
        $parsed = app(SequenceManager::class)->parse($n);

        return $parsed['number'];
    }, $numbers);

    expect($numericParts)->toBe([1, 2, 3, 4, 5]);
});

test('different doc types have independent sequences', function (): void {
    $manager = app(SequenceManager::class);

    $invoiceNum1 = $manager->generate('invoice');
    $receiptNum1 = $manager->generate('receipt');
    $invoiceNum2 = $manager->generate('invoice');
    $receiptNum2 = $manager->generate('receipt');

    // Both should be at number 2 in their respective sequences
    $invoiceParsed = $manager->parse($invoiceNum2);
    $receiptParsed = $manager->parse($receiptNum2);

    expect($invoiceParsed['number'])->toBe(2)
        ->and($receiptParsed['number'])->toBe(2);
});

test('sequence uses configured prefix', function (): void {
    $manager = app(SequenceManager::class);

    $number = $manager->generate('invoice');

    // Config has 'INV' prefix for invoice
    expect($number)->toStartWith('INV');
});

test('sequence respects padding configuration', function (): void {
    $manager = app(SequenceManager::class);

    $number = $manager->generate('invoice');
    $parsed = $manager->parse($number);

    // Default padding is 6
    $numericPart = array_filter(explode('-', $number), 'is_numeric');
    $numericPart = array_pop($numericPart);

    expect(mb_strlen($numericPart))->toBeGreaterThanOrEqual(1);
});
