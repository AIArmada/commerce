<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Data\NotificationPayload;
use AIArmada\AffiliateNetwork\Features\Attribution\Actions\RecordLinkCounters;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\ApplyToOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\ApproveApplication;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\RecordNetworkConversion;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Notifications\NetworkConversionNotification;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Communications\CommunicationsServiceProvider;
use AIArmada\Communications\Models\Communication;
use Illuminate\Support\Facades\Queue;
use Livewire\LivewireServiceProvider;

function notificationOffer(string $domain): AffiliateOffer
{
    $site = AffiliateSite::factory()->verified()->create([
        'domain' => $domain,
        'require_settled_for_payout' => true,
    ]);

    return AffiliateOffer::factory()->published()->forSite($site)->create([
        'landing_url' => "https://{$domain}/landing",
        'requires_approval' => true,
        'rate_base_bp' => 1000,
        'rate_fixed_minor' => null,
        'currency' => 'MYR',
    ]);
}

describe('network notifications', function (): void {
    beforeEach(function (): void {
        Queue::fake();

        $this->app->register(LivewireServiceProvider::class);
        $this->app->register(CommunicationsServiceProvider::class);
        $this->loadMigrationsFrom(__DIR__ . '/../../../../packages/communications/database/migrations');
        $this->artisan('migrate', ['--database' => 'testing']);

        config(['communications.features.owner.enabled' => false]);
    });

    test('applying notifies the site owner', function (): void {
        $offer = notificationOffer('notify-apply.example');
        $owner = User::factory()->create();
        $offer->site->forceFill(['owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey()])->save();
        $user = User::factory()->create();

        app(ApplyToOffer::class)->execute((string) $offer->getKey(), (string) $user->getKey());

        expect(Communication::query()->where('purpose', 'affiliate-network.application_submitted')->exists())->toBeTrue();
    });

    test('approving notifies the applicant', function (): void {
        $offer = notificationOffer('notify-approve.example');
        $user = User::factory()->create();

        $application = app(ApplyToOffer::class)->execute((string) $offer->getKey(), (string) $user->getKey());
        app(ApproveApplication::class)->execute($application);

        $communication = Communication::query()->where('purpose', 'affiliate-network.application_approved')->firstOrFail();

        expect($communication->recipients()->where('recipient_id', $user->getKey())->exists())->toBeTrue();
    });

    test('posted conversions notify the creator', function (): void {
        $offer = notificationOffer('notify-conv.example');
        $user = User::factory()->create();
        $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId((string) $user->getKey())->create();

        app(RecordNetworkConversion::class)->execute(
            offerId: (string) $offer->getKey(),
            affiliateId: (string) $user->getKey(),
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'NOTIFY-NET-1',
            linkId: (string) $link->getKey(),
        );

        expect(Communication::query()->where('purpose', 'affiliate-network.conversion_recorded')->exists())->toBeTrue();
    });

    test('counters-only conversions notify nobody', function (): void {
        $offer = notificationOffer('notify-quiet.example');
        $user = User::factory()->create();
        $link = AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId((string) $user->getKey())->create();

        app(RecordLinkCounters::class)->execute((string) $link->getKey(), 10000, 1);

        expect(Communication::query()->exists())->toBeFalse();
    });

    test('notifications stay silent when disabled', function (): void {
        config(['affiliate-network.notifications.enabled' => false]);

        $offer = notificationOffer('notify-off.example');
        $user = User::factory()->create();

        app(ApplyToOffer::class)->execute((string) $offer->getKey(), (string) $user->getKey());

        expect(Communication::query()->exists())->toBeFalse();
    });

    test('conversion notifications pass the payload through to mail', function (): void {
        $notification = new NetworkConversionNotification(new NotificationPayload(
            title: 'New earnings: ¥1,234',
            body: 'One of your links converted and earned ¥1,234.',
        ));

        $mail = $notification->toMail(new User);

        expect($mail->subject)->toBe('New earnings: ¥1,234')
            ->and($mail->introLines)->toContain('One of your links converted and earned ¥1,234.');
    });
});
