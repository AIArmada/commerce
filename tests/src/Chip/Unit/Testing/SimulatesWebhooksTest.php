<?php

declare(strict_types=1);

use AIArmada\Chip\Testing\SimulatesWebhooks;
use AIArmada\Chip\Enums\WebhookEventType;
use AIArmada\Chip\Testing\WebhookSimulator;

/**
 * A test class that uses the SimulatesWebhooks trait.
 */
class TraitTestClass
{
    use SimulatesWebhooks;

    public function testSimulateWebhook()
    {
        return $this->simulateWebhook();
    }

    public function testSimulatePaidWebhook(?string $url = null)
    {
        return $this->simulatePaidWebhook($url);
    }

    public function testSimulateFailedWebhook(?string $url = null)
    {
        return $this->simulateFailedWebhook($url);
    }

    public function testSimulateCancelledWebhook(?string $url = null)
    {
        return $this->simulateCancelledWebhook($url);
    }

    public function testSimulateRefundedWebhook(?string $url = null)
    {
        return $this->simulateRefundedWebhook($url);
    }

    public function testSimulateWebhookEvent(WebhookEventType $eventType, ?string $url = null)
    {
        return $this->simulateWebhookEvent($eventType, $url);
    }

    public function testDispatchWebhookEvent(WebhookEventType $eventType, array $overrides = [])
    {
        return $this->dispatchWebhookEvent($eventType, $overrides);
    }

    public function testFakeWebhookEvents(?array $eventsToFake = null)
    {
        return $this->fakeWebhookEvents($eventsToFake);
    }

    public function testWithoutWebhookSignatureVerification()
    {
        return $this->withoutWebhookSignatureVerification();
    }
}

describe('SimulatesWebhooks trait', function () {
    it('creates webhook simulator', function () {
        $test = new TraitTestClass;
        $simulator = $test->testSimulateWebhook();

        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('creates paid webhook simulator', function () {
        $test = new TraitTestClass;
        $simulator = $test->testSimulatePaidWebhook();

        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
        expect($simulator->getPayload()['status'])->toBe('paid');
    });

    it('creates paid webhook simulator with URL', function () {
        $test = new TraitTestClass;
        $simulator = $test->testSimulatePaidWebhook('https://example.com/webhook');

        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('creates failed webhook simulator', function () {
        $test = new TraitTestClass;
        $simulator = $test->testSimulateFailedWebhook();

        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('creates cancelled webhook simulator', function () {
        $test = new TraitTestClass;
        $simulator = $test->testSimulateCancelledWebhook();

        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('creates refunded webhook simulator', function () {
        $test = new TraitTestClass;
        $simulator = $test->testSimulateRefundedWebhook();

        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('creates simulator for specific event type', function () {
        $test = new TraitTestClass;
        $simulator = $test->testSimulateWebhookEvent(WebhookEventType::PurchasePaid);

        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('creates simulator for event type with URL', function () {
        $test = new TraitTestClass;
        $simulator = $test->testSimulateWebhookEvent(WebhookEventType::PurchasePaid, 'https://example.com');

        expect($simulator)->toBeInstanceOf(WebhookSimulator::class);
    });

    it('disables signature verification', function () {
        config(['chip.webhooks.verify_signature' => true]);

        $test = new TraitTestClass;
        $result = $test->testWithoutWebhookSignatureVerification();

        expect($result)->toBe($test);
        expect(config('chip.webhooks.verify_signature'))->toBeFalse();
    });
});
