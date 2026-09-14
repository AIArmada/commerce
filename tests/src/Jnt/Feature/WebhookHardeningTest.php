<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Models\JntTrackingEvent;
use AIArmada\Jnt\Services\WebhookService;
use AIArmada\Jnt\Webhooks\ProcessJntWebhook;
use Illuminate\Support\Facades\Queue;
use Spatie\WebhookClient\Models\WebhookCall;

describe('webhook payload hardening', function (): void {
    beforeEach(function (): void {
        $this->privateKey = 'hardening-private-key';
        config(['jnt.private_key' => $this->privateKey]);
        config(['jnt.webhooks.max_biz_content_bytes' => 1048576]);
        config(['jnt.webhooks.max_details' => 500]);

        $this->app->singleton(WebhookService::class, fn (): WebhookService => new WebhookService($this->privateKey));
    });

    it('rejects oversize webhook bodies with 422', function (): void {
        Queue::fake();
        config(['jnt.webhooks.max_biz_content_bytes' => 64]);

        $bizContent = json_encode(['billCode' => 'JNTBIG', 'details' => [], 'pad' => str_repeat('x', 128)]);
        $signature = base64_encode(md5($bizContent . $this->privateKey, true));

        $response = $this->postJson('/webhooks/jnt/status', ['bizContent' => $bizContent], ['digest' => $signature]);

        $response->assertStatus(422)->assertJson(['code' => '0']);
        Queue::assertNothingPushed();
    });

    it('rejects excessive detail counts with 422', function (): void {
        Queue::fake();
        config(['jnt.webhooks.max_details' => 2]);

        $details = [];

        for ($i = 0; $i < 3; $i++) {
            $details[] = [
                'scanTime' => '2024-01-15 10:30:00',
                'desc' => 'Event ' . $i,
                'scanTypeCode' => '20',
                'scanTypeName' => 'Transit',
                'scanType' => 'dispatch',
            ];
        }

        $bizContent = json_encode(['billCode' => 'JNTMANY', 'details' => $details]);
        $signature = base64_encode(md5($bizContent . $this->privateKey, true));

        $response = $this->postJson('/webhooks/jnt/status', ['bizContent' => $bizContent], ['digest' => $signature]);

        $response->assertStatus(422)->assertJson(['code' => '0']);
        Queue::assertNothingPushed();
    });

    it('tolerates malformed scan times while syncing the rest', function (): void {
        config()->set('jnt.owner.enabled', false);

        $shipment = JntOrder::query()->create([
            'order_id' => 'ORDER-BADTIME-1',
            'tracking_number' => 'JNTBADTIME1',
            'customer_code' => 'CUST',
        ]);

        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();
        $webhookCall->id = 1001;

        $processor = new ProcessJntWebhook($webhookCall);
        $method = new ReflectionMethod($processor, 'processEvent');

        $method->invoke($processor, 'POD', [
            'bizContent' => json_encode([
                'billCode' => 'JNTBADTIME1',
                'txlogisticId' => 'ORDER-BADTIME-1',
                'details' => [
                    [
                        'scanTime' => 'not-a-date-at-all',
                        'desc' => 'Broken clock event',
                        'scanTypeCode' => '20',
                        'scanTypeName' => 'Transit',
                        'scanType' => 'dispatch',
                    ],
                    [
                        'scanTime' => '2024-01-16 12:00:00',
                        'desc' => 'Parcel signed by recipient',
                        'scanTypeCode' => '602',
                        'scanTypeName' => 'Delivered',
                        'scanType' => 'POD',
                    ],
                ],
            ]),
        ]);

        $events = JntTrackingEvent::query()
            ->withoutOwnerScope()
            ->where('order_id', $shipment->id)
            ->orderBy('scan_time')
            ->get();

        expect($events)->toHaveCount(2)
            ->and($events->firstWhere('scan_type_code', '20')->scan_time)->toBeNull()
            ->and($shipment->refresh()->last_status_code)->toBe('602');

        Mockery::close();
    });

    it('dedupes replayed webhooks through the canonical event hash', function (): void {
        config()->set('jnt.owner.enabled', false);

        $shipment = JntOrder::query()->create([
            'order_id' => 'ORDER-REPLAY-1',
            'tracking_number' => 'JNTREPLAY1',
            'customer_code' => 'CUST',
        ]);

        $payload = [
            'bizContent' => json_encode([
                'billCode' => 'JNTREPLAY1',
                'txlogisticId' => 'ORDER-REPLAY-1',
                'details' => [
                    [
                        'scanTime' => '2024-01-15 09:00:00',
                        'desc' => 'Package collected',
                        'scanTypeCode' => '301',
                        'scanTypeName' => 'Collection',
                        'scanType' => 'PICKUP',
                    ],
                ],
            ]),
        ];

        foreach ([2001, 2002] as $callId) {
            $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();
            $webhookCall->id = $callId;

            $processor = new ProcessJntWebhook($webhookCall);
            $method = new ReflectionMethod($processor, 'processEvent');
            $method->invoke($processor, 'PICKUP', $payload);
        }

        expect(JntTrackingEvent::query()->withoutOwnerScope()->where('order_id', $shipment->id)->count())->toBe(1);

        Mockery::close();
    });

    it('truncates job-level details beyond the configured maximum', function (): void {
        config()->set('jnt.owner.enabled', false);
        config(['jnt.webhooks.max_details' => 1]);

        $shipment = JntOrder::query()->create([
            'order_id' => 'ORDER-TRUNC-1',
            'tracking_number' => 'JNTTRUNC1',
            'customer_code' => 'CUST',
        ]);

        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();
        $webhookCall->id = 3001;

        $processor = new ProcessJntWebhook($webhookCall);
        $method = new ReflectionMethod($processor, 'processEvent');

        $method->invoke($processor, 'POD', [
            'bizContent' => json_encode([
                'billCode' => 'JNTTRUNC1',
                'txlogisticId' => 'ORDER-TRUNC-1',
                'details' => [
                    [
                        'scanTime' => '2024-01-15 09:00:00',
                        'desc' => 'Package collected',
                        'scanTypeCode' => '301',
                        'scanTypeName' => 'Collection',
                        'scanType' => 'PICKUP',
                    ],
                    [
                        'scanTime' => '2024-01-16 12:00:00',
                        'desc' => 'Parcel signed by recipient',
                        'scanTypeCode' => '602',
                        'scanTypeName' => 'Delivered',
                        'scanType' => 'POD',
                    ],
                ],
            ]),
        ]);

        $events = JntTrackingEvent::query()->withoutOwnerScope()->where('order_id', $shipment->id)->get();

        expect($events)->toHaveCount(1)
            ->and($events->first()->scan_type_code)->toBe('602');

        Mockery::close();
    });

    it('syncs webhook events under the shipment owner', function (): void {
        config()->set('jnt.owner.enabled', true);
        config()->set('jnt.owner.include_global', false);
        config()->set('jnt.owner.auto_assign_on_create', true);

        $owner = User::query()->create([
            'name' => 'Hardening Owner',
            'email' => 'hardening-owner@example.test',
            'password' => 'secret',
        ]);

        $shipment = OwnerContext::withOwner($owner, fn () => JntOrder::query()->create([
            'order_id' => 'ORDER-HARDEN-1',
            'tracking_number' => 'JNTHARDEN1',
            'customer_code' => 'CUST',
        ]));

        $webhookCall = Mockery::mock(WebhookCall::class)->makePartial();
        $webhookCall->id = 4001;

        $processor = new ProcessJntWebhook($webhookCall);
        $method = new ReflectionMethod($processor, 'processEvent');

        $method->invoke($processor, 'PICKUP', [
            'bizContent' => json_encode([
                'billCode' => 'JNTHARDEN1',
                'txlogisticId' => 'ORDER-HARDEN-1',
                'details' => [
                    [
                        'scanTime' => '2024-01-15 09:00:00',
                        'desc' => 'Package collected',
                        'scanTypeCode' => '301',
                        'scanTypeName' => 'Collection',
                        'scanType' => 'PICKUP',
                    ],
                ],
            ]),
        ]);

        $stored = JntTrackingEvent::query()->withoutOwnerScope()->where('order_id', $shipment->id)->firstOrFail();

        expect($stored->owner_type)->toBe($owner->getMorphClass())
            ->and((string) $stored->owner_id)->toBe((string) $owner->getKey())
            ->and($stored->event_hash)->not->toBeNull();

        Mockery::close();
    });
});
