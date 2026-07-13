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
use AIArmada\Docs\Support\TemplateBlockRegistry;
use Illuminate\Validation\ValidationException;

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
                'unit_price_minor' => 100,
            ],
            [
                'name' => 'Product B',
                'quantity' => 1,
                'unit_price_minor' => 50,
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
        ->and($doc->subtotal_minor)->toBe(250)
        ->and($doc->total_minor)->toBe(250)
        ->and($doc->items)->toBeArray()->toHaveCount(2);
});

test('it calculates totals correctly', function (): void {
    $service = app(DocService::class);

    $doc = $service->create(DocData::from([
        'items' => [
            ['name' => 'Item 1', 'quantity' => 2, 'unit_price_minor' => 50],
            ['name' => 'Item 2', 'quantity' => 3, 'unit_price_minor' => 30],
        ],
        'tax_rate_basis_points' => 600,
        'discount_amount_minor' => 10,
    ]));

    expect($doc->subtotal_minor)->toBe(190)
        ->and($doc->tax_amount_minor)->toBe(11)
        ->and($doc->discount_amount_minor)->toBe(10)
        ->and($doc->total_minor)->toBe(191);
});

test('it can update doc status', function (): void {
    $service = app(DocService::class);

    $doc = $service->create(DocData::from([
        'items' => [['name' => 'Item', 'quantity' => 1, 'unit_price_minor' => 100]],
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
        'items' => [['name' => 'Item', 'quantity' => 1, 'unit_price_minor' => 100]],
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
        'items' => [['name' => 'Item', 'quantity' => 1, 'unit_price_minor' => 100]],
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
        'doc_type' => 'invoice',
        'is_default' => true,
        'layout' => TemplateBlockRegistry::defaultLayout(),
    ]);

    $service = app(DocService::class);

    $doc = $service->create(DocData::from([
        'items' => [['name' => 'Item', 'quantity' => 1, 'unit_price_minor' => 100]],
    ]));

    expect($doc->template)->not->toBeNull()
        ->and($doc->template->slug)->toBe('test-default');
});

test('it can use custom template', function (): void {
    $template = DocTemplate::create([
        'name' => 'Custom Template',
        'slug' => 'custom',
        'doc_type' => 'invoice',
        'is_default' => false,
        'layout' => TemplateBlockRegistry::defaultLayout(),
    ]);

    $service = app(DocService::class);

    $doc = $service->create(DocData::from([
        'doc_template_id' => $template->id,
        'items' => [['name' => 'Item', 'quantity' => 1, 'unit_price_minor' => 100]],
    ]));

    expect($doc->template)->not->toBeNull()
        ->and($doc->template->slug)->toBe('custom');
});

test('it rejects templates from a different document type', function (): void {
    $template = DocTemplate::create([
        'name' => 'Quotation Template',
        'slug' => 'quotation-template',
        'doc_type' => 'quotation',
        'is_default' => false,
        'layout' => TemplateBlockRegistry::defaultLayout(),
    ]);

    expect(fn (): Doc => app(DocService::class)->create(DocData::from([
        'doc_type' => 'invoice',
        'doc_template_id' => $template->id,
        'items' => [['name' => 'Item', 'quantity' => 1, 'unit_price_minor' => 100]],
    ])))->toThrow(ValidationException::class);
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
                    'unit_price_minor' => 100,
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
