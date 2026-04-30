<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\DataObjects\DocData;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocTemplate;
use AIArmada\Docs\Services\DocService;
use AIArmada\Docs\States\DocStatus;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Overdue;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\Pending;
use AIArmada\Docs\States\Sent;

test('it can generate doc numbers', function (): void {
    $service = app(DocService::class);
    $number = $service->generateNumber('invoice');

    expect($number)
        ->toBeString()
        ->toMatch('/^INV\d{2}-[A-Z0-9]{6}$/');
});

test('default strategy respects numbering format overrides', function (): void {
    $service = app(DocService::class);
    $originalFormat = config('docs.numbering.format');
    $originalPrefix = config('docs.types.invoice.numbering.prefix');

    config()->set('docs.numbering.format', [
        'year_format' => 'Y',
        'separator' => '/',
        'suffix_length' => 4,
    ]);
    config()->set('docs.types.invoice.numbering.prefix', 'BILL');

    try {
        $number = $service->generateNumber('invoice');

        expect($number)
            ->toBeString()
            ->toMatch('/^BILL\d{4}\/[A-Z0-9]{4}$/');
    } finally {
        config()->set('docs.numbering.format', $originalFormat);
        config()->set('docs.types.invoice.numbering.prefix', $originalPrefix);
    }
});

test('it can create a doc', function (): void {
    $service = app(DocService::class);

    $doc = $service->create(DocData::from([
        'doc_type' => 'invoice',
        'items' => [
            [
                'name' => 'Product A',
                'quantity' => 2,
                'price' => 100.00,
            ],
            [
                'name' => 'Product B',
                'quantity' => 1,
                'price' => 50.00,
            ],
        ],
        'customer_data' => [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ],
    ]));

    expect($doc)
        ->toBeInstanceOf(Doc::class)
        ->and($doc->doc_number)->toBeString()
        ->and($doc->doc_type)->toBe('invoice')
        ->and($doc->status->equals(Draft::class))->toBeTrue()
        ->and($doc->subtotal)->toBe('250.00')
        ->and($doc->total)->toBe('250.00')
        ->and($doc->items)->toBeArray()->toHaveCount(2);
});

test('it calculates totals correctly', function (): void {
    $service = app(DocService::class);

    $doc = $service->create(DocData::from([
        'items' => [
            ['name' => 'Item 1', 'quantity' => 2, 'price' => 50.00],
            ['name' => 'Item 2', 'quantity' => 3, 'price' => 30.00],
        ],
        'tax_rate' => 0.06,
        'discount_amount' => 10.00,
    ]));

    expect($doc->subtotal)->toBe('190.00')
        ->and($doc->tax_amount)->toBe('11.40')
        ->and($doc->discount_amount)->toBe('10.00')
        ->and($doc->total)->toBe('191.40');
});

test('it can update doc status', function (): void {
    $service = app(DocService::class);

    $doc = $service->create(DocData::from([
        'items' => [['name' => 'Item', 'quantity' => 1, 'price' => 100]],
    ]));

    expect($doc->status->equals(Draft::class))->toBeTrue();

    $service->updateStatus($doc, Paid::class, 'Payment received');

    $doc->refresh();
    expect($doc->status->equals(Paid::class))->toBeTrue();

    $history = $doc->statusHistories()->first();
    expect($history)
        ->not->toBeNull()
        ->and($history->status->equals(Paid::class))->toBeTrue()
        ->and($history->notes)->toBe('Payment received');
});

test('it can mark doc as paid', function (): void {
    $service = app(DocService::class);

    $doc = $service->create(DocData::from([
        'items' => [['name' => 'Item', 'quantity' => 1, 'price' => 100]],
    ]));

    expect($doc->isPaid())->toBeFalse();

    $doc->markAsPaid();
    $doc->refresh();

    expect($doc->isPaid())->toBeTrue()
        ->and($doc->status->equals(Paid::class))->toBeTrue()
        ->and($doc->paid_at)->not->toBeNull();
});

test('it can check if doc is overdue', function (): void {
    $service = app(DocService::class);

    $doc = $service->create(DocData::from([
        'items' => [['name' => 'Item', 'quantity' => 1, 'price' => 100]],
        'due_date' => now()->subDay(),
    ]));

    expect($doc->isOverdue())->toBeTrue();

    $doc->markAsPaid();
    expect($doc->isOverdue())->toBeFalse();
});

test('it uses default template when none specified', function (): void {
    DocTemplate::create([
        'name' => 'Test Default',
        'slug' => 'test-default',
        'view_name' => 'test-default',
        'doc_type' => 'invoice',
        'is_default' => true,
    ]);

    $service = app(DocService::class);

    $doc = $service->create(DocData::from([
        'items' => [['name' => 'Item', 'quantity' => 1, 'price' => 100]],
    ]));

    expect($doc->template)->not->toBeNull()
        ->and($doc->template->slug)->toBe('test-default');
});

test('it can use custom template', function (): void {
    $template = DocTemplate::create([
        'name' => 'Custom Template',
        'slug' => 'custom',
        'view_name' => 'custom',
        'doc_type' => 'invoice',
        'is_default' => false,
    ]);

    $service = app(DocService::class);

    $doc = $service->create(DocData::from([
        'doc_template_id' => $template->id,
        'items' => [['name' => 'Item', 'quantity' => 1, 'price' => 100]],
    ]));

    expect($doc->template)->not->toBeNull()
        ->and($doc->template->slug)->toBe('custom');
});

test('doc status has correct labels', function (): void {
    expect(DocStatus::labelFor(Draft::class))->toBe('Draft')
        ->and(DocStatus::labelFor(Paid::class))->toBe('Paid')
        ->and(DocStatus::labelFor(Overdue::class))->toBe('Overdue');
});

test('doc status has correct colors', function (): void {
    expect(DocStatus::colorFor(Draft::class))->toBe('gray')
        ->and(DocStatus::colorFor(Paid::class))->toBe('success')
        ->and(DocStatus::colorFor(Overdue::class))->toBe('danger');
});

test('it can check payable status', function (): void {
    expect(DocStatus::fromString(Pending::class)->isPayable())->toBeTrue()
        ->and(DocStatus::fromString(Sent::class)->isPayable())->toBeTrue()
        ->and(DocStatus::fromString(Paid::class)->isPayable())->toBeFalse()
        ->and(DocStatus::fromString(Draft::class)->isPayable())->toBeFalse();
});

test('it honors owner auto-assign on create config', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.auto_assign_on_create', false);

    $owner = User::query()->create([
        'name' => 'Owner Auto Assign Off',
        'email' => 'owner-auto-assign-off@example.test',
        'password' => bcrypt('password'),
    ]);

    $service = app(DocService::class);

    $doc = OwnerContext::withOwner($owner, function () use ($service): Doc {
        return $service->create(DocData::from([
            'doc_type' => 'invoice',
            'items' => [
                [
                    'name' => 'Owner Config Item',
                    'quantity' => 1,
                    'price' => 100.00,
                ],
            ],
            'customer_data' => [
                'name' => 'Config Customer',
                'email' => 'config-customer@example.test',
            ],
        ]));
    });

    expect($doc->owner_type)->toBeNull()
        ->and($doc->owner_id)->toBeNull();
});
