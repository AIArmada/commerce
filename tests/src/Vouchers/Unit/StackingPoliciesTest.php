<?php

declare(strict_types=1);

namespace Tests\Unit\Vouchers;

use AIArmada\Vouchers\Stacking\StackingDecision;

/* StackingMode/StackingRuleType enum asserts removed; superseded by StackingEnumsTest. */

describe('StackingDecision', function (): void {
    it('can create an allowed decision', function (): void {
        $decision = StackingDecision::allow();

        expect($decision->allowed)->toBeTrue();
        expect($decision->isAllowed())->toBeTrue();
        expect($decision->isDenied())->toBeFalse();
        expect($decision->reason)->toBeNull();
        expect($decision->conflictsWith)->toBeNull();
    });

    it('can create a denied decision', function (): void {
        $decision = StackingDecision::deny('Maximum vouchers exceeded');

        expect($decision->allowed)->toBeFalse();
        expect($decision->isAllowed())->toBeFalse();
        expect($decision->isDenied())->toBeTrue();
        expect($decision->reason)->toBe('Maximum vouchers exceeded');
        expect($decision->getReason())->toBe('Maximum vouchers exceeded');
    });

    it('converts to array', function (): void {
        $decision = StackingDecision::deny('Test reason');
        $array = $decision->toArray();

        expect($array)->toBeArray();
        expect($array['allowed'])->toBeFalse();
        expect($array['reason'])->toBe('Test reason');
        expect($array['conflicts_with'])->toBeNull();
        expect($array['suggested_replacement'])->toBeNull();
    });
});

/* StackingPolicy factory/fluent asserts removed; superseded by Stacking/StackingPolicyTest. */
