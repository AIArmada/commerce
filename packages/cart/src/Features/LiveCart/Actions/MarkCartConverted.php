<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\LiveCart\Actions;

use AIArmada\Cart\Exceptions\CartConflictException;
use AIArmada\Cart\Features\LiveCart\Models\CartModel;
use Carbon\CarbonImmutable;

final readonly class MarkCartConverted
{
    /**
     * Mark cart as converted (checked out).
     *
     * Idempotent: a second call is a no-op. The transition is applied with a
     * version-checked atomic update so concurrent checkouts cannot both win.
     */
    public function execute(CartModel $cart): void
    {
        if ($cart->checked_out_at !== null) {
            return;
        }

        if (! $cart->exists) {
            $cart->checked_out_at = CarbonImmutable::now();
            $cart->save();

            return;
        }

        if ($cart->version === null) {
            $cart->refresh();

            if ($cart->checked_out_at !== null) {
                return;
            }
        }

        $attemptedVersion = (int) $cart->version;
        $now = CarbonImmutable::now();

        $updated = CartModel::query()
            ->whereKey($cart->getKey())
            ->where('version', $attemptedVersion)
            ->whereNull('checked_out_at')
            ->update([
                'checked_out_at' => $now,
                'version' => $attemptedVersion + 1,
                'updated_at' => $now,
            ]);

        if ($updated > 0) {
            $cart->checked_out_at = $now;
            $cart->version = $attemptedVersion + 1;

            return;
        }

        $cart->refresh();

        if ($cart->checked_out_at !== null) {
            return;
        }

        throw new CartConflictException(
            'Cart was modified by another request during conversion',
            $attemptedVersion,
            (int) $cart->version,
        );
    }
}
