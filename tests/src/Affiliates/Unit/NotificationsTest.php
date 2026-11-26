<?php

declare(strict_types=1);

use AIArmada\Affiliates\Data\AffiliateConversionData;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\Affiliates\Events\AffiliateConversionRecorded;
use AIArmada\Affiliates\Features\Attribution\Api\CartAttribution;
use AIArmada\Affiliates\Features\Conversions\Actions\RecordAffiliateConversion;
use AIArmada\Affiliates\Features\Identity\Notifications\ConversionRecordedNotification;
use AIArmada\Affiliates\Features\Programs\Api\ProgramEnrollment;
use AIArmada\Affiliates\Features\Programs\Models\AffiliateProgram;
use AIArmada\CommerceSupport\Support\OwnerContext;
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

        expect(Communication::query()->where('purpose', 'affiliates.program_joined')->exists())->toBeFalse();

        OwnerContext::withOwner(null, function () use ($affiliate, $program): void {
            $communication = Communication::query()->where('purpose', 'affiliates.program_joined')->firstOrFail();

            expect($communication->owner_type)->toBeNull()
                ->and($communication->owner_id)->toBeNull()
                ->and($communication->idempotency_key)->toStartWith('program-joined:')
                ->and($communication->subject_id)->toBe($program->id)
                ->and($communication->subject_type)->toBe($program->getMorphClass())
                ->and($communication->recipients()->sole()->recipient_id)->toBe($affiliate->id)
                ->and($communication->recipients()->sole()->recipient_type)->toBe($affiliate->getMorphClass());
        });
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

    test('forged conversion events do not send affiliate notifications', function (): void {
        $affiliate = createTestAffiliate(['contact_email' => 'forged-notify-' . uniqid() . '@example.com']);

        event(new AffiliateConversionRecorded(new AffiliateConversionData(
            id: (string) \Illuminate\Support\Str::uuid(),
            affiliateId: $affiliate->id,
            affiliateCode: $affiliate->code,
            commissionMinor: 500000,
            commissionCurrency: 'MYR',
            ownerType: $affiliate->owner_type,
            ownerId: $affiliate->owner_id,
        )));

        expect(Communication::query()->withoutOwnerScope()->where('purpose', 'affiliates.conversion_recorded')->exists())->toBeFalse();
    });

    test('notifications stay silent when disabled', function (): void {
        config(['affiliates.notifications.enabled' => false]);

        $affiliate = createTestAffiliate();
        app(ProgramEnrollment::class)->join($affiliate->id, notificationProgram()->id);

        expect(Communication::query()->withoutOwnerScope()->exists())->toBeFalse();
    });

    test('affiliates route mail to the contact email', function (): void {
        $affiliate = createTestAffiliate(['contact_email' => 'route-' . uniqid() . '@example.com']);

        expect($affiliate->routeNotificationForMail())->toBe($affiliate->contact_email)
            ->and(createTestAffiliate()->routeNotificationForMail())->toBeNull();
    });
});

test('conversion notification uses the currency minor-unit precision', function (): void {
    $notification = new ConversionRecordedNotification('JPY', 1500);

    expect($notification->toMail(null)->subject)->toBe('New commission: JPY 1,500');
});
