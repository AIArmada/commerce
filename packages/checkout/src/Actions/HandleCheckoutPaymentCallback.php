<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Actions;

use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Data\CheckoutCallbackResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Support\CheckoutCallbackStatePolicy;
use Illuminate\Support\Facades\DB;

final readonly class HandleCheckoutPaymentCallback
{
    public function __construct(
        private CheckoutServiceInterface $checkoutService,
        private CheckoutCallbackStatePolicy $statePolicy,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $sessionId, string $callbackType, array $payload = []): CheckoutCallbackResult
    {
        return DB::transaction(function () use ($sessionId, $callbackType, $payload): CheckoutCallbackResult {
            $session = CheckoutSession::withoutOwnerScope()
                ->whereKey($sessionId)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                return CheckoutCallbackResult::notFound();
            }

            if ($this->statePolicy->isCallbackIdempotent($session)) {
                return CheckoutCallbackResult::alreadyCompleted($session);
            }

            if (! $this->statePolicy->canHandleCallback($session, $callbackType)) {
                return CheckoutCallbackResult::notFound();
            }

            $result = $this->checkoutService->handlePaymentCallback($session, $callbackType, $payload);

            return CheckoutCallbackResult::processed($session->fresh() ?? $session, $result);
        }, 3);
    }
}
