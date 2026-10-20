<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Actions;

use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Data\CheckoutCallbackResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\PaymentAttemptManager;
use AIArmada\Checkout\Support\CheckoutCallbackStatePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class HandleCheckoutPaymentCallback
{
    public function __construct(
        private CheckoutServiceInterface $checkoutService,
        private CheckoutCallbackStatePolicy $statePolicy,
        private ?PaymentAttemptManager $attemptManager = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>|null  $expectedGateways
     */
    public function handle(
        string $sessionId,
        string $callbackType,
        array $payload = [],
        ?array $expectedGateways = null,
    ): CheckoutCallbackResult {
        return DB::transaction(function () use ($sessionId, $callbackType, $payload, $expectedGateways): CheckoutCallbackResult {
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

            // Revalidate the route/notification gateway against the locked
            // current attempt. An outer unlocked read cannot authorize a
            // callback for a generation that changed after that read.
            if ($expectedGateways !== null) {
                $gateway = $this->attemptManager()->currentAttemptGateway($session);

                if ($gateway === null || ! in_array($gateway, $expectedGateways, true)) {
                    return CheckoutCallbackResult::notFound();
                }
            }

            $result = $this->checkoutService->handlePaymentCallback($session, $callbackType, $payload);
            $processedSession = $session->fresh() ?? $session;

            if ($callbackType === 'success' && $result->success) {
                $paymentData = $processedSession->payment_data ?? [];
                $paymentData['callback_token_consumed_at'] = CarbonImmutable::now()->toIso8601String();
                $processedSession->persistState(['payment_data' => $paymentData]);
                $processedSession->refresh();
            }

            return CheckoutCallbackResult::processed($processedSession, $result);
        }, 3);
    }

    private function attemptManager(): PaymentAttemptManager
    {
        return $this->attemptManager ?? app(PaymentAttemptManager::class);
    }
}
