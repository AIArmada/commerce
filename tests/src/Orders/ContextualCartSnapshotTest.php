<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\PurchaseContext;
use AIArmada\Orders\Support\CartLineSnapshot;

it('binds purchase context to checkout content signatures and order metadata', function (): void {
    $morning = new PurchaseContext(['window' => 'am', 'resource' => 'A']);
    $evening = new PurchaseContext(['window' => 'pm', 'resource' => 'A']);

    $buildLine = static fn (PurchaseContext $context): CartLineSnapshot => CartLineSnapshot::fromArray([
        'id' => 'line-1',
        'name' => 'Generic Service',
        'price' => 200,
        'quantity' => 2,
        'attributes' => [
            PurchaseContext::ATTRIBUTE_CONTEXT => $context->values,
            PurchaseContext::ATTRIBUTE_FINGERPRINT => $context->fingerprint,
        ],
    ]);

    $before = $buildLine($morning);
    $after = $buildLine($evening);

    expect($before->contentFingerprint())->not->toBe($after->contentFingerprint())
        ->and($before->orderItemMetadata()['purchase_context'])->toBe($morning->values)
        ->and($before->orderItemMetadata()['purchase_context_fingerprint'])->toBe($morning->fingerprint);
});
