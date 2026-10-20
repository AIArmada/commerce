<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Steps;

use AIArmada\Checkout\Contracts\PaymentCompensationInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\ProviderAwarePaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentAttemptClaim;
use AIArmada\Checkout\Data\PaymentFailureOrigin;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\PaymentClaimStatus;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Events\CheckoutPaymentCompleted;
use AIArmada\Checkout\Exceptions\PaymentAttemptExecutionException;
use AIArmada\Checkout\Exceptions\PaymentException;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\PaymentAttemptManager;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\Processing;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessPaymentStep extends AbstractCheckoutStep
{
    public function __construct(
        private readonly PaymentGatewayResolverInterface $paymentResolver,
        private readonly ?PaymentAttemptManager $attemptManager = null,
    ) {}

    public function getIdentifier(): string
    {
        return 'process_payment';
    }

    public function getName(): string
    {
        return 'Process Payment';
    }

    /**
     * @return array<string>
     */
    public function getDependencies(): array
    {
        return ['calculate_shipping'];
    }

    /**
     * @return array<string, string>
     */
    public function validate(CheckoutSession $session): array
    {
        $errors = [];

        if ($session->grand_total <= 0) {
            // Free order - no payment needed
            return $errors;
        }

        $gateway = $session->selected_payment_gateway ?? config('checkout.payment.default_gateway');

        // An attempt that must be reconciled is validated against its frozen
        // gateway, never against a newly selected one that does not own it.
        $gatewayToValidate = $this->attemptManager()->reconcilableGateway($session) ?? $gateway;

        if ($gatewayToValidate !== null && ! $this->paymentResolver->hasGateway($gatewayToValidate)) {
            $errors['payment_gateway'] = "Selected payment gateway '{$gatewayToValidate}' is not available";
        }

        return $errors;
    }

    public function handle(CheckoutSession $session): StepResult
    {
        // Handle free orders (e.g., 100% discount)
        if ($session->grand_total <= 0) {
            $paymentData = array_merge($session->payment_data ?? [], [
                'type' => 'free_order',
                'processed_at' => CarbonImmutable::now()->toIso8601String(),
            ]);

            $session->persistState([
                'payment_data' => $paymentData,
            ]);
            if (! $session->status->is(Processing::class)) {
                $session->transitionStatus(Processing::class);
            }

            return $this->success('No payment required - free order');
        }

        $selectedGateway = $session->selected_payment_gateway ?? $this->paymentResolver->getDefaultGateway();
        $providerHint = $this->providerHint($session);

        // The frozen identity of a reconcilable attempt decides which
        // processor is checked and driven. A newly selected gateway only
        // shapes a genuinely new generation and must not make recovery of
        // the owning attempt depend on a processor it never used.
        $reconcilableGateway = $this->attemptManager()->reconcilableGateway($session);

        if ($reconcilableGateway !== null) {
            $candidateGateway = $reconcilableGateway;
            $candidateProcessor = $this->paymentResolver->resolve($candidateGateway);

            if (! $candidateProcessor->isAvailable($session)) {
                return $this->failed('Selected payment method is not available', [
                    'payment' => 'Payment method not available for this order',
                ]);
            }

            $newClaimProvider = null;
        } else {
            $candidateGateway = $selectedGateway;
            $candidateProcessor = $this->paymentResolver->resolve($candidateGateway);

            if (! $candidateProcessor->isAvailable($session)) {
                return $this->failed('Selected payment method is not available', [
                    'payment' => 'Payment method not available for this order',
                ]);
            }

            // Resolve the concrete provider before the claim is persisted so
            // an unresolved default is never frozen into the attempt identity.
            $newClaimProvider = $candidateProcessor instanceof ProviderAwarePaymentProcessorInterface
                ? $candidateProcessor->resolveProvider($providerHint)
                : $providerHint;
        }

        // Claim one immutable attempt generation under the session row lock.
        // Concurrent callers and stale replays observe the authoritative
        // state instead of creating a second charge. Adoption keeps the
        // frozen identity and ignores the current selection/provider.
        $claim = $this->attemptManager()->claim($session, $selectedGateway, $newClaimProvider);

        if (! $claim->proceeds()) {
            return $this->observed(
                $claim->status === PaymentClaimStatus::Completed
                    ? 'Payment was already completed by another flow'
                    : 'Payment attempt is owned by another flow',
                ['payment_claim' => $claim->status->value],
            );
        }

        $processor = $candidateProcessor;

        try {
            if ($claim->gateway !== $candidateGateway) {
                // The authoritative claim does not match the read-only recovery
                // hint (a race with a concurrent outcome). Re-resolve from the
                // claim and verify the frozen processor is usable before I/O.
                $processor = $this->paymentResolver->resolve($claim->gateway);

                if (! $processor->isAvailable($session)) {
                    $origin = PaymentFailureOrigin::fromClaim($claim);

                    return $this->failed('Selected payment method is not available', [
                        'payment' => 'Payment method not available for this order',
                    ])->withFailureOrigin($origin)
                        ->withExecutionOrigin($origin);
                }
            }

            // Replay the exact request frozen when the attempt was claimed.
            // A claim without its snapshot is incomplete: reconstructing any
            // field from mutable session state would send different inputs
            // under the same idempotency key.
            if ($claim->request === null) {
                throw PaymentException::incompleteAttemptIdentity((string) $session->getKey(), $claim->number);
            }

            $paymentRequest = PaymentRequest::fromSnapshot($claim->request);

            // Gateway I/O happens outside any transaction; the claim already
            // fenced ownership of this attempt.
            $result = $processor->createPayment($session, $paymentRequest);

            // Record the result under the row lock, but only while this attempt
            // still owns the session. A callback winner, a cancel, or a later
            // attempt rejects the write and this run stays quiet.
            $recorded = $this->attemptManager()->record($session, $claim, $result);
        } catch (Throwable $e) {
            // Carry this execution's claimed generation out of the step so the
            // pipeline can fence step, compensation, lifecycle, error, and
            // event writes by attempt identity under the row lock instead of
            // deciding ownership from the persisted status alone.
            throw PaymentAttemptExecutionException::during($claim, $e);
        }

        if (! $recorded) {
            $this->voidSupersededPayment($session, $claim, $result);

            return $this->observed('Payment result was superseded by a concurrent update', [
                'payment_claim' => PaymentClaimStatus::Observed->value,
            ]);
        }

        // Handle redirect-based payments
        if ($result->requiresRedirect()) {
            return $this->success('Payment initiated - redirect required', [
                'redirect_url' => $result->redirectUrl,
                'payment_id' => $result->paymentId,
            ])->withExecutionOrigin($recorded);
        }

        // Handle immediate success
        if ($result->isSuccessful()) {
            event(new CheckoutPaymentCompleted(
                session: $session,
                paymentData: $session->payment_data ?? [],
            ));

            return $this->success('Payment completed', [
                'payment_id' => $result->paymentId,
                'transaction_id' => $result->transactionId,
            ])->withExecutionOrigin($recorded);
        }

        // Unknown or non-terminal outcomes (an uncertain creation response,
        // a processing charge, or a pending result without a redirect) stay
        // recoverable: the outcome is recorded and the pipeline observes
        // instead of failing and compensating before payment truth is known.
        if (! $result->status->isFinal()) {
            return $this->observed($result->message ?? 'Payment outcome is not final', [
                'payment_claim' => PaymentClaimStatus::Observed->value,
                'payment_status' => $result->status->value,
            ]);
        }

        return $this->failed($result->message ?? 'Payment failed', $result->errors)
            ->withFailureOrigin($recorded)
            ->withExecutionOrigin($recorded);
    }

    public function compensate(CheckoutSession $session): StepResult
    {
        $paymentId = $session->payment_id
            ?? data_get($session->payment_data ?? [], 'payment_id');

        if (! is_string($paymentId) || $paymentId === '') {
            return $this->compensated('No payment was created', [
                'operation' => 'payment_compensation',
                'skipped' => true,
            ]);
        }

        $paymentData = $session->payment_data ?? [];
        $frozenClaim = $this->attemptManager()->frozenIdentity($session);
        $gateway = is_array($frozenClaim) && is_string($frozenClaim['gateway'] ?? null) && $frozenClaim['gateway'] !== ''
            ? $frozenClaim['gateway']
            : $session->selected_payment_gateway;
        $provider = $this->attemptManager()->frozenProvider($session) ?? data_get($paymentData, 'provider');
        $paymentStatus = PaymentStatus::tryFrom((string) data_get($paymentData, 'status', PaymentStatus::Pending->value))
            ?? PaymentStatus::Pending;
        $operation = match ($paymentStatus) {
            PaymentStatus::Completed, PaymentStatus::PartiallyRefunded => 'refund',
            PaymentStatus::Refunded, PaymentStatus::Cancelled => 'none',
            default => 'void',
        };

        if ($operation === 'none') {
            return $this->compensated('Payment is already compensated', [
                'operation' => $operation,
                'payment_id' => $paymentId,
                'payment_status' => $paymentStatus->value,
                'skipped' => true,
            ]);
        }

        if ($this->paymentAlreadyCompensated($session, $paymentId)) {
            return $this->compensated('Payment compensation already recorded for this payment', [
                'operation' => $operation,
                'payment_id' => $paymentId,
                'payment_status' => $paymentStatus->value,
                'skipped' => true,
            ]);
        }

        try {
            $processor = $this->paymentResolver->resolve($gateway);

            if (! $processor instanceof PaymentCompensationInterface) {
                return $this->failed('Payment processor cannot compensate this payment', [
                    'payment' => 'The resolved processor must support refunds and voids',
                ]);
            }

            $reason = 'Checkout compensation';
            $result = $operation === 'refund'
                ? $this->refund($processor, $provider, $paymentId, $session->grand_total, $reason)
                : $this->void($processor, $provider, $paymentId, $reason);

            if ($result->status === PaymentStatus::Failed) {
                return $this->failed(
                    $result->message ?? 'Payment compensation failed',
                    $result->errors,
                );
            }

            return $this->compensated(
                $result->message ?? 'Payment compensated',
                [
                    'operation' => $operation,
                    'payment_id' => $paymentId,
                    'payment_status' => $result->status->value,
                    'provider' => $result->provider ?? $provider,
                    'gateway_response' => $result->gatewayResponse,
                ],
            );
        } catch (Throwable $e) {
            return $this->failed('Payment compensation failed', [
                'payment' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check the persisted compensation log for a completed refund/void of
     * this exact payment so repeated compensation runs do not hit the
     * gateway twice. Scoped by payment id on purpose: a retry creates a new
     * payment that must still be compensated.
     */
    private function paymentAlreadyCompensated(CheckoutSession $session, string $paymentId): bool
    {
        foreach ($session->getCompensationLog() as $entry) {
            if (($entry['step_identifier'] ?? null) !== $this->getIdentifier()) {
                continue;
            }

            if (($entry['status'] ?? null) !== StepStatus::RolledBack->value) {
                continue;
            }

            if (($entry['data']['payment_id'] ?? null) !== $paymentId) {
                continue;
            }

            if (in_array($entry['data']['operation'] ?? null, ['refund', 'void'], true)) {
                return true;
            }
        }

        return false;
    }

    private function refund(
        PaymentCompensationInterface $processor,
        mixed $provider,
        string $paymentId,
        int $amount,
        string $reason,
    ): PaymentResult {
        if (
            is_string($provider)
            && $provider !== ''
            && $processor instanceof ProviderAwarePaymentProcessorInterface
            && $provider !== $processor->getIdentifier()
        ) {
            return $processor->refundForProvider($provider, $paymentId, $amount, $reason);
        }

        return $processor->refund($paymentId, $amount, $reason);
    }

    private function void(
        PaymentCompensationInterface $processor,
        mixed $provider,
        string $paymentId,
        string $reason,
    ): PaymentResult {
        if (
            is_string($provider)
            && $provider !== ''
            && $processor instanceof ProviderAwarePaymentProcessorInterface
            && $provider !== $processor->getIdentifier()
        ) {
            return $processor->voidPaymentForProvider($provider, $paymentId, $reason);
        }

        return $processor->voidPayment($paymentId, $reason);
    }

    /**
     * The mutable provider hint the host set for the next attempt. Only a
     * brand-new generation consults it; recovery keeps the frozen provider.
     */
    private function providerHint(CheckoutSession $session): ?string
    {
        $provider = data_get($session->payment_data ?? [], 'provider');

        if (! is_string($provider)) {
            return null;
        }

        $provider = mb_trim($provider);

        return $provider !== '' ? $provider : null;
    }

    /**
     * Void a payment this run created after another flow already owns the
     * outcome. The void is tied to the losing attempt's processor and
     * provider, never to the winning payment, and is skipped when the
     * persisted payment is this same charge (the winner may still settle it)
     * or when an unknown winner is mid-flight.
     */
    private function voidSupersededPayment(CheckoutSession $session, PaymentAttemptClaim $claim, PaymentResult $result): void
    {
        $paymentId = $result->paymentId;

        if (! is_string($paymentId) || $paymentId === '') {
            return;
        }

        $persisted = $session->payment_id;

        if (is_string($persisted) && $persisted !== '') {
            if ($persisted === $paymentId) {
                return;
            }
        } elseif ($session->status->is(Completed::class) || $session->status->is(AwaitingPayment::class)) {
            // The winning flow may yet settle on this very purchase; do not
            // void what might be the session's payment.
            return;
        }

        try {
            $processor = $this->paymentResolver->resolve($claim->gateway);

            if (! $processor instanceof PaymentCompensationInterface) {
                return;
            }

            $provider = $result->provider ?? $claim->provider;
            $reason = 'Superseded by a concurrent checkout update';

            if (
                is_string($provider)
                && $provider !== ''
                && $processor instanceof ProviderAwarePaymentProcessorInterface
                && $provider !== $processor->getIdentifier()
            ) {
                $processor->voidPaymentForProvider($provider, $paymentId, $reason);

                return;
            }

            $processor->voidPayment($paymentId, $reason);
        } catch (Throwable $e) {
            Log::warning('Checkout failed to void a superseded payment', [
                'session_id' => $session->getKey(),
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function attemptManager(): PaymentAttemptManager
    {
        return $this->attemptManager ?? app(PaymentAttemptManager::class);
    }
}
