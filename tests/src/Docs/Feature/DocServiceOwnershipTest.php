<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Docs\Contracts\DocumentEmailSender;
use AIArmada\Docs\Contracts\DocumentLifecycle;
use AIArmada\Docs\Contracts\DocumentPaymentRecorder;
use AIArmada\Docs\Contracts\DocumentVersions;
use AIArmada\Docs\Data\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocPayment;
use AIArmada\Docs\Services\DocService;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function (): void {
    config()->set('docs.owner.enabled', true);
    config()->set('docs.owner.include_global', false);
    config()->set('docs.owner.auto_assign_on_create', true);
});

test('doc service assigns explicit owners and blocks cross-owner payments', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Docs Owner A',
        'email' => 'docs-owner-a@example.test',
        'password' => bcrypt('password'),
    ]);
    $ownerB = User::query()->create([
        'name' => 'Docs Owner B',
        'email' => 'docs-owner-b@example.test',
        'password' => bcrypt('password'),
    ]);

    $service = app(DocService::class);
    $doc = $service->createFromType(
        DocType::Invoice,
        [
            'total_minor' => 100,
            'currency' => 'MYR',
        ],
        $ownerA,
    );
    OwnerContext::withOwner($ownerA, static fn (): mixed => $doc->markAsSent());

    expect($doc->owner_type)->toBe($ownerA->getMorphClass())
        ->and((string) $doc->owner_id)->toBe((string) $ownerA->getKey());

    expect(fn (): mixed => OwnerContext::withOwner($ownerB, fn (): DocPayment => $service->recordPayment($doc, [
        'amount_minor' => 25,
        'payment_method' => 'cash',
    ])))->toThrow(AuthorizationException::class);

    OwnerContext::withOwner($ownerA, function () use ($doc): void {
        expect($doc->payments()->count())->toBe(0);
    });
});

test('global document visibility does not grant tenant write access', function (): void {
    config()->set('docs.owner.include_global', true);
    $owner = User::factory()->create();
    $doc = OwnerContext::withOwner(null, function (): Doc {
        $doc = app(DocService::class)->createFromType(DocType::Invoice, [
            'total_minor' => 100,
            'currency' => 'MYR',
        ]);
        $doc->markAsSent();

        return $doc;
    });

    $docId = (string) $doc->getKey();
    $payment = ['amount_minor' => 25, 'payment_method' => 'cash'];

    OwnerContext::withOwner($owner, function () use ($docId, $payment): void {
        expect(Doc::query()->forOwner(OwnerContext::resolve(), true)->find($docId))->not->toBeNull();
        expect(fn () => app(DocumentPaymentRecorder::class)->record($docId, $payment))->toThrow(AuthorizationException::class);
        expect(fn () => app(DocumentVersions::class)->capture($docId))->toThrow(AuthorizationException::class);
        expect(fn () => app(DocumentLifecycle::class)->delete([$docId]))->toThrow(AuthorizationException::class);
        expect(fn () => app(DocumentEmailSender::class)->sendTo($docId, 'recipient@example.test'))->toThrow(AuthorizationException::class);
    });

    OwnerContext::withOwner(null, function () use ($docId, $payment): void {
        expect(app(DocumentPaymentRecorder::class)->record($docId, $payment))->not->toBeEmpty();
    });
});

test('copies documents only inside the source owner scope', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $service = app(DocService::class);

    $source = OwnerContext::withOwner($owner, fn (): Doc => $service->createFromType(
        DocType::Quotation,
        [
            'currency' => 'MYR',
            'items' => [['name' => 'Item', 'quantity' => 1, 'unit_price_minor' => 1000]],
            'discount_amount_minor' => 100,
        ],
        $owner,
    ));

    OwnerContext::withOwner($other, function () use ($service, $source, $other): void {
        expect(fn () => $service->clone($source, $other))->toThrow(AuthorizationException::class);
        expect(fn () => $service->convert($source, DocType::Invoice, $other))->toThrow(AuthorizationException::class);
    });

    OwnerContext::withOwner($owner, function () use ($service, $source, $owner, $other): void {
        expect(fn () => $service->clone($source, $other))->toThrow(AuthorizationException::class);
        expect(fn () => $service->convert($source, DocType::Invoice, $other))->toThrow(AuthorizationException::class);

        $clone = $service->clone($source);
        $converted = $service->convert($source, DocType::Invoice);

        expect((string) $clone->owner_id)->toBe((string) $owner->getKey())
            ->and((string) $converted->owner_id)->toBe((string) $owner->getKey())
            ->and($clone->discount_amount_minor)->toBe(100)
            ->and($converted->discount_amount_minor)->toBe(100);
    });
});
