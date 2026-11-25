<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Attribution\Api\CartAttribution;
use AIArmada\Affiliates\Features\Conversions\Actions\RecordAffiliateConversion;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Features\Programs\Api\ProgramEnrollment;
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Communications\CommunicationsServiceProvider;
use AIArmada\Communications\Models\Communication;
use Illuminate\Support\Facades\Queue;
use Livewire\LivewireServiceProvider;

function notificationProgram(): AffiliateProgram
{
    return AffiliateProgram::create([
        'name' => 'Notify Program',
        'slug' => 'notify-program-' . uniqid(),
        'status' => ProgramStatus::Active,
        'visibility' => ProgramVisibility::Public,
        'requires_approval' => false,
        'commission_type' => CommissionType::Percentage,
    ]);
}

describe('engine notifications', function (): void {
    beforeEach(function (): void {
        Queue::fake();

        $this->app->register(LivewireServiceProvider::class);
        $this->app->register(CommunicationsServiceProvider::class);
        $this->loadMigrationsFrom(__DIR__ . '/../../../../packages/communications/database/migrations');
        $this->artisan('migrate', ['--database' => 'testing']);
    });

    test('joining a program queues a managed notification', function (): void {
        $affiliate = createTestAffiliate(['contact_email' => 'join-notify-' . uniqid() . '@example.com']);
        $program = notificationProgram();

        app(ProgramEnrollment::class)->join($affiliate->id, $program->id);

        $communication = Communication::query()->where('purpose', 'affiliates.program_joined')->firstOrFail();

        expect($communication->idempotency_key)->toStartWith('program-joined:')
            ->and($communication->recipients()->count())->toBe(1);
    });

    test('recorded conversions queue a managed notification', function (): void {
        $affiliate = createTestAffiliate(['contact_email' => 'conv-notify-' . uniqid() . '@example.com']);
        $cart = app('cart')->getCurrentCart();
        app(CartAttribution::class)->attach((string) $affiliate->getKey(), $cart);
        $cart->add('notify-item', 'Order item', 10.00, 1);

        app(RecordAffiliateConversion::class)->record($cart, [
            'external_reference' => 'NOTIFY-1',
        ]);

        expect(Communication::query()->where('purpose', 'affiliates.conversion_recorded')->exists())->toBeTrue();
    });

    test('notifications stay silent when disabled', function (): void {
        config(['affiliates.notifications.enabled' => false]);

        $affiliate = createTestAffiliate();
        app(ProgramEnrollment::class)->join($affiliate->id, notificationProgram()->id);

        expect(Communication::query()->exists())->toBeFalse();
    });

    test('affiliates route mail to the contact email', function (): void {
        $affiliate = createTestAffiliate(['contact_email' => 'route-' . uniqid() . '@example.com']);

        expect($affiliate->routeNotificationForMail())->toBe($affiliate->contact_email)
            ->and(createTestAffiliate()->routeNotificationForMail())->toBeNull();
    });
});
