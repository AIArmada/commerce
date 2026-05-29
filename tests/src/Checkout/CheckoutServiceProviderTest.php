<?php

declare(strict_types=1);

use AIArmada\Checkout\CheckoutServiceProvider;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Checkout\Contracts\CheckoutStepInterface;
use AIArmada\Checkout\Contracts\CheckoutStepRegistryInterface;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Data\StepResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\Services\CheckoutService;
use AIArmada\Checkout\Services\CheckoutStepRegistry;
use AIArmada\Checkout\Services\PaymentGatewayResolver;
use AIArmada\Checkout\Support\ChipIntegrationRegistrar;
use AIArmada\Checkout\Support\HandleChipPurchaseEventForCheckout;
use AIArmada\Chip\Events\PurchaseCancelled;
use AIArmada\Chip\Events\PurchasePaid;
use AIArmada\Chip\Events\PurchasePaymentFailure;
use Illuminate\Support\Facades\Event;

describe('CheckoutServiceProvider', function (): void {
    it('provides correct services list', function (): void {
        $provider = new CheckoutServiceProvider(app());
        $provides = $provider->provides();

        expect($provides)->toContain('checkout')
            ->and($provides)->toContain(CheckoutService::class)
            ->and($provides)->toContain(CheckoutServiceInterface::class)
            ->and($provides)->toContain(CheckoutStepRegistry::class)
            ->and($provides)->toContain(CheckoutStepRegistryInterface::class)
            ->and($provides)->toContain(PaymentGatewayResolver::class)
            ->and($provides)->toContain(PaymentGatewayResolverInterface::class);
    });

    it('provides correct services count', function (): void {
        $provider = new CheckoutServiceProvider(app());
        $provides = $provider->provides();

        expect($provides)->toHaveCount(7);
    });

    it('does not register chip listeners when the chip checkout integration is disabled', function (): void {
        config()->set('checkout.integrations.chip.enabled', false);

        Event::shouldReceive('listen')->never();

        $registrar = new ChipIntegrationRegistrar;

        $registrar->register();
    });

    it('registers chip listeners when the chip checkout integration is enabled', function (): void {
        config()->set('checkout.integrations.chip.enabled', true);

        Event::shouldReceive('listen')
            ->once()
            ->with(PurchasePaid::class, HandleChipPurchaseEventForCheckout::class);

        Event::shouldReceive('listen')
            ->once()
            ->with(PurchasePaymentFailure::class, HandleChipPurchaseEventForCheckout::class);

        Event::shouldReceive('listen')
            ->once()
            ->with(PurchaseCancelled::class, HandleChipPurchaseEventForCheckout::class);

        $registrar = new ChipIntegrationRegistrar;

        $registrar->register();
    });

    it('keeps reserve_inventory before process_payment when configured', function (): void {
        config()->set('checkout.integrations.inventory.reserve_before_payment', true);

        $provider = new CheckoutServiceProvider(app());

        expect(resolveInventoryStepOrder($provider, [
            'validate_cart',
            'resolve_customer',
            'calculate_pricing',
            'apply_discounts',
            'calculate_shipping',
            'calculate_tax',
            'reserve_inventory',
            'process_payment',
            'persist_customer',
            'create_order',
            'dispatch_documents',
        ]))->toBe([
            'validate_cart',
            'resolve_customer',
            'calculate_pricing',
            'apply_discounts',
            'calculate_shipping',
            'calculate_tax',
            'reserve_inventory',
            'process_payment',
            'persist_customer',
            'create_order',
            'dispatch_documents',
        ]);
    });

    it('moves reserve_inventory into the post-payment phase when configured', function (): void {
        config()->set('checkout.integrations.inventory.reserve_before_payment', false);

        $provider = new CheckoutServiceProvider(app());

        expect(resolveInventoryStepOrder($provider, [
            'validate_cart',
            'resolve_customer',
            'calculate_pricing',
            'apply_discounts',
            'calculate_shipping',
            'calculate_tax',
            'reserve_inventory',
            'process_payment',
            'persist_customer',
            'create_order',
            'dispatch_documents',
        ]))->toBe([
            'validate_cart',
            'resolve_customer',
            'calculate_pricing',
            'apply_discounts',
            'calculate_shipping',
            'calculate_tax',
            'process_payment',
            'reserve_inventory',
            'persist_customer',
            'create_order',
            'dispatch_documents',
        ]);
    });

    it('does not re-add reserve_inventory when the configured order omits it', function (): void {
        config()->set('checkout.integrations.inventory.reserve_before_payment', false);

        $provider = new CheckoutServiceProvider(app());

        expect(resolveInventoryStepOrder($provider, [
            'validate_cart',
            'resolve_customer',
            'calculate_pricing',
            'apply_discounts',
            'calculate_shipping',
            'calculate_tax',
            'process_payment',
            'persist_customer',
            'create_order',
            'dispatch_documents',
        ]))->toBe([
            'validate_cart',
            'resolve_customer',
            'calculate_pricing',
            'apply_discounts',
            'calculate_shipping',
            'calculate_tax',
            'process_payment',
            'persist_customer',
            'create_order',
            'dispatch_documents',
        ]);
    });

    it('preserves steps that were appended to the live registry order', function (): void {
        config()->set('checkout.integrations.inventory.reserve_before_payment', false);

        $registry = new CheckoutStepRegistry;
        $registry->register('resolve_customer', createProviderTestStep('resolve_customer'));
        $registry->register('calculate_pricing', createProviderTestStep('calculate_pricing'));
        $registry->register('reserve_inventory', createProviderTestStep('reserve_inventory'));
        $registry->register('process_payment', createProviderTestStep('process_payment'));
        $registry->register('create_order', createProviderTestStep('create_order'));
        $registry->setOrder([
            'resolve_customer',
            'calculate_pricing',
            'reserve_inventory',
            'process_payment',
            'create_order',
        ]);
        $registry->register('persist_customer', createProviderTestStep('persist_customer'));

        app()->instance(CheckoutStepRegistryInterface::class, $registry);

        $provider = new CheckoutServiceProvider(app());
        invokeInventoryStepNormalizer($provider);

        expect($registry->getOrder())->toBe([
            'resolve_customer',
            'calculate_pricing',
            'process_payment',
            'reserve_inventory',
            'create_order',
            'persist_customer',
        ]);
    });
});

function resolveInventoryStepOrder(CheckoutServiceProvider $provider, array $order): array
{
    $method = new ReflectionMethod($provider, 'resolveInventoryStepOrder');
    $method->setAccessible(true);

    /** @var array<string> $resolvedOrder */
    $resolvedOrder = $method->invoke($provider, $order);

    return $resolvedOrder;
}

function invokeInventoryStepNormalizer(CheckoutServiceProvider $provider): void
{
    $method = new ReflectionMethod($provider, 'normalizeInventoryStepOrder');
    $method->setAccessible(true);
    $method->invoke($provider);
}

function createProviderTestStep(string $identifier): CheckoutStepInterface
{
    return new class($identifier) implements CheckoutStepInterface
    {
        public function __construct(
            private readonly string $identifier,
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

        public function rollback(CheckoutSession $session): void {}

        public function getDependencies(): array
        {
            return [];
        }
    };
}
