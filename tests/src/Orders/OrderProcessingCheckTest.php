<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Health\OrderProcessingCheck;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\PendingPayment;
use Spatie\Health\Checks\Result;
use Spatie\Health\Enums\Status;

describe('OrderProcessingCheck Health Check', function (): void {
    describe('Health Check Configuration', function (): void {
        it('can be instantiated', function (): void {
            $check = new OrderProcessingCheck;
            expect($check)->toBeInstanceOf(OrderProcessingCheck::class);
        });

        it('can configure max pending hours', function (): void {
            $check = new OrderProcessingCheck;
            $result = $check->maxPendingHours(48);

            expect($result)->toBe($check);
            expect(method_exists($check, 'maxPendingHours'))->toBeTrue();
        });

        it('can configure max processing hours', function (): void {
            $check = new OrderProcessingCheck;
            $result = $check->maxProcessingHours(72);

            expect($result)->toBe($check);
            expect(method_exists($check, 'maxProcessingHours'))->toBeTrue();
        });

        it('can configure both max ages', function (): void {
            $check = new OrderProcessingCheck;
            $result = $check->maxAge(36, 60);

            expect($result)->toBe($check);
            expect(method_exists($check, 'maxAge'))->toBeTrue();
        });
    });

    describe('Health Check Execution', function (): void {
        it('can run health check', function (): void {
            $check = new OrderProcessingCheck;
            $result = $check->run();

            expect($result)->toBeInstanceOf(Result::class);
        });

        it('uses explicit global owner context when owner resolver returns null', function (): void {
            config()->set('orders.owner.enabled', true);
            config()->set('orders.owner.include_global', false);
            config()->set('orders.owner.auto_assign_on_create', false);

            $order = OwnerContext::withOwner(null, fn (): Order => Order::query()->create([
                'owner_type' => null,
                'owner_id' => null,
                'status' => PendingPayment::class,
                'currency' => 'MYR',
                'subtotal' => 10000,
                'grand_total' => 10000,
            ]));

            $order->forceFill([
                'created_at' => now()->subHours(30),
            ])->saveQuietly();

            $check = new OrderProcessingCheck;

            $result = OwnerContext::withOwner(null, fn (): Result => $check->run());

            expect($result->status->equals(Status::warning()))->toBeTrue()
                ->and($result->meta['reason'] ?? null)->not->toBe('owner_context_missing');
        });
    });
});
