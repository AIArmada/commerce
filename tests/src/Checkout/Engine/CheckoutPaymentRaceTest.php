<?php

declare(strict_types=1);

use AIArmada\Checkout\Contracts\PaymentCompensationInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Enums\PaymentStatus;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\AwaitingPayment;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Steps\ProcessPaymentStep;
use AIArmada\Commerce\Tests\Checkout\CheckoutEngineTestCase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EngineRace;

uses(CheckoutEngineTestCase::class);

/**
 * Engine race gateway: every createPayment call lands one row in the race
 * ledger, so the ledger count is the ground truth for how many charges the
 * gateway accepted. A short delay keeps the winning charge in flight while
 * the losing caller's claim lands.
 */
final class CheckoutEngineRaceProcessor implements PaymentCompensationInterface, PaymentProcessorInterface
{
    public function getIdentifier(): string
    {
        return 'chip';
    }

    public function getName(): string
    {
        return 'Engine race gateway';
    }

    public function isAvailable(CheckoutSession $session): bool
    {
        return true;
    }

    public function createPayment(CheckoutSession $session, PaymentRequest $request): PaymentResult
    {
        usleep(150_000);

        DB::table('checkout_engine_creates')->insert([
            'id' => (string) Str::uuid(),
            'session_id' => (string) $session->getKey(),
            'attempt' => (int) ($request->metadata['checkout_attempt'] ?? 0),
            'idempotency_key' => (string) ($request->metadata['checkout_idempotency_key'] ?? ''),
            'created_at' => CarbonImmutable::now()->toIso8601String(),
        ]);

        return PaymentResult::pending(
            'pay_engine_' . mb_substr(hash('sha256', (string) $session->getKey()), 0, 16),
            'https://gateway.example.test/pay/engine',
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

    public function voidPayment(string $paymentId, ?string $reason = null): PaymentResult
    {
        return new PaymentResult(status: PaymentStatus::Cancelled, paymentId: $paymentId);
    }
}

/**
 * @return array<string, mixed>
 */
function checkoutEngineAttemptOutcome(string $sessionId): array
{
    try {
        $session = CheckoutSession::query()->findOrFail($sessionId);

        $resolver = new class(new CheckoutEngineRaceProcessor) implements PaymentGatewayResolverInterface
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
        };

        $result = (new ProcessPaymentStep($resolver))->handle($session);

        return [
            'ok' => true,
            'observed' => $result->isObserved(),
            'success' => $result->isSuccessful(),
        ];
    } catch (Throwable $exception) {
        return [
            'ok' => false,
            'error' => $exception::class,
            'message' => $exception->getMessage(),
        ];
    }
}

/**
 * CHK-01 engine race.
 *
 * Two processes drive the same pending session through the real payment step
 * on independent PostgreSQL connections. The claim must serialize them so
 * exactly one charge reaches the gateway, the loser observes instead of
 * re-driving, and the persisted attempt identity stays frozen at one.
 */
it('claims exactly one gateway attempt when two processes race one session', function (): void {
    $this->useEngine();

    for ($round = 0; $round < 3; $round++) {
        DB::table('checkout_engine_creates')->delete();

        $session = CheckoutSession::query()->forceCreate([
            'cart_id' => 'engine-race-cart-' . $round,
            'status' => Processing::class,
            'selected_payment_gateway' => 'chip',
            'grand_total' => 10000,
            'currency' => 'MYR',
        ]);
        $sessionId = (string) $session->getKey();

        $results = EngineRace::run([
            'process_a' => static function (string $workdir) use ($sessionId): array {
                return checkoutEngineAttemptOutcome($sessionId);
            },
            'process_b' => static function (string $workdir) use ($sessionId): array {
                return checkoutEngineAttemptOutcome($sessionId);
            },
        ]);

        expect($results['process_a']['ok'])->toBeTrue()
            ->and($results['process_b']['ok'])->toBeTrue();

        $owners = array_filter($results, static fn (array $outcome): bool => $outcome['observed'] === false);
        $observers = array_filter($results, static fn (array $outcome): bool => $outcome['observed'] === true);

        expect($owners)->toHaveCount(1)
            ->and($observers)->toHaveCount(1)
            ->and(DB::table('checkout_engine_creates')->where('session_id', $sessionId)->count())->toBe(1);

        $session->refresh();

        $expectedPaymentId = 'pay_engine_' . mb_substr(hash('sha256', $sessionId), 0, 16);
        $outcome = data_get($session->payment_data ?? [], 'payment_attempt_outcome');

        expect($session->status)->toBeInstanceOf(AwaitingPayment::class)
            ->and((int) $session->payment_attempts)->toBe(1)
            ->and($session->payment_id)->toBe($expectedPaymentId)
            ->and($session->payment_redirect_url)->toBe('https://gateway.example.test/pay/engine')
            ->and($outcome)->toBeArray()
            ->and((int) $outcome['number'])->toBe(1)
            ->and($outcome['status'])->toBe(PaymentStatus::Pending->value);
    }
});
