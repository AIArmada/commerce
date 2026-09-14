<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Docs\Enums\DocPaymentStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\Docs\States\Sent;
use AIArmada\FilamentDocs\Actions\RecordPaymentAction;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\TextInput;

uses(TestCase::class);

it('counts paid rows only when computing the outstanding balance', function (): void {
    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'total_minor' => 100,
        'currency' => 'MYR',
    ]);

    $makePayment = function (DocPaymentStatus $status, int $amount) use ($doc): void {
        DocPayment::query()->create([
            'doc_id' => $doc->id,
            'status' => $status,
            'amount_minor' => $amount,
            'currency' => 'MYR',
            'payment_method' => 'cash',
            'paid_at' => CarbonImmutable::now(),
        ]);
    };

    $makePayment(DocPaymentStatus::Paid, 30);
    $makePayment(DocPaymentStatus::Refunded, 50);
    $makePayment(DocPaymentStatus::Failed, 20);
    $makePayment(DocPaymentStatus::Voided, 10);

    $totalPaid = (new ReflectionMethod(RecordPaymentAction::class, 'getTotalPaid'))
        ->invoke(null, $doc->fresh());

    expect($totalPaid)->toBe(30);

    $schema = (new ReflectionMethod(RecordPaymentAction::class, 'getFormSchema'))
        ->invoke(null, $doc->fresh());

    /** @var TextInput|null $amountInput */
    $amountInput = collect($schema)
        ->first(fn ($component) => $component instanceof TextInput && $component->getName() === 'amount_minor');

    expect($amountInput)->toBeInstanceOf(TextInput::class)
        ->and($amountInput->getMaxValue())->toEqual(70)
        ->and($amountInput->getDefaultState())->toEqual(70);
});
