<?php

declare(strict_types=1);

use AIArmada\Checkout\Actions\CheckoutFinalizer;
use AIArmada\Checkout\Actions\HandleCheckoutPaymentCallback;
use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Contracts\PaymentCompensationInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentAttemptClaim;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Enums\StepStatus;
use AIArmada\Checkout\Events\CheckoutFailed;
use AIArmada\Checkout\Events\CheckoutStepFailed;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\PaymentAttemptManager;
use AIArmada\Checkout\Services\StepExecutor;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Completed;
use AIArmada\Checkout\States\PaymentProcessing;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Steps\ProcessPaymentStep;
use AIArmada\Checkout\Support\CheckoutCallbackStatePolicy;
use AIArmada\Commerce\Tests\Checkout\CheckoutEngineTestCase;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\EngineRace;

uses(CheckoutEngineTestCase::class);

/**
 * Engine race gateway for the losing process: it records the accepted charge
 * in the ledger, waits for the callback winner's committed marker, and then
 * throws a transport error exactly like a gateway response lost after the
 * provider accepted the purchase.
 */
final class Chk04EngineThrowingProcessor implements PaymentCompensationInterface, PaymentProcessorInterface
{
    public function __construct(
        private readonly string $workdir,
        public object $tracker,
        private readonly string $waitFor = 'winner-done',
    ) {}

    public function getIdentifier(): string
    {
        return 'chip';
    }

    public function getName(): string
    {
        return 'Engine stale-thrown gateway';
    }

    public function isAvailable(CheckoutSession $session): bool
    {
        return true;
    }

    public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
    {
        DB::table('checkout_engine_creates')->insert([
            'id' => (string) Str::uuid(),
            'session_id' => (string) $session->getKey(),
            'attempt' => (int) ($request->metadata['checkout_attempt'] ?? 0),
            'idempotency_key' => (string) ($request->metadata['checkout_idempotency_key'] ?? ''),
            'created_at' => CarbonImmutable::now()->toIso8601String(),
        ]);

        $deadline = microtime(true) + 60;

        while (! is_file($this->workdir . '/' . $this->waitFor)) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('losing process timed out waiting for [' . $this->waitFor . ']');
            }

            usleep(5000);
        }

        throw new RuntimeException('Engine gateway timeout after acceptance');
    }

    public function handleCallback(array $payload): PaymentResult
    {
        return PaymentResult::failed('unused');
    }

    public function getRedirectUrl(CheckoutSession $session): ?string
    {
        return null;
    }

    public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
    {
        return PaymentResult::failed('unused');
    }

    public function checkStatus(string $paymentId): PaymentResult
    {
        return PaymentResult::failed('unused');
    }

    public function voidPayment(string $paymentId, ?string $reason = null): PaymentResult
    {
        $this->tracker->voided[] = $paymentId;

        return new PaymentResult(status: PaymentStatus::Cancelled, paymentId: $paymentId);
    }
}

/**
 * Failure-compensation gateway: attempt 1 records a definitive failure with a
 * payment identity, then blocks inside voidPayment. The retry connection
 * claims and records attempt 2 while the stale failure operation holds the
 * post-check interval.
 */
final class Chk04EngineCompensatingProcessor implements PaymentCompensationInterface, PaymentProcessorInterface
{
    public function __construct(
        private readonly string $workdir,
        public object $tracker,
        private readonly string $waitFor = 'attempt-2-claimed',
    ) {}

    public function getIdentifier(): string
    {
        return 'chip';
    }

    public function getName(): string
    {
        return 'Engine stale-compensation gateway';
    }

    public function isAvailable(CheckoutSession $session): bool
    {
        return true;
    }

    public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
    {
        return PaymentResult::failed('Card declined', paymentId: 'pay_engine_stale_failure');
    }

    public function handleCallback(array $payload): PaymentResult
    {
        return PaymentResult::failed('unused');
    }

    public function getRedirectUrl(CheckoutSession $session): ?string
    {
        return null;
    }

    public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
    {
        return PaymentResult::failed('unused');
    }

    public function checkStatus(string $paymentId): PaymentResult
    {
        return PaymentResult::failed('unused');
    }

    public function voidPayment(string $paymentId, ?string $reason = null): PaymentResult
    {
        $this->tracker->voided[] = $paymentId;

        file_put_contents($this->workdir . '/compensation-started', (string) getmypid());

        $deadline = microtime(true) + 60;

        while (! is_file($this->workdir . '/' . $this->waitFor)) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('stale compensation timed out waiting for ' . $this->waitFor);
            }

            usleep(5000);
        }

        return new PaymentResult(status: PaymentStatus::Cancelled, paymentId: $paymentId);
    }
}

/** Winning process gateway: only its callback path is exercised. */
final class Chk04EngineCallbackProcessor implements PaymentProcessorInterface
{
    public function __construct(
        private readonly string $paymentId,
    ) {}

    public function getIdentifier(): string
    {
        return 'chip';
    }

    public function getName(): string
    {
        return 'Engine callback gateway';
    }

    public function isAvailable(CheckoutSession $session): bool
    {
        return true;
    }

    public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
    {
        return PaymentResult::failed('unused');
    }

    public function handleCallback(array $payload): PaymentResult
    {
        return new PaymentResult(
            status: PaymentStatus::Completed,
            paymentId: $this->paymentId,
            amount: 10000,
            currency: 'MYR',
        );
    }

    public function getRedirectUrl(CheckoutSession $session): ?string
    {
        return null;
    }

    public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
    {
        return PaymentResult::failed('unused');
    }

    public function checkStatus(string $paymentId): PaymentResult
    {
        return PaymentResult::failed('unused');
    }
}

/**
 * Gateway for the newer generation: it records the claim, signals the stale
 * execution, waits for that execution to finish, and only then returns a
 * non-final processing result. The stale exception therefore lands while
 * attempt 2 is claimed but before any response is recorded.
 */
final class Chk04EngineActiveClaimProcessor implements PaymentProcessorInterface
{
    public function __construct(
        private readonly string $workdir,
    ) {}

    public function getIdentifier(): string
    {
        return 'chip';
    }

    public function getName(): string
    {
        return 'Engine active-generation gateway';
    }

    public function isAvailable(CheckoutSession $session): bool
    {
        return true;
    }

    public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
    {
        file_put_contents(
            $this->workdir . '/attempt-2-claimed',
            (string) ($request->metadata['checkout_attempt'] ?? 0),
        );

        $deadline = microtime(true) + 60;

        while (! is_file($this->workdir . '/loser-done')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('active attempt timed out waiting for the stale execution');
            }

            usleep(5000);
        }

        return new PaymentResult(
            status: PaymentStatus::Processing,
            paymentId: 'pay_attempt_2',
            amount: 10000,
            currency: 'MYR',
        );
    }

    public function handleCallback(array $payload): PaymentResult
    {
        return PaymentResult::failed('unused');
    }

    public function getRedirectUrl(CheckoutSession $session): ?string
    {
        return null;
    }

    public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
    {
        return PaymentResult::failed('unused');
    }

    public function checkStatus(string $paymentId): PaymentResult
    {
        return PaymentResult::failed('unused');
    }
}

/** Returns a deliberately stale gateway result after the recovery commits. */
final class Chk13EngineReturningProcessor implements PaymentProcessorInterface
{
    public function __construct(
        private readonly string $workdir,
        private readonly PaymentResult $result,
        private readonly string $waitFor,
    ) {}

    public function getIdentifier(): string
    {
        return 'chip';
    }

    public function getName(): string
    {
        return 'Engine stale-result gateway';
    }

    public function isAvailable(CheckoutSession $session): bool
    {
        return true;
    }

    public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
    {
        DB::table('checkout_engine_creates')->insert([
            'id' => (string) Str::uuid(),
            'session_id' => (string) $session->getKey(),
            'attempt' => (int) ($request->metadata['checkout_attempt'] ?? 0),
            'idempotency_key' => (string) ($request->metadata['checkout_idempotency_key'] ?? ''),
            'created_at' => CarbonImmutable::now()->toIso8601String(),
        ]);

        $deadline = microtime(true) + 60;

        while (! is_file($this->workdir . '/' . $this->waitFor)) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('stale result timed out waiting for [' . $this->waitFor . ']');
            }

            usleep(5000);
        }

        return $this->result;
    }

    public function handleCallback(array $payload): PaymentResult
    {
        return PaymentResult::failed('unused');
    }

    public function getRedirectUrl(CheckoutSession $session): ?string
    {
        return null;
    }

    public function refund(string $paymentId, int $amount, ?string $reason = null): PaymentResult
    {
        return PaymentResult::failed('unused');
    }

    public function checkStatus(string $paymentId): PaymentResult
    {
        return PaymentResult::failed('unused');
    }
}

final class Chk04EngineSuccessStep implements CheckoutStepInterface
{
    /**
     * @param  list<string>  $dependencies
     */
    public function __construct(
        private readonly string $identifier,
        private readonly array $dependencies = [],
    ) {}

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return $this->identifier;
    }

    public function validate(CheckoutSession $session): array
    {
        return [];
    }

    public function handle(CheckoutSession $session): StepResult
    {
        return StepResult::success($this->identifier);
    }

    public function canSkip(CheckoutSession $session): bool
    {
        return false;
    }

    public function compensate(CheckoutSession $session): StepResult
    {
        return StepResult::compensated($this->identifier);
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }
}

/**
 * Deterministic pre-compensation gate. The step is marked completed in the
 * fixture so it is the first step visited by reverse compensation; its
 * compensate() signals the recovery process and waits until that recovery has
 * committed. The payment step is still ahead in the reverse order, so a
 * recovery that wins must stop it before its void side effect.
 */
final class Chk04EngineGateStep implements CheckoutStepInterface
{
    public function __construct(
        private readonly string $workdir,
    ) {}

    public function getIdentifier(): string
    {
        return 'refund_gate';
    }

    public function getName(): string
    {
        return 'refund_gate';
    }

    public function validate(CheckoutSession $session): array
    {
        return [];
    }

    public function handle(CheckoutSession $session): StepResult
    {
        return StepResult::success('refund_gate');
    }

    public function canSkip(CheckoutSession $session): bool
    {
        return false;
    }

    public function compensate(CheckoutSession $session): StepResult
    {
        file_put_contents($this->workdir . '/compensation-gate', (string) getmypid());

        $deadline = microtime(true) + 60;

        while (! is_file($this->workdir . '/recovery-done')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('failure compensation gate timed out waiting for recovery');
            }

            usleep(5000);
        }

        return StepResult::compensated('refund_gate');
    }

    public function getDependencies(): array
    {
        return [];
    }
}

final class Chk04EngineResolver implements PaymentGatewayResolverInterface
{
    public function __construct(
        private readonly PaymentProcessorInterface $processor,
    ) {}

    public function resolve(?string $gateway = null): PaymentProcessorInterface
    {
        return $this->processor;
    }

    /** @return array<string, PaymentProcessorInterface> */
    public function getAvailable(): array
    {
        return ['chip' => $this->processor];
    }

    public function hasGateway(string $gateway): bool
    {
        return true;
    }

    public function getDefaultGateway(): string
    {
        return 'chip';
    }

    public function register(string $identifier, PaymentProcessorInterface $processor): void {}
}

function chk04EngineService(PaymentProcessorInterface $processor, ?string $gateWorkdir = null): CheckoutService
{
    $resolver = new Chk04EngineResolver($processor);

    $registry = new CheckoutStepRegistry;
    $registry->register('calculate_shipping', new Chk04EngineSuccessStep('calculate_shipping'));
    $registry->register('process_payment', new ProcessPaymentStep($resolver));

    $order = ['calculate_shipping', 'process_payment'];

    if ($gateWorkdir !== null) {
        $registry->register('refund_gate', new Chk04EngineGateStep($gateWorkdir));
        $order[] = 'refund_gate';
    }

    $registry->register('create_order', new Chk04EngineSuccessStep('create_order', ['process_payment']));
    $order[] = 'create_order';
    $registry->setOrder($order);

    return new CheckoutService(
        stepRegistry: $registry,
        events: app(Dispatcher::class),
        stepExecutor: new StepExecutor($registry, app(Dispatcher::class)),
        finalizer: new CheckoutFinalizer(app(Dispatcher::class)),
        paymentResolver: $resolver,
    );
}

/** @return array<string, mixed> */
function chk04EngineLoserOutcome(string $sessionId, string $workdir): array
{
    $tracker = new class
    {
        public array $voided = [];
    };

    $service = chk04EngineService(new Chk04EngineThrowingProcessor($workdir, $tracker));

    $session = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();

    try {
        $result = $service->processCheckout($session);
    } catch (Throwable $exception) {
        return [
            'threw' => true,
            'error' => $exception::class . ': ' . $exception->getMessage(),
            'voided' => $tracker->voided,
        ];
    }

    return [
        'threw' => false,
        'success' => $result->success,
        'voided' => $tracker->voided,
    ];
}

/** @return array<string, mixed> */
function chk04EngineActiveLoserOutcome(string $sessionId, string $workdir): array
{
    $tracker = new class
    {
        public array $voided = [];
    };

    $service = chk04EngineService(new Chk04EngineThrowingProcessor($workdir, $tracker, 'attempt-2-claimed'));
    $session = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();

    try {
        $result = $service->processCheckout($session);

        return [
            'threw' => false,
            'success' => $result->success,
            'processing' => $result->metadata['processing'] ?? null,
            'reconciliation' => $result->metadata['reconciliation'] ?? null,
            'voided' => $tracker->voided,
        ];
    } catch (Throwable $exception) {
        return [
            'threw' => true,
            'error' => $exception::class . ': ' . $exception->getMessage(),
            'voided' => $tracker->voided,
        ];
    } finally {
        file_put_contents($workdir . '/loser-done', (string) getmypid());
    }
}

/** @return array<string, mixed> */
function chk04EngineCompensationLoserOutcome(string $sessionId, string $workdir): array
{
    $tracker = new class
    {
        public array $voided = [];
    };

    $service = chk04EngineService(new Chk04EngineCompensatingProcessor($workdir, $tracker));
    $session = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();

    try {
        $result = $service->processCheckout($session);

        return [
            'threw' => false,
            'success' => $result->success,
            'redirect' => $result->requiresRedirect(),
            'redirectUrl' => $result->redirectUrl,
            'voided' => $tracker->voided,
        ];
    } catch (Throwable $exception) {
        return [
            'threw' => true,
            'error' => $exception::class . ': ' . $exception->getMessage(),
            'voided' => $tracker->voided,
        ];
    }
}

/** @return array<string, mixed> */
function chk04EngineCompensationRetryOutcome(string $sessionId, string $workdir): array
{
    $deadline = microtime(true) + 60;

    while (! is_file($workdir . '/compensation-started')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('retry timed out waiting for stale compensation');
        }

        usleep(5000);
    }

    $manager = app(PaymentAttemptManager::class);

    $claim = DB::transaction(function () use ($sessionId, $manager): PaymentAttemptClaim {
        $session = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->lockForUpdate()->firstOrFail();

        $paymentData = $session->payment_data ?? [];
        $paymentData['retry_requested_for_attempt'] = (int) $session->payment_attempts;

        $session->setStepState('process_payment', StepStatus::Pending);
        $session->persistState([
            'payment_redirect_url' => null,
            'error_message' => null,
            'payment_data' => $paymentData,
        ]);
        $session->transitionStatus(Processing::class);

        return $manager->claim($session, 'chip');
    });

    $session = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();
    $manager->record($session, $claim, PaymentResult::pending(
        'pay_engine_attempt_2',
        'https://gateway.example.test/engine/attempt-2',
    ));

    $session = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();
    $session->setStepState('process_payment', StepStatus::Completed);

    file_put_contents($workdir . '/attempt-2-claimed', (string) getmypid());

    return [
        'claimNumber' => $claim->number,
        'claimStatus' => $claim->status->value,
    ];
}

/** @return array<string, mixed> */
function chk04EngineWinnerOutcome(string $sessionId, string $workdir): array
{
    $deadline = microtime(true) + 60;

    while (DB::table('checkout_engine_creates')->where('session_id', $sessionId)->count() === 0) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('callback winner timed out waiting for the accepted charge');
        }

        usleep(10_000);
    }

    $service = chk04EngineService(new Chk04EngineCallbackProcessor('pay_winner_engine'));

    $callback = (new HandleCheckoutPaymentCallback($service, new CheckoutCallbackStatePolicy))
        ->handle($sessionId, 'success', [
            'status' => 'paid',
            'metadata' => [
                'checkout_attempt' => 1,
                'checkout_gateway' => 'chip',
            ],
        ]);

    file_put_contents($workdir . '/winner-done', (string) getmypid());

    return [
        'sessionNotFound' => $callback->sessionNotFound,
        'resultSuccess' => $callback->result?->success,
    ];
}

/** @return array<string, mixed> */
function chk04EngineActiveRetryOutcome(string $sessionId, string $workdir): array
{
    $service = chk04EngineService(new Chk04EngineActiveClaimProcessor($workdir));

    // The failure callback is only trustworthy once attempt 1 owns the
    // session. Wait for the stale execution's gateway entry (which happens
    // after its claim committed) before ending that generation.
    $deadline = microtime(true) + 60;

    while (DB::table('checkout_engine_creates')->where('session_id', $sessionId)->count() === 0) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('failure callback timed out waiting for attempt 1 to be claimed');
        }

        usleep(10_000);
    }

    $callback = (new HandleCheckoutPaymentCallback($service, new CheckoutCallbackStatePolicy))
        ->handle($sessionId, 'failure', [
            'status' => 'failed',
            'metadata' => ['checkout_attempt' => 1],
        ]);

    $session = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();
    $statusAfterCallback = $session->status->name();
    $rawStatusAfterCallback = (string) CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->toBase()->value('status');

    // The failed callback compensates the pre-payment steps; restore the prep
    // state so this explicit retry can release the replacement generation.
    $session->setStepState('calculate_shipping', StepStatus::Completed);
    $rawStatusBeforeRetry = (string) CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->toBase()->value('status');

    try {
        $retry = $service->retryPayment($session);
    } catch (Throwable $exception) {
        return [
            'sessionNotFound' => $callback->sessionNotFound,
            'statusAfterCallback' => $statusAfterCallback,
            'rawStatusAfterCallback' => $rawStatusAfterCallback,
            'rawStatusBeforeRetry' => $rawStatusBeforeRetry,
            'threw' => true,
            'error' => $exception::class . ': ' . $exception->getMessage(),
            'errorLocation' => $exception->getFile() . ':' . $exception->getLine(),
            'errorTrace' => mb_substr($exception->getTraceAsString(), 0, 600),
        ];
    }

    $statusAfterRetry = $session->fresh()->status->name();

    return [
        'sessionNotFound' => $callback->sessionNotFound,
        'statusAfterCallback' => $statusAfterCallback,
        'rawStatusBeforeRetry' => $rawStatusBeforeRetry,
        'statusAfterRetry' => $statusAfterRetry,
        'threw' => false,
        'success' => $retry->success,
        'processing' => $retry->metadata['processing'] ?? null,
        'reconciliation' => $retry->metadata['reconciliation'] ?? null,
    ];
}

/**
 * Pre-token interval: the stale execution owns attempt 1 and is blocked in
 * the gateway; a recovery adopts the same generation and records a completed
 * result before the stale transport error lands.
 *
 * @return array<string, mixed>
 */
function chk04EnginePreTokenLoserOutcome(string $sessionId, string $workdir): array
{
    $tracker = new class
    {
        public array $voided = [];
    };

    $service = chk04EngineService(new Chk04EngineThrowingProcessor($workdir, $tracker, 'recovery-recorded'));
    $session = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();

    try {
        $result = $service->processCheckout($session);

        return [
            'threw' => false,
            'success' => $result->success,
            'processing' => $result->metadata['processing'] ?? null,
            'reconciliation' => $result->metadata['reconciliation'] ?? null,
            'voided' => $tracker->voided,
        ];
    } catch (Throwable $exception) {
        return [
            'threw' => true,
            'error' => $exception::class . ': ' . $exception->getMessage(),
            'voided' => $tracker->voided,
        ];
    }
}

/** @return array<string, mixed> */
function chk04EnginePreTokenRecoveryOutcome(string $sessionId, string $workdir): array
{
    $deadline = microtime(true) + 60;

    while (DB::table('checkout_engine_creates')->where('session_id', $sessionId)->count() === 0) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('recovery timed out waiting for attempt 1 to reach the gateway');
        }

        usleep(5000);
    }

    $manager = app(PaymentAttemptManager::class);

    $claim = DB::transaction(function () use ($sessionId, $manager): PaymentAttemptClaim {
        $session = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->lockForUpdate()->firstOrFail();

        $paymentData = $session->payment_data ?? [];
        $paymentData['retry_requested_for_attempt'] = (int) $session->payment_attempts;

        $session->setStepState('process_payment', StepStatus::Pending);
        $session->persistState(['payment_data' => $paymentData]);

        return $manager->claim($session, 'chip');
    });

    $session = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();
    $recorded = $manager->record($session, $claim, new PaymentResult(
        status: PaymentStatus::Completed,
        paymentId: 'pay_engine_recovered',
        amount: 10000,
        currency: 'MYR',
    ));

    file_put_contents($workdir . '/recovery-recorded', (string) getmypid());

    return [
        'claimNumber' => $claim->number,
        'claimStatus' => $claim->status->value,
        'recorded' => $recorded !== null,
    ];
}

function chk13EngineCaptureFailureEvents(string $sessionId): void
{
    Event::listen(CheckoutStepFailed::class, static function (CheckoutStepFailed $event) use ($sessionId): void {
        if ((string) $event->session->getKey() !== $sessionId) {
            return;
        }

        DB::table('checkout_engine_events')->insert([
            'id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'event' => CheckoutStepFailed::class,
            'step_identifier' => $event->stepIdentifier,
        ]);
    });

    Event::listen(CheckoutFailed::class, static function (CheckoutFailed $event) use ($sessionId): void {
        if ((string) $event->session->getKey() !== $sessionId) {
            return;
        }

        DB::table('checkout_engine_events')->insert([
            'id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'event' => CheckoutFailed::class,
            'step_identifier' => null,
        ]);
    });
}

/** @return array<string, mixed> */
function chk13EngineStaleResultOutcome(string $sessionId, string $workdir): array
{
    chk13EngineCaptureFailureEvents($sessionId);

    $service = chk04EngineService(new Chk13EngineReturningProcessor(
        $workdir,
        PaymentResult::uncertain('Stale create response timed out', provider: 'chip'),
        'recovery-recorded',
    ));
    $session = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();

    try {
        $result = $service->processCheckout($session);

        return [
            'threw' => false,
            'success' => $result->success,
            'processing' => $result->metadata['processing'] ?? null,
            'reconciliation' => $result->metadata['reconciliation'] ?? null,
        ];
    } catch (Throwable $exception) {
        return [
            'threw' => true,
            'error' => $exception::class . ': ' . $exception->getMessage(),
        ];
    }
}

/** @return array<string, mixed> */
function chk13EnginePaidRecoveryOutcome(string $sessionId, string $workdir): array
{
    $deadline = microtime(true) + 60;

    while (DB::table('checkout_engine_creates')->where('session_id', $sessionId)->count() === 0) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('recovery timed out waiting for the old gateway result');
        }

        usleep(5000);
    }

    $manager = app(PaymentAttemptManager::class);
    $claim = DB::transaction(function () use ($sessionId, $manager): PaymentAttemptClaim {
        $session = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->lockForUpdate()->firstOrFail();
        $paymentData = $session->payment_data ?? [];
        $paymentData['retry_requested_for_attempt'] = (int) $session->payment_attempts;
        $session->setStepState('process_payment', StepStatus::Pending);
        $session->persistState(['payment_data' => $paymentData]);

        return $manager->claim($session, 'chip');
    });

    $session = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();
    $origin = $manager->record($session, $claim, new PaymentResult(
        status: PaymentStatus::Completed,
        paymentId: 'pay_engine_revision_winner',
        transactionId: 'txn_engine_revision_winner',
        amount: 10000,
        currency: 'MYR',
        gatewayResponse: ['receipt' => 'recovered'],
        provider: 'chip',
    ));

    $stepWritten = $origin !== null
        && $manager->recordStepStateIfOwned($session, 'process_payment', StepStatus::Completed, $origin);

    file_put_contents($workdir . '/recovery-recorded', (string) getmypid());

    return [
        'claimNumber' => $claim->number,
        'claimStatus' => $claim->status->value,
        'recorded' => $origin !== null,
        'stepWritten' => $stepWritten,
    ];
}

/** @return array<string, mixed> */
function chk13EngineUnresolvedThrowOutcome(string $sessionId, string $workdir): array
{
    $tracker = new class
    {
        public array $voided = [];
    };

    chk13EngineCaptureFailureEvents($sessionId);

    $service = chk04EngineService(new Chk04EngineThrowingProcessor(
        $workdir,
        $tracker,
        'same-generation-adopted',
    ));
    $session = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();

    try {
        $result = $service->processCheckout($session);

        return [
            'threw' => false,
            'success' => $result->success,
            'processing' => $result->metadata['processing'] ?? null,
            'reconciliation' => $result->metadata['reconciliation'] ?? null,
            'voided' => $tracker->voided,
        ];
    } catch (Throwable $exception) {
        return [
            'threw' => true,
            'error' => $exception::class . ': ' . $exception->getMessage(),
            'voided' => $tracker->voided,
        ];
    }
}

/** @return array<string, mixed> */
function chk13EngineUnresolvedAdoptionOutcome(string $sessionId, string $workdir): array
{
    $deadline = microtime(true) + 60;

    while (DB::table('checkout_engine_creates')->where('session_id', $sessionId)->count() === 0) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('recovery timed out waiting for the old gateway to start');
        }

        usleep(5000);
    }

    $manager = app(PaymentAttemptManager::class);
    $claim = DB::transaction(function () use ($sessionId, $manager): PaymentAttemptClaim {
        $session = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->lockForUpdate()->firstOrFail();
        $paymentData = $session->payment_data ?? [];
        $paymentData['retry_requested_for_attempt'] = (int) $session->payment_attempts;
        $session->persistState(['payment_data' => $paymentData]);

        return $manager->claim($session, 'chip');
    });

    file_put_contents($workdir . '/same-generation-adopted', (string) getmypid());

    return [
        'claimNumber' => $claim->number,
        'claimStatus' => $claim->status->value,
        'claimRevision' => $claim->revision,
    ];
}

/**
 * Same-generation recovery interval: the failure operation is already active
 * and its first compensation is blocked at the gate. Recovery verifies the
 * successful callback for the same attempt and completes the session before
 * the stale pass reaches the payment step's void.
 *
 * @return array<string, mixed>
 */
function chk04EngineSameGenerationLoserOutcome(string $sessionId, string $workdir): array
{
    $tracker = new class
    {
        public array $voided = [];
    };

    $service = chk04EngineService(
        new Chk04EngineCompensatingProcessor($workdir, $tracker, 'recovery-done'),
        $workdir,
    );

    $session = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();

    try {
        $result = $service->processCheckout($session);

        return [
            'threw' => false,
            'success' => $result->success,
            'processing' => $result->metadata['processing'] ?? null,
            'reconciliation' => $result->metadata['reconciliation'] ?? null,
            'voided' => $tracker->voided,
        ];
    } catch (Throwable $exception) {
        return [
            'threw' => true,
            'error' => $exception::class . ': ' . $exception->getMessage(),
            'voided' => $tracker->voided,
        ];
    }
}

/** @return array<string, mixed> */
function chk04EngineSameGenerationRecoveryOutcome(string $sessionId, string $workdir): array
{
    $deadline = microtime(true) + 60;

    while (! is_file($workdir . '/compensation-gate')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('recovery timed out waiting for the failure operation gate');
        }

        usleep(5000);
    }

    $service = chk04EngineService(new Chk04EngineCallbackProcessor('pay_engine_stale_failure'));

    $callback = (new HandleCheckoutPaymentCallback($service, new CheckoutCallbackStatePolicy))
        ->handle($sessionId, 'success', [
            'status' => 'paid',
            'id' => 'pay_engine_stale_failure',
            'metadata' => [
                'checkout_attempt' => 1,
                'checkout_gateway' => 'chip',
            ],
        ]);

    file_put_contents($workdir . '/recovery-done', (string) getmypid());

    return [
        'sessionNotFound' => $callback->sessionNotFound,
        'resultSuccess' => $callback->result?->success,
    ];
}

/**
 * CHK-04 engine race.
 *
 * The losing process accepts a charge, the winning callback completes the
 * session on its own connection, and only then does the losing process'
 * gateway call throw. The completed lifecycle, winner payment identity, step
 * map, and error state must survive, and the winner must not be compensated.
 */
it('keeps the callback winner when a losing process throws after gateway acceptance', function (): void {
    $this->useEngine();

    DB::table('checkout_engine_creates')->delete();

    $session = CheckoutSession::query()->forceCreate([
        'cart_id' => 'engine-stale-thrown-cart',
        'status' => Processing::class,
        'selected_payment_gateway' => 'chip',
        'grand_total' => 10000,
        'currency' => 'MYR',
        'step_states' => [
            'calculate_shipping' => StepStatus::Pending->value,
            'process_payment' => StepStatus::Pending->value,
            'create_order' => StepStatus::Pending->value,
        ],
    ]);

    $sessionId = (string) $session->getKey();

    $results = EngineRace::run([
        'loser' => static fn (string $workdir): array => chk04EngineLoserOutcome($sessionId, $workdir),
        'winner' => static fn (string $workdir): array => chk04EngineWinnerOutcome($sessionId, $workdir),
    ]);

    expect($results['loser']['threw'])->toBeFalse()
        ->and($results['loser']['success'])->toBeTrue()
        ->and($results['loser']['voided'])->toBe([])
        ->and($results['winner']['sessionNotFound'])->toBeFalse()
        ->and($results['winner']['resultSuccess'])->toBeTrue()
        ->and(DB::table('checkout_engine_creates')->where('session_id', $sessionId)->count())->toBe(1);

    $fresh = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();

    expect($fresh->status)->toBeInstanceOf(Completed::class)
        ->and($fresh->payment_id)->toBe('pay_winner_engine')
        ->and($fresh->error_message)->toBeNull()
        ->and((int) $fresh->payment_attempts)->toBe(1)
        ->and(data_get($fresh->step_states, 'calculate_shipping'))->toBe(StepStatus::Completed->value)
        ->and(data_get($fresh->step_states, 'process_payment'))->toBe(StepStatus::Completed->value)
        ->and(data_get($fresh->step_states, 'create_order'))->toBe(StepStatus::Completed->value)
        ->and($fresh->current_step)->toBe('create_order');
});

/**
 * CHK-04 engine race for the active-generation window.
 *
 * The stale execution claims attempt 1 and blocks in the gateway. A verified
 * failure callback then ends attempt 1, an explicit retry claims attempt 2,
 * and the stale execution's exception lands while attempt 2 is claimed but
 * before its response is recorded. Attempt 2's claim, lifecycle, payment
 * identity, step map, and error state must survive untouched.
 */
it('keeps a newer active generation when a stale process throws', function (): void {
    $this->useEngine();

    DB::table('checkout_engine_creates')->delete();

    $session = CheckoutSession::query()->forceCreate([
        'cart_id' => 'engine-active-generation-cart',
        'status' => Processing::class,
        'selected_payment_gateway' => 'chip',
        'grand_total' => 10000,
        'currency' => 'MYR',
        'step_states' => [
            'calculate_shipping' => StepStatus::Pending->value,
            'process_payment' => StepStatus::Pending->value,
            'create_order' => StepStatus::Pending->value,
        ],
    ]);

    $sessionId = (string) $session->getKey();

    $results = EngineRace::run([
        'loser' => static fn (string $workdir): array => chk04EngineActiveLoserOutcome($sessionId, $workdir),
        'retry' => static fn (string $workdir): array => chk04EngineActiveRetryOutcome($sessionId, $workdir),
    ]);

    expect($results['retry']['rawStatusBeforeRetry'])->toBe('payment_failed')
        ->and($results['retry']['statusAfterCallback'])->toBe('payment_failed')
        ->and($results['loser']['threw'])->toBeFalse()
        ->and($results['loser']['success'])->toBeFalse()
        ->and($results['loser']['processing'])->toBeTrue()
        ->and($results['loser']['reconciliation'])->toBeTrue()
        ->and($results['loser']['voided'])->toBe([])
        ->and($results['retry']['sessionNotFound'])->toBeFalse()
        ->and($results['retry']['threw'])->toBeFalse()
        ->and($results['retry']['processing'])->toBeTrue();

    $fresh = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();

    expect($fresh->status)->toBeInstanceOf(PaymentProcessing::class)
        ->and((int) $fresh->payment_attempts)->toBe(2)
        ->and($fresh->payment_id)->toBe('pay_attempt_2')
        ->and($fresh->error_message)->toBeNull()
        ->and(data_get($fresh->step_states, 'calculate_shipping'))->toBe(StepStatus::Completed->value)
        ->and(data_get($fresh->step_states, 'process_payment'))->toBe(StepStatus::Processing->value)
        ->and(data_get($fresh->step_states, 'create_order'))->toBe(StepStatus::Pending->value)
        ->and($fresh->current_step)->toBe('process_payment');

    $attemptTwoCompensations = array_filter(
        $fresh->getCompensationLog(),
        static fn (array $entry): bool => ($entry['data']['payment_id'] ?? null) === 'pay_attempt_2',
    );

    expect($attemptTwoCompensations)->toBe([]);
});

/**
 * CHK-04 engine race for the post-check compensation interval.
 *
 * The stale execution records a definitive failure for attempt 1 and blocks
 * inside the gateway void after its failure-operation token was issued. The
 * retry connection claims attempt 2 and records its redirect before the stale
 * compensation resumes. The replacement claim, payment identity, step map,
 * lifecycle, error state, and operation token must survive; the stale
 * compensation may only leave additive superseded evidence.
 */
it('keeps a replacement claim recorded while a stale failure is compensating', function (): void {
    $this->useEngine();

    DB::table('checkout_engine_creates')->delete();

    $session = CheckoutSession::query()->forceCreate([
        'cart_id' => 'engine-compensation-race-cart',
        'status' => Processing::class,
        'selected_payment_gateway' => 'chip',
        'grand_total' => 10000,
        'currency' => 'MYR',
        'step_states' => [
            'calculate_shipping' => StepStatus::Pending->value,
            'process_payment' => StepStatus::Pending->value,
            'create_order' => StepStatus::Pending->value,
        ],
    ]);

    $sessionId = (string) $session->getKey();

    $results = EngineRace::run([
        'stale' => static fn (string $workdir): array => chk04EngineCompensationLoserOutcome($sessionId, $workdir),
        'retry' => static fn (string $workdir): array => chk04EngineCompensationRetryOutcome($sessionId, $workdir),
    ]);

    expect($results['stale']['threw'])->toBeFalse()
        ->and($results['stale']['redirect'])->toBeTrue()
        ->and($results['stale']['redirectUrl'])->toBe('https://gateway.example.test/engine/attempt-2')
        ->and($results['stale']['voided'])->toBe(['pay_engine_stale_failure'])
        ->and($results['retry']['claimNumber'])->toBe(2)
        ->and($results['retry']['claimStatus'])->toBe('claimed');

    $fresh = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();

    expect($fresh->status)->toBeInstanceOf(AwaitingPayment::class)
        ->and((int) $fresh->payment_attempts)->toBe(2)
        ->and(data_get($fresh->payment_data, 'payment_attempt.number'))->toBe(2)
        ->and(data_get($fresh->payment_data, 'payment_attempt.idempotency_key'))->toEndWith(':attempt:2')
        ->and(data_get($fresh->payment_data, 'payment_failure_operation'))->toBeNull()
        ->and($fresh->payment_id)->toBe('pay_engine_attempt_2')
        ->and($fresh->payment_redirect_url)->toBe('https://gateway.example.test/engine/attempt-2')
        ->and($fresh->error_message)->toBeNull()
        ->and($fresh->getCompensationLog())->toBe([])
        ->and(data_get($fresh->step_states, 'process_payment'))->toBe(StepStatus::Completed->value)
        ->and(data_get($fresh->step_states, 'create_order'))->toBe(StepStatus::Pending->value)
        ->and(data_get($fresh->payment_data, 'superseded_failure_compensations.0.attempts'))->toBe(1)
        ->and(data_get($fresh->payment_data, 'superseded_failure_compensations.0.step_identifier'))->toBe('process_payment');
});

/**
 * CHK-04 engine race for the pre-token interval.
 *
 * A recovery adopts attempt 1 and records its completed result while the
 * stale execution is still inside the gateway. The stale transport error then
 * lands after the recovery committed. It must observe quietly: no failure
 * operation, no void, no lifecycle/error overwrite.
 */
it('keeps a same-generation recovery that lands before the failure token', function (): void {
    $this->useEngine();

    DB::table('checkout_engine_creates')->delete();

    $session = CheckoutSession::query()->forceCreate([
        'cart_id' => 'engine-pre-token-cart',
        'status' => Processing::class,
        'selected_payment_gateway' => 'chip',
        'grand_total' => 10000,
        'currency' => 'MYR',
        'step_states' => [
            'calculate_shipping' => StepStatus::Pending->value,
            'process_payment' => StepStatus::Pending->value,
            'create_order' => StepStatus::Pending->value,
        ],
    ]);

    $sessionId = (string) $session->getKey();

    $results = EngineRace::run([
        'stale' => static fn (string $workdir): array => chk04EnginePreTokenLoserOutcome($sessionId, $workdir),
        'recovery' => static fn (string $workdir): array => chk04EnginePreTokenRecoveryOutcome($sessionId, $workdir),
    ]);

    expect($results['recovery']['claimNumber'])->toBe(1)
        ->and($results['recovery']['claimStatus'])->toBe('adopted')
        ->and($results['recovery']['recorded'])->toBeTrue()
        ->and($results['stale']['threw'])->toBeFalse()
        ->and($results['stale']['success'])->toBeFalse()
        ->and($results['stale']['processing'])->toBeTrue()
        ->and($results['stale']['reconciliation'])->toBeNull()
        ->and($results['stale']['voided'])->toBe([]);

    $fresh = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();

    expect($fresh->status)->toBeInstanceOf(Processing::class)
        ->and((int) $fresh->payment_attempts)->toBe(1)
        ->and($fresh->payment_id)->toBe('pay_engine_recovered')
        ->and($fresh->error_message)->toBeNull()
        ->and(data_get($fresh->payment_data, 'payment_failure_operation'))->toBeNull()
        ->and(data_get($fresh->payment_data, 'payment_attempt_outcome.payment_id'))->toBe('pay_engine_recovered')
        ->and($fresh->getCompensationLog())->toBe([]);
});

/**
 * CHK-04 engine race for the same-generation recovery interval.
 *
 * The failing execution has already begun its failure operation and is
 * blocked in the first compensation. A verified success callback for the same
 * attempt completes the session before the stale pass reaches the payment
 * step. The stale pass must stop at the gate, leave the payment unvoided, and
 * observe the completed lifecycle instead of overwriting it.
 */
it('stops a stale failure when the same generation recovers after the failure token', function (): void {
    $this->useEngine();

    DB::table('checkout_engine_creates')->delete();

    $session = CheckoutSession::query()->forceCreate([
        'cart_id' => 'engine-same-generation-recovery-cart',
        'status' => Processing::class,
        'selected_payment_gateway' => 'chip',
        'grand_total' => 10000,
        'currency' => 'MYR',
        'step_states' => [
            'calculate_shipping' => StepStatus::Pending->value,
            'process_payment' => StepStatus::Pending->value,
            'refund_gate' => StepStatus::Completed->value,
            'create_order' => StepStatus::Pending->value,
        ],
    ]);

    $sessionId = (string) $session->getKey();

    $results = EngineRace::run([
        'stale' => static fn (string $workdir): array => chk04EngineSameGenerationLoserOutcome($sessionId, $workdir),
        'recovery' => static fn (string $workdir): array => chk04EngineSameGenerationRecoveryOutcome($sessionId, $workdir),
    ]);

    expect($results['recovery']['sessionNotFound'])->toBeFalse()
        ->and($results['recovery']['resultSuccess'])->toBeTrue()
        ->and($results['stale']['threw'])->toBeFalse()
        ->and($results['stale']['success'])->toBeTrue()
        ->and($results['stale']['voided'])->toBe([]);

    $fresh = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();

    expect($fresh->status)->toBeInstanceOf(Completed::class)
        ->and((int) $fresh->payment_attempts)->toBe(1)
        ->and($fresh->payment_id)->toBe('pay_engine_stale_failure')
        ->and($fresh->error_message)->toBeNull()
        ->and(data_get($fresh->step_states, 'process_payment'))->toBe(StepStatus::Completed->value)
        ->and(data_get($fresh->step_states, 'create_order'))->toBe(StepStatus::Completed->value)
        ->and($fresh->getCompensationLog())->toBe([]);

    $superseded = data_get($fresh->payment_data, 'superseded_failure_compensations', []);

    expect($superseded)->not->toBe([])
        ->and(data_get($superseded, '0.step_identifier'))->toBe('refund_gate');
});

/**
 * CHK-13 engine race for the accepted-result interval.
 *
 * A recovery adopts attempt 1 and commits a paid result while the original
 * create call is blocked. The old call then returns an uncertain result with
 * no payment id. Its claim revision is stale even though its generation is
 * unchanged, so it must not erase the paid evidence or completed step state.
 */
it('keeps a paid same-generation recovery when the old create response arrives later', function (): void {
    $this->useEngine();

    DB::table('checkout_engine_creates')->delete();
    DB::table('checkout_engine_events')->delete();

    $session = CheckoutSession::query()->forceCreate([
        'cart_id' => 'engine-revision-stale-result-cart',
        'status' => Processing::class,
        'selected_payment_gateway' => 'chip',
        'grand_total' => 10000,
        'currency' => 'MYR',
        'step_states' => [
            'calculate_shipping' => StepStatus::Pending->value,
            'process_payment' => StepStatus::Pending->value,
            'create_order' => StepStatus::Pending->value,
        ],
    ]);

    $sessionId = (string) $session->getKey();

    $results = EngineRace::run([
        'stale' => static fn (string $workdir): array => chk13EngineStaleResultOutcome($sessionId, $workdir),
        'recovery' => static fn (string $workdir): array => chk13EnginePaidRecoveryOutcome($sessionId, $workdir),
    ]);

    expect($results['recovery']['claimNumber'])->toBe(1)
        ->and($results['recovery']['claimStatus'])->toBe('adopted')
        ->and($results['recovery']['recorded'])->toBeTrue()
        ->and($results['recovery']['stepWritten'])->toBeTrue()
        ->and($results['stale']['threw'])->toBeFalse()
        ->and($results['stale']['success'])->toBeFalse()
        ->and($results['stale']['processing'])->toBeTrue()
        ->and(DB::table('checkout_engine_events')->where('session_id', $sessionId)->count())->toBe(0)
        ->and(DB::table('checkout_engine_creates')->where('session_id', $sessionId)->count())->toBe(1);

    $fresh = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();

    expect($fresh->status)->toBeInstanceOf(Processing::class)
        ->and((int) $fresh->payment_attempts)->toBe(1)
        ->and($fresh->payment_id)->toBe('pay_engine_revision_winner')
        ->and($fresh->error_message)->toBeNull()
        ->and(data_get($fresh->payment_data, 'payment_attempt_outcome.payment_id'))->toBe('pay_engine_revision_winner')
        ->and(data_get($fresh->payment_data, 'payment_attempt_outcome.uncertain'))->toBeFalse()
        ->and(data_get($fresh->payment_data, 'provider'))->toBe('chip')
        ->and(data_get($fresh->payment_data, 'gateway_response.receipt'))->toBe('recovered')
        ->and(data_get($fresh->step_states, 'process_payment'))->toBe(StepStatus::Completed->value)
        ->and(data_get($fresh->payment_data, 'payment_failure_operation'))->toBeNull();
});

/**
 * CHK-13 engine race for adoption before an unresolved thrown response.
 *
 * Attempt 1 is adopted by a recovery process but remains in PaymentProcessing
 * with no recorded response. The old owner then throws. Its earlier revision
 * no longer authorizes a failed step or failure notification.
 */
it('does not write a failed step when a same-generation adoption precedes a throw', function (): void {
    $this->useEngine();

    DB::table('checkout_engine_creates')->delete();
    DB::table('checkout_engine_events')->delete();

    $session = CheckoutSession::query()->forceCreate([
        'cart_id' => 'engine-adopted-unresolved-throw-cart',
        'status' => Processing::class,
        'selected_payment_gateway' => 'chip',
        'grand_total' => 10000,
        'currency' => 'MYR',
        'step_states' => [
            'calculate_shipping' => StepStatus::Pending->value,
            'process_payment' => StepStatus::Pending->value,
            'create_order' => StepStatus::Pending->value,
        ],
    ]);

    $sessionId = (string) $session->getKey();

    $results = EngineRace::run([
        'stale' => static fn (string $workdir): array => chk13EngineUnresolvedThrowOutcome($sessionId, $workdir),
        'recovery' => static fn (string $workdir): array => chk13EngineUnresolvedAdoptionOutcome($sessionId, $workdir),
    ]);

    expect($results['recovery']['claimNumber'])->toBe(1)
        ->and($results['recovery']['claimStatus'])->toBe('adopted')
        ->and($results['stale']['threw'])->toBeFalse()
        ->and($results['stale']['success'])->toBeFalse()
        ->and($results['stale']['processing'])->toBeTrue()
        ->and($results['stale']['reconciliation'])->toBeTrue()
        ->and($results['stale']['voided'])->toBe([])
        ->and(DB::table('checkout_engine_events')->where('session_id', $sessionId)->count())->toBe(0);

    $fresh = CheckoutSession::withoutOwnerScope()->whereKey($sessionId)->firstOrFail();

    expect($fresh->status)->toBeInstanceOf(PaymentProcessing::class)
        ->and((int) $fresh->payment_attempts)->toBe(1)
        ->and($fresh->payment_id)->toBeNull()
        ->and($fresh->error_message)->toBeNull()
        ->and(data_get($fresh->payment_data, 'payment_attempt_outcome'))->toBeNull()
        ->and(data_get($fresh->step_states, 'process_payment'))->toBe(StepStatus::Processing->value)
        ->and(data_get($fresh->payment_data, 'payment_failure_operation'))->toBeNull();
});
