<?php

declare(strict_types=1);

use AIArmada\Cashier\Gateways\Stripe\StripeCustomer;
use AIArmada\Checkout\Contracts\PaymentGatewayResolverInterface;
use AIArmada\Checkout\Contracts\PaymentProcessorInterface;
use AIArmada\Checkout\Data\PaymentRequest;
use AIArmada\Checkout\Data\PaymentResult;
use AIArmada\Checkout\Models\CheckoutSession;
use AIArmada\Checkout\States\Processing;
use AIArmada\Checkout\Steps\ProcessPaymentStep;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\Events\Models\EventRegistrationItem;
use AIArmada\Events\Resolvers\DefaultEventOrderItemFulfillmentResolver;
use Illuminate\Support\Str;
use Stripe\Customer as StripeApiCustomer;

use function Pest\Laravel\mock;

test('keeps the three owning reads below the one percent null/degraded rate threshold', function (): void {
    /** @var User $owner */
    $owner = User::query()->firstOrFail();

    $owningReadOutputs = OwnerContext::withOwner($owner, function (): array {
        $customer = Customer::query()->create([
            'first_name' => 'Null Rate',
            'last_name' => 'Ownership',
            'status' => 'active',
        ]);
        $checkoutEmail = 'null-rate-checkout@example.com';

        $customer->addContactMethod(ContactMethodData::email($checkoutEmail));

        $checkoutEmailRead = null;
        $processor = mock(PaymentProcessorInterface::class);
        $processor->shouldReceive('getIdentifier')->andReturn('null-rate');
        $processor->shouldReceive('isAvailable')->once()->andReturnTrue();
        $processor->shouldReceive('createPayment')
            ->once()
            ->withArgs(function (CheckoutSession $session, PaymentRequest $request) use (&$checkoutEmailRead): bool {
                $checkoutEmailRead = $request->customerEmail;

                return true;
            })
            ->andReturn(PaymentResult::pending('null-rate-payment', 'https://gateway.example.test/null-rate'));

        $resolver = mock(PaymentGatewayResolverInterface::class);
        $resolver->shouldReceive('resolve')->with('null-rate')->once()->andReturn($processor);

        $checkoutSession = CheckoutSession::query()->create([
            'cart_id' => 'null-rate-cart',
            'customer_id' => $customer->id,
            'status' => Processing::class,
            'selected_payment_gateway' => 'null-rate',
            'grand_total' => 1000,
            'currency' => 'MYR',
        ]);

        (new ProcessPaymentStep($resolver))->handle($checkoutSession);

        $stripeEmail = 'null-rate-stripe@example.com';
        $stripeCustomer = StripeApiCustomer::constructFrom([
            'id' => 'cus_null_rate',
            'email' => $stripeEmail,
        ]);
        $stripeEmailRead = (new StripeCustomer($stripeCustomer))->email();

        $event = Event::factory()->create([
            'title' => 'Null-rate event',
            'slug' => 'null-rate-event',
            'delivery_mode' => 'in_person',
        ]);
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'timezone' => 'UTC',
            'delivery_mode' => 'in_person',
        ]);
        $registration = EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'registration_type' => 'individual',
            'status' => 'confirmed',
            'total_participants' => 1,
            'currency' => 'MYR',
        ]);
        $participant = $registration->participants()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'name' => 'Null-rate participant',
            'is_primary' => true,
        ]);
        $eventEmail = 'null-rate-event@example.com';
        $participant->addContactMethod(ContactMethodData::email($eventEmail));

        $registrationItem = EventRegistrationItem::query()->create([
            'event_registration_id' => $registration->id,
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'ticket_type_id' => (string) Str::uuid(),
            'quantity' => 1,
            'unit_price' => 1000,
            'total_price' => 1000,
            'currency' => 'MYR',
            'status' => 'confirmed',
        ]);
        $eventPayload = app(DefaultEventOrderItemFulfillmentResolver::class)->resolve($registrationItem);

        return [
            'checkout.process_payment.customer_email' => $checkoutEmailRead,
            'cashier.stripe_customer.email' => $stripeEmailRead,
            'events.fulfillment.participant_email' => data_get($eventPayload, 'participants.0.email'),
        ];
    });

    $denominator = count($owningReadOutputs);
    $degradedReads = array_filter(
        $owningReadOutputs,
        static fn (mixed $output): bool => ! is_string($output) || mb_trim($output) === '',
    );
    $nullOrDegradedRate = count($degradedReads) / $denominator;
    $onePercentNullOrDegradedRateThreshold = 0.01;

    expect($denominator)->toBe(3)
        ->and($nullOrDegradedRate)->toBeLessThan($onePercentNullOrDegradedRateThreshold);
});
