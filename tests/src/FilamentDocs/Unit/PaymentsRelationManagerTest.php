<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Enums\DocPaymentStatus;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\PartiallyPaid;
use AIArmada\Docs\States\Sent;
use AIArmada\FilamentDocs\Resources\DocResource\RelationManagers\PaymentsRelationManager;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(TestCase::class);

afterEach(function (): void {
    Mockery::close();
});

function filamentDocs_paymentsTable(): Table
{
    /** @var HasTable $livewire */
    $livewire = Mockery::mock(HasTable::class);

    return app(PaymentsRelationManager::class)->table(Table::make($livewire));
}

it('exposes no payment delete actions (H3)', function (): void {
    $table = filamentDocs_paymentsTable();

    $names = [];
    foreach ($table->getRecordActions() as $action) {
        $names[] = $action->getName();
    }
    foreach ($table->getToolbarActions() as $action) {
        if ($action instanceof BulkActionGroup) {
            foreach ($action->getActions() as $nested) {
                $names[] = $nested->getName();
            }

            continue;
        }
        $names[] = $action->getName();
    }

    expect($names)->not()->toContain('delete');
    expect($names)->not()->toContain('delete_selected');
});

it('records relation-manager payments through the domain recorder (H3)', function (): void {
    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'total_minor' => 100,
        'currency' => 'MYR',
    ]);

    $payment = PaymentsRelationManager::recordPaymentForDoc($doc, [
        'amount_minor' => 40,
        'payment_method' => 'cash',
        'reference' => 'RM-001',
        'paid_at' => now(),
        'notes' => null,
    ]);

    expect($payment)->toBeInstanceOf(DocPayment::class);
    expect($payment->status)->toBe(DocPaymentStatus::Paid);
    expect($payment->currency)->toBe('MYR');

    $doc->refresh();
    expect($doc->status->equals(PartiallyPaid::class))->toBeTrue();

    PaymentsRelationManager::recordPaymentForDoc($doc, [
        'amount_minor' => 60,
        'payment_method' => 'cash',
        'paid_at' => now(),
    ]);

    $doc->refresh();
    expect($doc->status->equals(Paid::class))->toBeTrue();
    expect($doc->paid_at)->not()->toBeNull();
});

it('rejects relation-manager overpayments instead of persisting them (H3)', function (): void {
    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'total_minor' => 100,
        'currency' => 'MYR',
    ]);

    expect(fn (): DocPayment => PaymentsRelationManager::recordPaymentForDoc($doc, [
        'amount_minor' => 101,
        'payment_method' => 'cash',
        'paid_at' => now(),
    ]))->toThrow(ValidationException::class);

    expect(DocPayment::query()->where('doc_id', $doc->id)->count())->toBe(0);
});

it('keeps recorded payment amounts immutable but allows note edits (H3)', function (): void {
    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'total_minor' => 100,
        'currency' => 'MYR',
    ]);

    $payment = PaymentsRelationManager::recordPaymentForDoc($doc, [
        'amount_minor' => 40,
        'payment_method' => 'cash',
        'reference' => 'RM-001',
        'paid_at' => now(),
    ]);

    expect(fn (): DocPayment => PaymentsRelationManager::updatePaymentForDoc($doc, $payment, [
        'amount_minor' => 90,
        'currency' => 'MYR',
        'payment_method' => 'cash',
    ]))->toThrow(ValidationException::class);

    $updated = PaymentsRelationManager::updatePaymentForDoc($doc, $payment, [
        'amount_minor' => 40,
        'currency' => 'MYR',
        'payment_method' => 'cash',
        'reference' => 'RM-001-B',
        'notes' => 'corrected memo',
    ]);

    expect($updated->reference)->toBe('RM-001-B');
    expect($updated->notes)->toBe('corrected memo');
    expect($updated->amount_minor)->toBe(40);
});

it('rejects cross-owner payment writes in the relation manager (H3)', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Pay Owner A',
        'email' => 'pay-owner-a@example.test',
        'password' => bcrypt('password'),
    ]);
    $ownerB = User::query()->create([
        'name' => 'Pay Owner B',
        'email' => 'pay-owner-b@example.test',
        'password' => bcrypt('password'),
    ]);

    $doc = OwnerContext::withOwner($ownerB, fn (): Doc => Doc::factory()->create([
        'status' => Sent::class,
        'total_minor' => 100,
        'currency' => 'MYR',
    ]));

    OwnerContext::withOwner($ownerA, function () use ($doc): void {
        expect(fn (): DocPayment => PaymentsRelationManager::recordPaymentForDoc($doc, [
            'amount_minor' => 10,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]))->toThrow(NotFoundHttpException::class);
    });

    expect(DocPayment::query()->where('doc_id', $doc->id)->count())->toBe(0);
});
