<?php

declare(strict_types=1);

use AIArmada\Cart\CartServiceProvider;
use AIArmada\CommerceSupport\SupportServiceProvider;
use Illuminate\Events\Dispatcher;

require_once __DIR__ . '/../../Support/OctaneEventShims.php';

// A real Octane worker soak test is blocked here because this proof must remain static and server-free.
it('registers every expected Octane lifecycle listener', function (): void {
    $originalEvents = app('events');
    $dispatcher = new Dispatcher(app());
    app()->instance('events', $dispatcher);

    try {
        $supportProvider = new SupportServiceProvider(app());
        $supportMethod = new ReflectionMethod($supportProvider, 'registerOctaneListeners');
        $supportMethod->invoke($supportProvider);

        $cartProvider = new CartServiceProvider(app());
        $cartMethod = new ReflectionMethod($cartProvider, 'registerOctaneListeners');
        $cartMethod->invoke($cartProvider);

        $receivedEvent = 'Laravel\\Octane\\Events\\RequestReceived';
        $terminatedEvent = 'Laravel\\Octane\\Events\\RequestTerminated';
        $repositoryRoot = dirname(__DIR__, 3);
        $supportProviderPath = realpath($repositoryRoot . '/packages/commerce-support/src/SupportServiceProvider.php');
        $cartProviderPath = realpath($repositoryRoot . '/packages/cart/src/CartServiceProvider.php');

        if ($supportProviderPath === false || $cartProviderPath === false) {
            throw new RuntimeException('Octane listener provider source could not be located.');
        }

        $registeredListeners = (array) new ReflectionProperty(Dispatcher::class, 'listeners')->getValue($dispatcher);
        $sourceForListener = static function (mixed $listener): string {
            if (! $listener instanceof Closure) {
                return '';
            }

            $reflection = new ReflectionFunction($listener);
            $fileName = $reflection->getFileName();

            if (! is_string($fileName)) {
                return '';
            }

            $lines = file($fileName, FILE_IGNORE_NEW_LINES);

            if ($lines === false) {
                return '';
            }

            return implode("\n", array_slice(
                $lines,
                $reflection->getStartLine() - 1,
                $reflection->getEndLine() - $reflection->getStartLine() + 1,
            ));
        };
        $supportListeners = array_values(array_filter(
            array_merge(
                $registeredListeners[$receivedEvent] ?? [],
                $registeredListeners[$terminatedEvent] ?? [],
            ),
            static fn (mixed $listener): bool => $listener instanceof Closure
                && (new ReflectionFunction($listener))->getFileName() === $supportProviderPath,
        ));
        $cartListeners = array_values(array_filter(
            $registeredListeners[$receivedEvent] ?? [],
            static fn (mixed $listener): bool => $listener instanceof Closure
                && (new ReflectionFunction($listener))->getFileName() === $cartProviderPath,
        ));

        expect($registeredListeners[$receivedEvent] ?? [])->toHaveCount(2)
            ->and($registeredListeners[$terminatedEvent] ?? [])->toHaveCount(1)
            ->and($supportListeners)->toHaveCount(2)
            ->and($cartListeners)->toHaveCount(1);

        foreach ($supportListeners as $listener) {
            $listenerSource = $sourceForListener($listener);

            expect($listenerSource)->toContain('OwnerContext::flushState()')
                ->and($listenerSource)->toContain('AuditableModelRegistry::class')
                ->and($listenerSource)->toContain('LoggableModelRegistry::class');
        }

        $cartListenerSource = $sourceForListener($cartListeners[0]);

        expect($cartListenerSource)->toContain("app()->forgetInstance('cart.storage')")
            ->and($cartListenerSource)->toContain("app()->forgetInstance('cart')")
            ->and($cartListenerSource)->toContain('app()->forgetInstance(CartFactory::class)')
            ->and($cartListenerSource)->toContain('ConditionPresets::restoreOctaneDefaults()')
            ->and($cartListenerSource)->toContain('RulePresets::restoreOctaneDefaults()');
    } finally {
        app()->instance('events', $originalEvents);
    }
});
