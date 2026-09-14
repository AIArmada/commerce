<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\TestCase;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\Docs\Services\DocPaymentRecorder;
use AIArmada\Docs\States\Sent;
use AIArmada\FilamentDocs\Actions\RecordPaymentAction;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(TestCase::class);

function filamentDocs_invokeRecordPayment(Doc $record, array $data): void
{
    $method = new ReflectionMethod(RecordPaymentAction::class, 'recordPayment');

    $method->invoke(null, $record, $data);
}

it('surfaces recorder overpayments as validation errors instead of 500s (M1)', function (): void {
    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'total_minor' => 100,
        'currency' => 'MYR',
    ]);

    try {
        filamentDocs_invokeRecordPayment($doc, [
            'amount_minor' => 101,
            'payment_method' => 'cash',
            'reference' => 'OVER-1',
            'paid_at' => now(),
            'notes' => null,
        ]);

        $this->fail('Expected a ValidationException for the overpayment.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('amount_minor');
    }

    expect(DocPayment::query()->where('doc_id', $doc->id)->count())->toBe(0);
});

it('notifies instead of throwing when payment recording fails unexpectedly (M1)', function (): void {
    $doc = Doc::factory()->create([
        'status' => Sent::class,
        'total_minor' => 100,
        'currency' => 'MYR',
    ]);

    app()->bind(DocPaymentRecorder::class, fn (): never => throw new RuntimeException('gateway down'));

    filamentDocs_invokeRecordPayment($doc, [
        'amount_minor' => 10,
        'payment_method' => 'cash',
        'paid_at' => now(),
    ]);

    $notifications = session()->get('filament.notifications', []);
    expect($notifications)->not()->toBeEmpty();
    expect(collect($notifications)->last()['title'])->toBe('Payment Failed');
});

it('rejects cross-owner payment recording (M1/L2)', function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);

    $ownerA = User::query()->create([
        'name' => 'Record Owner A',
        'email' => 'record-owner-a@example.test',
        'password' => bcrypt('password'),
    ]);
    $ownerB = User::query()->create([
        'name' => 'Record Owner B',
        'email' => 'record-owner-b@example.test',
        'password' => bcrypt('password'),
    ]);

    $doc = OwnerContext::withOwner($ownerB, fn (): Doc => Doc::factory()->create([
        'status' => Sent::class,
        'total_minor' => 100,
        'currency' => 'MYR',
    ]));

    OwnerContext::withOwner($ownerA, function () use ($doc): void {
        expect(fn (): mixed => filamentDocs_invokeRecordPayment($doc, [
            'amount_minor' => 10,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]))->toThrow(NotFoundHttpException::class);
    });

    expect(DocPayment::query()->where('doc_id', $doc->id)->count())->toBe(0);
});
