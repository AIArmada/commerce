<?php

declare(strict_types=1);

namespace AIArmada\Checkout\Services;

use AIArmada\Checkout\Data\PaymentAttemptClaim;
use AIArmada\Checkout\Data\PaymentCallbackOwnership;
use AIArmada\Checkout\Data\PaymentFailureOperation;
use AIArmada\Checkout\Data\PaymentFailureOrigin;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\PaymentAttemptOutcome;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Exceptions\InvalidCheckoutStateException;
use AIArmada\Checkout\Exceptions\PaymentException;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Cancelled;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentFailed;
use AIArmada\Checkout\States\PaymentProcessing;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Support\CheckoutPaymentReference;
use AIArmada\Checkout\Support\PaymentRequestBuilder;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Claims, freezes, and fences checkout payment attempts.
 *
 * Every claim and every recorded gateway result runs under the session row
 * lock so a stale instance can never reopen a terminal session, a second
 * caller can never create a second charge for the same attempt generation,
 * and a late result can never overwrite a winning payment. `payment_attempts`
 * is the generation counter. A separate monotonic revision fences executions
 * within that generation as claims, adoptions, accepted results, and verified
 * outcomes change the session's authoritative payment state.
 *
 * Attempt outcomes are classified durably — unknown/uncertain, known
 * non-terminal, and definitively failed — and every recovery decision
 * (re-drive, release a new generation, or observe) derives from that
 * classification so claims and retries cannot diverge.
 *
 * The frozen claim is also the single source of identity for recovery:
 * gateway, concrete provider, amount, currency, reference, idempotency key,
 * payment method, and the complete provider request snapshot are captured
 * once for a new generation (with the concrete provider resolved before the
 * claim is persisted) and re-read for adoption, status queries,
 * compensation, and orphan handling instead of any mutable selection.
 */
final class PaymentAttemptManager
{
    public function __construct(
        private readonly ?PaymentRequestBuilder $requestBuilder = null,
    ) {}

    public function claim(CheckoutSession $session, string $gateway, ?string $provider = null): PaymentAttemptClaim
    {
        return DB::transaction(function () use ($session, $gateway, $provider): PaymentAttemptClaim {
            $this->lockAndRefresh($session);

            if ($session->status->is(Completed::class)) {
                return PaymentAttemptClaim::completed();
            }

            if ($session->status->isTerminal()) {
                return PaymentAttemptClaim::rejected();
            }

            $classification = $this->outcomeClassification($session);

            if ($classification === PaymentAttemptOutcome::Recorded) {
                return PaymentAttemptClaim::observed();
            }

            if ($classification === PaymentAttemptOutcome::Reconcile) {
                // An explicit retry reconciles the frozen identity of a
                // crashed or uncertain attempt. Any other caller only
                // observes; it never releases a second charge.
                $claim = data_get($session->payment_data ?? [], 'payment_attempt');

                if ($this->retryRequestedFor($session) === (int) $session->payment_attempts
                    && is_array($claim)
                ) {
                    return $this->adopt($session, $claim);
                }

                return PaymentAttemptClaim::observed();
            }

            return $this->claimNewAttempt($session, $gateway, $provider);
        });
    }

    /**
     * The frozen identity of the current attempt generation's claim, or null
     * when no claim is recorded for that generation.
     *
     * @return array<string, mixed>|null
     */
    public function frozenIdentity(CheckoutSession $session): ?array
    {
        $claim = data_get($session->payment_data ?? [], 'payment_attempt');

        if (! is_array($claim) || (int) ($claim['number'] ?? 0) !== (int) $session->payment_attempts) {
            return null;
        }

        return $claim;
    }

    /**
     * Gateway of the attempt generation this session must reconcile.
     *
     * Returns the frozen gateway when the current generation requires
     * reconciliation (crashed or uncertain) and therefore must be re-driven
     * through its original processor. Returns null when the next attempt is
     * a new generation, where the current gateway selection applies.
     * Read-only hint: `claim()` remains the authoritative decision.
     */
    public function reconcilableGateway(CheckoutSession $session): ?string
    {
        if ($this->outcomeClassification($session) !== PaymentAttemptOutcome::Reconcile) {
            return null;
        }

        $gateway = $this->frozenIdentity($session)['gateway'] ?? null;

        return is_string($gateway) && $gateway !== '' ? $gateway : null;
    }

    /**
     * Authoritative gateway of the current attempt generation.
     *
     * An active claimed generation owns callback routing with its frozen
     * gateway: changing the mutable selection must not redirect the return
     * URL, provider notification, or recovery away from the attempt that is
     * actually in flight. Mutable selection applies only before any
     * generation was claimed, or after a replacement generation is claimed.
     */
    public function currentAttemptGateway(CheckoutSession $session): ?string
    {
        $frozen = $this->frozenIdentity($session)['gateway'] ?? null;

        if (is_string($frozen) && $frozen !== '') {
            return $frozen;
        }

        $selected = $session->selected_payment_gateway;

        return is_string($selected) && $selected !== '' ? $selected : null;
    }

    /**
     * Provider of the current attempt generation: the frozen claim provider
     * when one was captured, otherwise the provider recorded with the latest
     * gateway result so compensation and status paths keep pairing the
     * provider with the payment they act on.
     */
    public function frozenProvider(CheckoutSession $session): ?string
    {
        $provider = $this->frozenIdentity($session)['provider'] ?? null;

        if (is_string($provider) && $provider !== '') {
            return $provider;
        }

        $recorded = data_get($session->payment_data ?? [], 'provider');

        return is_string($recorded) && $recorded !== '' ? $recorded : null;
    }

    /**
     * Classify the session's current attempt generation for recovery.
     *
     * The caller must hold the session row lock (or pass a freshly locked
     * instance) so the classification and the claim it drives cannot diverge
     * from a concurrent callback, cancel, or later attempt.
     */
    public function outcomeClassification(CheckoutSession $session): PaymentAttemptOutcome
    {
        $attempts = (int) $session->payment_attempts;

        if ($attempts === 0) {
            return PaymentAttemptOutcome::NewGeneration;
        }

        $paymentData = $session->payment_data ?? [];
        $claim = data_get($paymentData, 'payment_attempt');
        $outcome = data_get($paymentData, 'payment_attempt_outcome');

        $claimNumber = is_array($claim) ? (int) ($claim['number'] ?? 0) : 0;
        $outcomeNumber = is_array($outcome) ? (int) ($outcome['number'] ?? 0) : 0;

        if ($claimNumber !== $attempts) {
            // No frozen identity exists for the current generation; treat it
            // as unclaimed so the caller claims a fresh one.
            return PaymentAttemptOutcome::NewGeneration;
        }

        if ($outcomeNumber !== $attempts) {
            // A claim without an authoritative outcome: crashed or abandoned.
            return PaymentAttemptOutcome::Reconcile;
        }

        if (($outcome['uncertain'] ?? false) === true) {
            return PaymentAttemptOutcome::Reconcile;
        }

        $status = PaymentStatus::tryFrom((string) ($outcome['status'] ?? '')) ?? PaymentStatus::Failed;

        if (in_array($status, [PaymentStatus::Failed, PaymentStatus::Cancelled], true)) {
            return PaymentAttemptOutcome::DefinitiveFailure;
        }

        return PaymentAttemptOutcome::Recorded;
    }

    /**
     * Bind an inbound callback to the current attempt generation.
     *
     * The caller must hold the session row lock so the generation, frozen
     * identity, and recorded payment identity cannot change between this
     * decision and the guarded writes it authorizes.
     *
     * Generation metadata is the trusted provider evidence for a callback
     * that arrives before the create response was recorded. Without it, a
     * null or retained payment id is never permission to act: the callback
     * cannot prove which attempt owns it and must fail closed.
     */
    public function classifyCallbackOwnership(
        CheckoutSession $session,
        ?int $attempt,
        ?string $gateway,
        ?string $provider,
        ?string $paymentId,
        bool $allowMissingPaymentIdentity = false,
    ): PaymentCallbackOwnership {
        $attempts = (int) $session->payment_attempts;

        if ($attempts === 0) {
            // No attempt generation was ever claimed; keep the legacy
            // identity comparison so a different known payment still cannot
            // replace the session's payment.
            $currentPaymentId = $session->payment_id;

            if (is_string($currentPaymentId) && $currentPaymentId !== ''
                && is_string($paymentId) && $paymentId !== ''
                && $currentPaymentId !== $paymentId) {
                return PaymentCallbackOwnership::unverifiable('Payment identity mismatch', null, $paymentId);
            }

            return PaymentCallbackOwnership::owned(null, $paymentId);
        }

        $claim = $this->frozenIdentity($session);

        if (! is_array($claim)) {
            return PaymentCallbackOwnership::unverifiable(
                'Payment attempt identity is not established',
                $attempt,
                $paymentId,
            );
        }

        if ($attempt !== null && $attempt !== $attempts) {
            // The receipt belongs to an older (or impossible future)
            // generation and has no authority over the active attempt.
            return PaymentCallbackOwnership::superseded(
                'Payment belongs to a superseded payment attempt',
                $attempt,
                $paymentId,
            );
        }

        $claimGateway = is_string($claim['gateway'] ?? null) ? $claim['gateway'] : null;
        $currentPaymentId = $this->currentAttemptPaymentId($session);

        if ($currentPaymentId !== null) {
            if (is_string($paymentId) && $paymentId !== '' && $paymentId === $currentPaymentId) {
                // Identity proves the current receipt; provider and gateway
                // evidence is still checked below before it may act.
            } elseif (is_string($paymentId) && $paymentId !== '') {
                // The current generation already has a recorded identity; a
                // different provider payment id is an obsolete receipt. Check
                // this before provider/gateway evidence: an old receipt from
                // another provider is still stale, not an unverifiable
                // callback for the active attempt.
                return PaymentCallbackOwnership::superseded(
                    'Payment identity mismatch',
                    $attempt,
                    $paymentId,
                );
            } elseif (! $allowMissingPaymentIdentity) {
                return PaymentCallbackOwnership::unverifiable('Payment identity is missing', $attempt, null);
            }
        }

        if (is_string($gateway) && $gateway !== '' && $claimGateway !== null && $gateway !== $claimGateway) {
            return PaymentCallbackOwnership::unverifiable('Payment gateway mismatch', $attempt, $paymentId);
        }

        $currentProvider = $this->currentAttemptProvider($session, $claim);

        if (is_string($provider) && $provider !== '' && $currentProvider !== null && $provider !== $currentProvider) {
            return PaymentCallbackOwnership::unverifiable('Payment provider mismatch', $attempt, $paymentId);
        }

        if ($currentPaymentId !== null) {
            // Failure/cancel browser callbacks carry no payload; the rotated
            // per-attempt callback token has already bound them upstream.
            return PaymentCallbackOwnership::owned($attempts, $currentPaymentId);
        }

        if ($attempt !== $attempts) {
            return PaymentCallbackOwnership::unverifiable(
                'Payment attempt generation is missing',
                $attempt,
                $paymentId,
            );
        }

        // Before the create response, only provider generation metadata can
        // prove ownership. The caller resolves the processor from the frozen
        // gateway, so the gateway is bound as well.
        return PaymentCallbackOwnership::owned($attempts, $paymentId);
    }

    /**
     * Persist a verified callback success as the current generation's
     * outcome without changing lifecycle state. The callback path settles
     * status separately through verification and finalization.
     */
    public function recordVerifiedOutcome(CheckoutSession $session, PaymentResult $result): void
    {
        $this->transactionally(function () use ($session, $result): void {
            $this->lockAndRefresh($session);

            $attempts = (int) $session->payment_attempts;
            $claim = $this->frozenIdentity($session);

            if ($attempts === 0 || ! is_array($claim)) {
                return;
            }

            $paymentData = $session->payment_data ?? [];
            $this->bumpAttemptRevision($paymentData);
            $paymentData['payment_attempt_outcome'] = $this->outcomeRecord(
                $attempts,
                $result,
                is_string($claim['provider'] ?? null) ? $claim['provider'] : null,
            );

            $session->persistState(['payment_data' => $paymentData]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function outcomeRecord(int $number, PaymentResult $result, ?string $fallbackProvider = null): array
    {
        return [
            'number' => $number,
            'status' => $result->status->value,
            'uncertain' => $result->uncertain,
            'definitive' => $result->status->isFinal(),
            'payment_id' => $result->paymentId,
            'provider' => $result->provider ?? $fallbackProvider,
            'recorded_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * Keep a paid receipt from a superseded generation as attempt-specific
     * reconciliation evidence. This never touches the active generation's
     * payment identity, outcome, verification state, or error state.
     *
     * @param  array<string, mixed>  $receipt
     */
    public function recordObsoleteReceipt(CheckoutSession $session, array $receipt): void
    {
        $this->transactionally(function () use ($session, $receipt): void {
            $this->lockAndRefresh($session);

            $paymentData = $session->payment_data ?? [];
            $receipts = $paymentData['obsolete_attempt_receipts'] ?? [];
            $receipts = is_array($receipts) ? $receipts : [];

            $receipts[] = array_merge($receipt, [
                'recorded_at' => CarbonImmutable::now()->toIso8601String(),
            ]);

            $paymentData['obsolete_attempt_receipts'] = array_slice($receipts, -10);

            $session->persistState(['payment_data' => $paymentData]);
        });
    }

    /**
     * Payment identity recorded for the current generation, or null while
     * its create response has not been recorded. A previous generation's
     * retained payment id is deliberately ignored.
     */
    private function currentAttemptPaymentId(CheckoutSession $session): ?string
    {
        $outcome = data_get($session->payment_data ?? [], 'payment_attempt_outcome');

        if (! is_array($outcome) || (int) ($outcome['number'] ?? 0) !== (int) $session->payment_attempts) {
            return null;
        }

        $paymentId = $outcome['payment_id'] ?? null;

        return is_string($paymentId) && $paymentId !== '' ? $paymentId : null;
    }

    /**
     * @param  array<string, mixed>  $claim
     */
    private function currentAttemptProvider(CheckoutSession $session, array $claim): ?string
    {
        $provider = $claim['provider'] ?? null;

        if (is_string($provider) && $provider !== '') {
            return $provider;
        }

        $outcome = data_get($session->payment_data ?? [], 'payment_attempt_outcome');

        if (! is_array($outcome) || (int) ($outcome['number'] ?? 0) !== (int) $session->payment_attempts) {
            return null;
        }

        $provider = $outcome['provider'] ?? null;

        return is_string($provider) && $provider !== '' ? $provider : null;
    }

    /**
     * Record a verified, definitive failure for the current attempt
     * generation under the row lock. Callback paths use this so an explicit
     * retry can release a replacement generation instead of observing a
     * stale non-terminal outcome.
     */
    public function recordDefinitiveFailure(CheckoutSession $session, ?string $paymentId = null): ?PaymentFailureOrigin
    {
        return DB::transaction(function () use ($session, $paymentId): ?PaymentFailureOrigin {
            $this->lockAndRefresh($session);

            $attempts = (int) $session->payment_attempts;

            if ($attempts === 0) {
                return null;
            }

            $paymentData = $session->payment_data ?? [];
            $claim = data_get($paymentData, 'payment_attempt');
            $claimNumber = is_array($claim) ? (int) ($claim['number'] ?? 0) : 0;

            if ($claimNumber !== $attempts) {
                return null;
            }

            $outcome = data_get($paymentData, 'payment_attempt_outcome');
            $outcomeNumber = is_array($outcome) ? (int) ($outcome['number'] ?? 0) : 0;

            // Never rewrite a newer generation's outcome from an older
            // receipt; callers fence the generation, this is defense in depth.
            if ($outcomeNumber > $attempts) {
                return null;
            }

            $evidencePaymentId = $paymentId
                ?? (is_array($outcome) && is_string($outcome['payment_id'] ?? null) ? $outcome['payment_id'] : null);

            $revision = $this->bumpAttemptRevision($paymentData);

            $paymentData['payment_attempt_outcome'] = [
                'number' => $attempts,
                'status' => PaymentStatus::Failed->value,
                'uncertain' => false,
                'definitive' => true,
                'payment_id' => $evidencePaymentId,
                'provider' => is_array($claim)
                    ? ($claim['provider'] ?? (is_string($paymentData['provider'] ?? null) ? $paymentData['provider'] : null))
                    : null,
                'recorded_at' => CarbonImmutable::now()->toIso8601String(),
            ];

            $session->persistState(['payment_data' => $paymentData]);

            return new PaymentFailureOrigin($attempts, $revision, claimed: true);
        });
    }

    /**
     * Claim a durable failure/compensation operation for the execution that
     * currently owns the attempt.
     *
     * The generation, payment identity, and resources this operation may
     * compensate are captured under the session row lock. Gateway I/O runs
     * after the lock is released, but every later write rechecks this token
     * against the locked fresh row. A replacement claim clears the token,
     * which turns the stale operation into an observer instead of allowing
     * it to overwrite the replacement.
     */
    public function beginFailureOperation(CheckoutSession $session, PaymentFailureOrigin $origin): ?PaymentFailureOperation
    {
        return $this->transactionally(function () use ($session, $origin): ?PaymentFailureOperation {
            // Read the locked row into its own instance instead of refreshing
            // the caller. A non-payment failure may arrive after a rolled-back
            // transaction dropped its step writes, and the run's in-memory
            // step map is the only record of what it actually completed.
            $locked = CheckoutSession::whereKey($session->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                return null;
            }

            $attempts = (int) $locked->payment_attempts;

            // The origin is the execution's observation, not a hint. A
            // replacement claim, an adoption, or an authoritative result on
            // the same generation advances the revision and revokes any
            // failure authority the older execution thought it had.
            if ($attempts !== $origin->attempts
                || $this->attemptRevision($locked) !== $origin->revision
                || $origin->claimed !== ($origin->attempts > 0)
            ) {
                return null;
            }

            if ($locked->status->is(Completed::class) || $locked->status->is(Cancelled::class)) {
                return null;
            }

            // A verified failure callback can legitimately fail an attempt
            // whose pending redirect put the session in AwaitingPayment.
            // Only a durable definitive failure for that same generation
            // authorizes the transition; a success redirect from this or a
            // newer generation must be observed instead.
            if ($locked->status->is(AwaitingPayment::class)
                && $attempts > 0
                && $this->outcomeClassification($locked) !== PaymentAttemptOutcome::DefinitiveFailure
            ) {
                return null;
            }

            $frozen = $this->frozenIdentity($locked);

            if ($attempts > 0 && ! is_array($frozen)) {
                return null;
            }

            if ($locked->status->isTerminal() && ! $locked->status->is(PaymentFailed::class)) {
                return null;
            }

            $paymentData = $locked->payment_data ?? [];
            $existing = data_get($paymentData, 'payment_failure_operation');

            // One owner per generation: a second failure path for the same
            // attempt observes the operation instead of duplicating gateway
            // compensation or failure notifications.
            if (is_array($existing) && (int) ($existing['attempts'] ?? -1) === $attempts) {
                return null;
            }

            $paymentId = $locked->payment_id;

            if (! is_string($paymentId) || $paymentId === '') {
                $recorded = data_get($paymentData, 'payment_id');
                $paymentId = is_string($recorded) && $recorded !== '' ? $recorded : null;
            }

            $paymentStatus = data_get($paymentData, 'status');
            $paymentStatus = is_string($paymentStatus) && PaymentStatus::tryFrom($paymentStatus) !== null
                ? $paymentStatus
                : null;

            $operation = new PaymentFailureOperation(
                token: (string) Str::uuid(),
                attempts: $attempts,
                revision: $origin->revision,
                attempt: $origin->claimed ? $attempts : null,
                paymentId: $paymentId,
                gateway: $this->currentAttemptGateway($locked),
                provider: $this->frozenProvider($locked),
                paymentStatus: $paymentStatus,
                amount: (int) ($frozen['amount'] ?? $locked->grand_total),
                currency: is_string($frozen['currency'] ?? null)
                    ? $frozen['currency']
                    : (is_string($locked->currency) ? $locked->currency : null),
                startedAt: CarbonImmutable::now()->toIso8601String(),
            );

            $paymentData['payment_failure_operation'] = $operation->toRecord();
            $locked->persistState(['payment_data' => $paymentData]);

            // A claimed-attempt failure has no rolled-back step writes to
            // preserve, so hand the caller the locked row for reporting. A
            // non-attempt failure keeps its in-memory step map for
            // compensation and only receives the durable operation token.
            if ($origin->claimed) {
                $session->setRawAttributes($locked->getAttributes(), true);
                $session->syncOriginal();
            } else {
                $session->setAttribute('payment_data', $paymentData);
                $session->syncOriginalAttribute('payment_data');
            }

            return $operation;
        });
    }

    /**
     * Capture the execution state a failure is about to be reported against.
     *
     * A claimed execution passes its frozen attempt number; a non-payment
     * step passes null and observes the current generation. A claimed origin
     * whose generation no longer matches is returned with its original
     * generation and a zero revision so it fails closed rather than being
     * silently rebound to the locked current row.
     */
    public function captureFailureOrigin(CheckoutSession $session, ?int $attemptNumber = null): PaymentFailureOrigin
    {
        return $this->transactionally(function () use ($session, $attemptNumber): PaymentFailureOrigin {
            $locked = CheckoutSession::whereKey($session->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                return PaymentFailureOrigin::preClaim();
            }

            $attempts = (int) $locked->payment_attempts;

            if ($attemptNumber !== null && $attemptNumber !== $attempts) {
                return new PaymentFailureOrigin($attemptNumber, 0, claimed: true);
            }

            return new PaymentFailureOrigin(
                $attempts,
                $this->attemptRevision($locked),
                claimed: $attempts > 0,
            );
        });
    }

    /**
     * Whether an already-issued failure operation still owns the locked
     * current row. Gateway compensation calls run after this check and their
     * results are checked again, so a recovery that lands in between becomes
     * observation evidence instead of a rollback of the recovered payment.
     */
    public function failureOperationStillAuthorized(
        CheckoutSession $session,
        PaymentFailureOperation $operation,
    ): bool {
        return $this->transactionally(function () use ($session, $operation): bool {
            $locked = CheckoutSession::whereKey($session->getKey())->lockForUpdate()->first();

            return $locked !== null && $this->failureOperationOwned($locked, $operation);
        });
    }

    /**
     * Record one compensation result only while the operation still owns the
     * locked current row. A superseded result is retained as attempt-scoped
     * evidence and never lands in the active compensation log or step map.
     */
    public function recordFailureCompensationIfOwned(
        CheckoutSession $session,
        PaymentFailureOperation $operation,
        string $stepIdentifier,
        StepResult $result,
    ): bool {
        return $this->transactionally(function () use ($session, $operation, $stepIdentifier, $result): bool {
            $locked = CheckoutSession::whereKey($session->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                return false;
            }

            if (! $this->failureOperationOwned($locked, $operation)) {
                $this->recordSupersededCompensationEvidence($locked, $operation, $stepIdentifier, $result);

                return false;
            }

            $locked->recordCompensation($stepIdentifier, $result);

            if ($result->isCompensated()) {
                $locked->setStepState($stepIdentifier, StepStatus::RolledBack);
            }

            return true;
        });
    }

    /**
     * Complete the failure lifecycle only while the operation still owns the
     * locked current row.
     */
    public function finishFailureOperationIfOwned(
        CheckoutSession $session,
        PaymentFailureOperation $operation,
        string $message,
    ): bool {
        return $this->transactionally(function () use ($session, $operation, $message): bool {
            $locked = CheckoutSession::whereKey($session->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                return false;
            }

            if (! $this->failureOperationOwned($locked, $operation)) {
                return false;
            }

            if (! $locked->status->isTerminal() && ! $locked->status->is(PaymentFailed::class)) {
                $locked->transitionStatus(PaymentFailed::class);
            }

            $paymentData = $locked->payment_data ?? [];
            $record = data_get($paymentData, 'payment_failure_operation');
            $record = is_array($record) ? $record : $operation->toRecord();
            $record['status'] = 'completed';
            $record['finished_at'] = CarbonImmutable::now()->toIso8601String();
            $paymentData['payment_failure_operation'] = $record;

            $locked->persistState([
                'payment_data' => $paymentData,
                'error_message' => $message,
            ]);

            // Hand the caller the committed failure snapshot for its event
            // without refreshing away the attempt identity it observed.
            $session->setRawAttributes($locked->getAttributes(), true);
            $session->syncOriginal();

            return true;
        });
    }

    private function failureOperationOwned(CheckoutSession $session, PaymentFailureOperation $operation): bool
    {
        $current = data_get($session->payment_data ?? [], 'payment_failure_operation');

        if (! is_array($current)) {
            return false;
        }

        if (! hash_equals((string) ($current['token'] ?? ''), $operation->token)) {
            return false;
        }

        if ((int) ($current['attempts'] ?? -1) !== $operation->attempts) {
            return false;
        }

        if ((int) $session->payment_attempts !== $operation->attempts) {
            return false;
        }

        // A claim, adoption, recorded result, verified outcome, or definitive
        // failure on this generation supersedes the observation that
        // authorized the operation. The token alone is not enough.
        if ($this->attemptRevision($session) !== $operation->revision) {
            return false;
        }

        if ($session->status->is(Completed::class) || $session->status->is(Cancelled::class)) {
            return false;
        }

        // The callback-failure path begins the operation while the session
        // is still AwaitingPayment; it is authorized only by the durable
        // definitive-failure outcome for this same generation.
        if ($session->status->is(AwaitingPayment::class)
            && $operation->attempts > 0
            && $this->outcomeClassification($session) !== PaymentAttemptOutcome::DefinitiveFailure
        ) {
            return false;
        }

        if ($operation->attempt !== null && (int) $session->payment_attempts !== $operation->attempt) {
            return false;
        }

        return $operation->attempts === 0 || is_array($this->frozenIdentity($session));
    }

    private function recordSupersededCompensationEvidence(
        CheckoutSession $session,
        PaymentFailureOperation $operation,
        string $stepIdentifier,
        StepResult $result,
    ): void {
        $paymentData = $session->payment_data ?? [];
        $evidence = $paymentData['superseded_failure_compensations'] ?? [];
        $evidence = is_array($evidence) ? $evidence : [];

        $evidence[] = [
            'token' => $operation->token,
            'attempts' => $operation->attempts,
            'attempt' => $operation->attempt,
            'step_identifier' => $stepIdentifier,
            'status' => $result->status->value,
            'message' => $result->message,
            'data' => $result->data,
            'recorded_at' => CarbonImmutable::now()->toIso8601String(),
        ];

        $paymentData['superseded_failure_compensations'] = array_slice($evidence, -20);

        $session->persistState(['payment_data' => $paymentData]);
    }

    /**
     * Record a gateway result under the row lock, but only when the caller's
     * execution still owns the current attempt revision. Returns null when a
     * callback, recovery, result, or later attempt already owns the outcome.
     */
    public function record(
        CheckoutSession $session,
        PaymentAttemptClaim $claim,
        PaymentResult $result,
    ): ?PaymentFailureOrigin {
        return DB::transaction(function () use ($session, $claim, $result): ?PaymentFailureOrigin {
            $this->lockAndRefresh($session);

            $ownsAttempt = (int) $session->payment_attempts === $claim->number
                && $this->attemptRevision($session) === $claim->revision
                && ($session->status->is(Processing::class) || $session->status->is(PaymentProcessing::class));

            if (! $ownsAttempt) {
                return null;
            }

            $now = CarbonImmutable::now()->toIso8601String();

            $paymentData = array_merge($session->payment_data ?? [], [
                'gateway' => $claim->gateway,
                'provider' => $result->provider ?? $claim->provider ?? $claim->gateway,
                'payment_id' => $result->paymentId,
                'transaction_id' => $result->transactionId,
                'status' => $result->status->value,
                'amount' => $result->amount ?? $claim->amount,
                'currency' => $result->currency,
                'reference' => $claim->reference,
                'gateway_response' => $result->gatewayResponse,
                'processed_at' => $now,
                'payment_attempt_outcome' => $this->outcomeRecord(
                    $claim->number,
                    $result,
                    $claim->provider,
                ),
            ]);

            $revision = $this->bumpAttemptRevision($paymentData);
            $origin = new PaymentFailureOrigin($claim->number, $revision, claimed: true);

            $session->persistState([
                'payment_id' => $result->paymentId,
                'payment_data' => $paymentData,
            ]);

            if ($result->requiresRedirect()) {
                $session->persistState(['payment_redirect_url' => $result->redirectUrl]);
                $session->transitionStatus(AwaitingPayment::class);

                return $origin;
            }

            if ($result->isSuccessful()) {
                if (! $session->status->is(Processing::class)) {
                    $session->transitionStatus(Processing::class);
                }

                return $origin;
            }

            if ($result->status->isFinal()) {
                $session->transitionStatus(PaymentFailed::class);
            }

            // Unknown or known non-terminal outcomes (an uncertain creation
            // response, a processing charge, or a pending result without a
            // redirect) stay in PaymentProcessing: the attempt remains
            // recoverable and no definitive-failure compensation may run
            // before payment truth is known.
            return $origin;
        });
    }

    /**
     * Start a step only when the persisted session still allows this
     * execution to write step state.
     *
     * The ownership read and the beginStep() write share the session row
     * lock, so a callback winner cannot complete between the check and the
     * write. Returns false when a callback already completed or redirected
     * the session and the caller must observe instead.
     */
    public function beginStepIfOwned(
        CheckoutSession $session,
        string $stepIdentifier,
        PaymentFailureOrigin $origin,
    ): bool {
        return $this->transactionally(function () use ($session, $stepIdentifier, $origin): bool {
            $locked = CheckoutSession::whereKey($session->getKey())->lockForUpdate()->first();

            if ($locked === null
                || ! $this->executionOriginMatches($locked, $origin)
                || $locked->status->isTerminal()
                || $locked->status->is(AwaitingPayment::class)
            ) {
                return false;
            }

            $locked->beginStep($stepIdentifier);
            $this->syncSessionWithLocked($session, $locked);

            return true;
        });
    }

    /**
     * Persist a returned step state only while the execution still owns the
     * exact attempt generation and revision it observed before doing work.
     *
     * The locked row supplies the step map that is updated and then copied
     * back to the caller. Keeping that committed map in memory preserves the
     * pre-payment completion bookkeeping used if its surrounding transaction
     * later rolls back for compensation.
     */
    public function recordStepStateIfOwned(
        CheckoutSession $session,
        string $stepIdentifier,
        StepStatus $status,
        PaymentFailureOrigin $origin,
    ): bool {
        return $this->transactionally(function () use ($session, $stepIdentifier, $status, $origin): bool {
            $locked = CheckoutSession::whereKey($session->getKey())->lockForUpdate()->first();

            if ($locked === null
                || ! $this->mayWriteStepState($locked, $stepIdentifier, $status, $origin)
            ) {
                return false;
            }

            $locked->setStepState($stepIdentifier, $status);
            $this->syncSessionWithLocked($session, $locked);

            return true;
        });
    }

    /**
     * Record a thrown step failure only while its captured execution origin
     * still owns the session row.
     */
    public function recordStepFailureIfOwned(
        CheckoutSession $session,
        string $stepIdentifier,
        PaymentFailureOrigin $origin,
    ): bool {
        return $this->recordStepStateIfOwned($session, $stepIdentifier, StepStatus::Failed, $origin);
    }

    private function mayWriteStepState(
        CheckoutSession $session,
        string $stepIdentifier,
        StepStatus $stepStatus,
        PaymentFailureOrigin $origin,
    ): bool {
        if (! $this->executionOriginMatches($session, $origin)) {
            return false;
        }

        if ($session->status->is(AwaitingPayment::class)) {
            return $stepIdentifier === 'process_payment' && $stepStatus === StepStatus::Completed;
        }

        if ($session->status->is(PaymentFailed::class)) {
            return $stepIdentifier === 'process_payment'
                && $stepStatus === StepStatus::Failed
                && $origin->claimed;
        }

        if ($session->status->isTerminal()) {
            return false;
        }

        return true;
    }

    private function executionOriginMatches(CheckoutSession $session, PaymentFailureOrigin $origin): bool
    {
        $attempts = (int) $session->payment_attempts;

        return $attempts === $origin->attempts
            && $this->attemptRevision($session) === $origin->revision
            && $origin->claimed === ($attempts > 0);
    }

    /**
     * The authoritative ownership question for a failing execution.
     *
     * A completed or redirected session is always owned by that outcome.
     * Without a claimed generation (a non-payment step) the previous status
     * guard applies. With one, the decision must come from the attempt
     * generation and its durable outcome classification — never status
     * alone: a later generation, a definitive failure recorded by another
     * flow, or a terminal state all mean the execution lost ownership.
     */
    private function ownsFailure(CheckoutSession $session, ?int $attemptNumber): bool
    {
        if ($session->status->is(Completed::class) || $session->status->is(AwaitingPayment::class)) {
            return false;
        }

        if ($attemptNumber === null) {
            return true;
        }

        if ((int) $session->payment_attempts !== $attemptNumber) {
            return false;
        }

        if ($session->status->isTerminal()) {
            return false;
        }

        // Only an unrecorded (reconcilable) generation can still be failed
        // by the execution that claims it. A recorded outcome — including a
        // definitive failure recorded by a callback — belongs to that flow.
        return $this->outcomeClassification($session) === PaymentAttemptOutcome::Reconcile;
    }

    private function claimNewAttempt(CheckoutSession $session, string $gateway, ?string $provider = null): PaymentAttemptClaim
    {
        $retryLimit = max(0, (int) config('checkout.payment.retry_limit', 3));

        $updated = $session->newQuery()
            ->whereKey($session->getKey())
            ->where('payment_attempts', '<', $retryLimit)
            ->update([
                'payment_attempts' => DB::raw('payment_attempts + 1'),
                'selected_payment_gateway' => $gateway,
            ]);

        if ($updated !== 1) {
            $attempts = (int) ($session->newQuery()
                ->whereKey($session->getKey())
                ->value('payment_attempts') ?? $session->payment_attempts);

            throw PaymentException::retryLimitExceeded($attempts, $retryLimit);
        }

        $number = (int) $session->payment_attempts + 1;

        $session->setAttribute('payment_attempts', $number);
        $session->setAttribute('selected_payment_gateway', $gateway);

        $paymentData = $session->payment_data ?? [];
        unset($paymentData['retry_requested_for_attempt'], $paymentData['payment_failure_operation']);
        $revision = $this->bumpAttemptRevision($paymentData);

        $paymentMethod = data_get($paymentData, 'payment_method');
        $paymentMethod = is_string($paymentMethod) && mb_trim($paymentMethod) !== ''
            ? mb_trim($paymentMethod)
            : null;

        $identity = [
            'number' => $number,
            'gateway' => $gateway,
            'provider' => $this->normalizeProvider($provider) ?? $this->explicitProvider($session),
            'amount' => (int) $session->grand_total,
            'currency' => (string) ($session->currency ?? ''),
            'reference' => CheckoutPaymentReference::forSession($session),
            'idempotency_key' => $this->idempotencyKey($session, $number),
            'payment_method' => $paymentMethod,
            'claimed_at' => CarbonImmutable::now()->toIso8601String(),
            'revision' => $revision,
        ];

        $paymentData['payment_attempt'] = $identity;
        // Rotate the callback token for every new attempt generation so a
        // prior attempt's token cannot authorize a newer attempt's transition.
        $paymentData['callback_token'] = Str::random(40);
        $paymentData['callback_token_created_at'] = CarbonImmutable::now()->toIso8601String();
        unset($paymentData['callback_token_consumed_at']);

        // The request snapshot is built under the row lock with the rotated
        // token visible to the builder, so the callback URLs frozen with the
        // attempt are exactly the ones the provider receives.
        $session->setAttribute('payment_data', $paymentData);
        $claim = PaymentAttemptClaim::claimed($identity);
        $identity['request'] = $this->requestBuilder()->build($session, $claim)->toSnapshot();
        $paymentData['payment_attempt'] = $identity;

        // The previous generation's payment identity is evidence for that
        // attempt, not authorization for this one. Clear it so the pre-response
        // window cannot accept an obsolete receipt by id alone.
        $session->persistState([
            'payment_data' => $paymentData,
            'payment_id' => null,
            'payment_redirect_url' => null,
        ]);

        $this->transitionToPaymentProcessing($session);

        return PaymentAttemptClaim::claimed($identity);
    }

    /**
     * @param  array<string, mixed>  $claim
     */
    private function adopt(CheckoutSession $session, array $claim): PaymentAttemptClaim
    {
        $number = is_numeric($claim['number'] ?? null) ? (int) $claim['number'] : 0;

        if ($number <= 0 || $number !== (int) $session->payment_attempts) {
            throw PaymentException::incompleteAttemptIdentity((string) $session->getKey(), $number);
        }

        $gateway = $claim['gateway'] ?? null;
        $reference = $claim['reference'] ?? null;
        $idempotencyKey = $claim['idempotency_key'] ?? null;
        $currency = $claim['currency'] ?? null;
        $request = $claim['request'] ?? null;

        if (! is_string($gateway) || mb_trim($gateway) === ''
            || ! is_numeric($claim['amount'] ?? null)
            || ! is_string($currency) || mb_trim($currency) === ''
            || ! is_string($reference) || mb_trim($reference) === ''
            || ! is_string($idempotencyKey) || mb_trim($idempotencyKey) === ''
            || ! array_key_exists('payment_method', $claim)
            || ! is_string($claim['claimed_at'] ?? null)
            || ! is_array($request) || $request === []
        ) {
            throw PaymentException::incompleteAttemptIdentity((string) $session->getKey(), $number);
        }

        $provider = $claim['provider'] ?? null;
        $paymentMethod = $claim['payment_method'];

        if (($provider !== null && ! is_string($provider))
            || ($paymentMethod !== null && ! is_string($paymentMethod))
        ) {
            throw PaymentException::incompleteAttemptIdentity((string) $session->getKey(), $number);
        }

        $paymentData = $session->payment_data ?? [];
        unset($paymentData['retry_requested_for_attempt'], $paymentData['payment_failure_operation']);
        $revision = $this->bumpAttemptRevision($paymentData);

        $identity = [
            'number' => $number,
            'gateway' => $gateway,
            'provider' => $provider,
            'amount' => (int) $claim['amount'],
            'currency' => $currency,
            'reference' => $reference,
            'idempotency_key' => $idempotencyKey,
            'payment_method' => $paymentMethod,
            'claimed_at' => $claim['claimed_at'],
            'revision' => $revision,
            'request' => $request,
        ];

        $paymentData['payment_attempt'] = $identity;

        if (! is_string($paymentData['callback_token'] ?? null) || $paymentData['callback_token'] === '') {
            throw PaymentException::incompleteAttemptIdentity((string) $session->getKey(), $number);
        }

        $session->persistState(['payment_data' => $paymentData]);

        $this->transitionToPaymentProcessing($session);

        return PaymentAttemptClaim::adopted($identity);
    }

    private function requestBuilder(): PaymentRequestBuilder
    {
        return $this->requestBuilder ?? app(PaymentRequestBuilder::class);
    }

    private function transitionToPaymentProcessing(CheckoutSession $session): void
    {
        if (! $session->status->is(Processing::class)) {
            $session->transitionStatus(Processing::class);
        }

        $session->transitionStatus(PaymentProcessing::class);
    }

    private function explicitProvider(CheckoutSession $session): ?string
    {
        $provider = data_get($session->payment_data ?? [], 'provider');

        return is_string($provider) && $provider !== '' ? $provider : null;
    }

    private function normalizeProvider(?string $provider): ?string
    {
        if (! is_string($provider)) {
            return null;
        }

        $provider = mb_trim($provider);

        return $provider !== '' ? $provider : null;
    }

    private function idempotencyKey(CheckoutSession $session, int $number): string
    {
        return (string) $session->getKey() . ':attempt:' . $number;
    }

    private function retryRequestedFor(CheckoutSession $session): int
    {
        $retryRequested = data_get($session->payment_data ?? [], 'retry_requested_for_attempt');

        return is_numeric($retryRequested) ? (int) $retryRequested : 0;
    }

    /**
     * Whether the locked persisted row was already completed or redirected
     * by a callback. Reads the row without refreshing the caller's instance,
     * so a pipeline failure keeps its in-memory step map for compensation.
     */
    private function attemptRevision(CheckoutSession $session): int
    {
        $revision = data_get($session->payment_data ?? [], 'payment_attempt_revision');

        return is_numeric($revision) ? (int) $revision : 0;
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    private function bumpAttemptRevision(array &$paymentData): int
    {
        $revision = $paymentData['payment_attempt_revision'] ?? 0;
        $revision = (is_numeric($revision) ? (int) $revision : 0) + 1;
        $paymentData['payment_attempt_revision'] = $revision;

        return $revision;
    }

    private function syncSessionWithLocked(CheckoutSession $session, CheckoutSession $locked): void
    {
        $session->setRawAttributes($locked->getAttributes(), true);
        $session->syncOriginal();
    }

    /**
     * Run the callback in a transaction, or directly when the caller already
     * owns one. This keeps callback handling free of redundant savepoints
     * while preserving the row-lock atomicity of every guarded write.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    private function transactionally(Closure $callback): mixed
    {
        return DB::transactionLevel() > 0 ? $callback() : DB::transaction($callback);
    }

    /**
     * Lock the session row and re-read it so concurrent claims, recordings,
     * callbacks, and cancels serialize instead of lost-updating the JSON
     * blobs.
     */
    private function lockAndRefresh(CheckoutSession $session): void
    {
        $locked = CheckoutSession::whereKey($session->getKey())->lockForUpdate()->first();

        if ($locked === null) {
            throw InvalidCheckoutStateException::sessionNotFound((string) $session->getKey());
        }

        $session->refresh();
    }
}
