<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Listeners;

use AIArmada\Checkout\Data\DiscountCommitment;
use AIArmada\Checkout\Events\CheckoutCompleted;
use AIArmada\Vouchers\Contracts\VoucherServiceInterface;

/**
 * Redeems voucher commitments only after checkout has created its order.
 *
 * Discount evaluation happens before order creation, so consuming a voucher
 * from a discount provider's commit method would either attach usage to the
 * checkout-session id or consume it before the payment can succeed.
 */
final class RedeemVouchersOnCheckoutCompleted
{
    public function handle(CheckoutCompleted $event): void
    {
        if (! interface_exists(VoucherServiceInterface::class)) {
            return;
        }

        $session = $event->session;
        $commitments = $this->commitments($session->discount_data ?? []);
        $orderId = $session->order_id;
        $voucherService = app(VoucherServiceInterface::class);

        foreach ($commitments as $commitment) {
            if ($commitment->providerKey !== 'vouchers' || $commitment->reservationToken === '') {
                continue;
            }

            try {
                if (is_string($orderId) && $orderId !== '') {
                    $currency = is_string($session->currency) && $session->currency !== ''
                        ? $session->currency
                        : null;

                    $voucherService->redeem(
                        code: $commitment->reservationToken,
                        orderId: $orderId,
                        discountAmount: $commitment->appliedAmount,
                        currency: $currency,
                    );
                }
            } finally {
                $voucherService->release($commitment->reservationToken, (string) $session->getKey());
            }
        }
    }

    /**
     * @param  array<string, mixed>  $discountData
     * @return array<int, DiscountCommitment>
     */
    private function commitments(array $discountData): array
    {
        $rawCommitments = $discountData['commitments'] ?? [];

        if (! is_array($rawCommitments)) {
            return [];
        }

        $commitments = [];

        foreach ($rawCommitments as $rawCommitment) {
            if (is_array($rawCommitment)) {
                $commitments[] = DiscountCommitment::fromArray($rawCommitment);
            }
        }

        return $commitments;
    }
}
