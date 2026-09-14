<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\NullOwnerResolver;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Jnt\Console\Commands\Orders\OrderPrintCommand;
use AIArmada\Jnt\Console\Commands\Webhooks\WebhookTestCommand;
use AIArmada\Jnt\Data\OrderData;
use AIArmada\Jnt\Data\TrackingData;
use AIArmada\Jnt\Data\TrackingDetailData;
use AIArmada\Jnt\Enums\TrackingStatus;
use AIArmada\Jnt\Events\JntOrderStatusChanged;
use AIArmada\Jnt\Exceptions\JntValidationException;
use AIArmada\Jnt\Http\Controllers\AwbController;
use AIArmada\Jnt\JntServiceProvider;
use AIArmada\Jnt\Listeners\SendShipmentNotifications;
use AIArmada\Jnt\Models\JntOrder;
use AIArmada\Jnt\Models\JntOrderItem;
use AIArmada\Jnt\Models\JntTrackingEvent;
use AIArmada\Jnt\Models\JntWebhookLog;
use AIArmada\Jnt\Notifications\OrderDeliveredNotification;
use AIArmada\Jnt\Services\JntExpressService;
use AIArmada\Jnt\Services\JntStatusMapper;
use AIArmada\Jnt\Services\JntTrackingService;
use AIArmada\Jnt\Services\WebhookService;
use AIArmada\Jnt\Shipping\JntShippingDriver;
use AIArmada\Jnt\Support\TrackingEventHash;
use AIArmada\Jnt\Webhooks\JntSpatieSignatureValidator;
use AIArmada\Jnt\Webhooks\JntWebhookProfile;
use AIArmada\Jnt\Webhooks\JntWebhookResponse;
use AIArmada\Jnt\Webhooks\ProcessJntWebhook;
use AIArmada\Shipping\Data\AddressData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\WebhookConfig;

describe('carrier safety guards', function (): void {
    it('maps returned descriptions to Returned instead of ReturnInitiated', function (): void {
        $mapper = new JntStatusMapper;

        expect($mapper->fromString('Parcel returned to sender'))->toBe(TrackingStatus::Returned)
            ->and($mapper->fromString('RETURN COMPLETED'))->toBe(TrackingStatus::Returned)
            ->and($mapper->fromString('Return to sender initiated'))->toBe(TrackingStatus::ReturnInitiated)
            ->and($mapper->fromString('Returning to facility'))->toBe(TrackingStatus::ReturnInitiated)
            ->and($mapper->resolve(statusDescription: 'Parcel returned'))->toBe(TrackingStatus::Returned);
    });

    it('rejects carrier payloads with missing required keys', function (): void {
        expect(fn () => OrderData::fromApiArray(['billCode' => 'X']))->toThrow(JntValidationException::class);
        expect(fn () => TrackingData::fromApiArray(['details' => []]))->toThrow(JntValidationException::class);
        expect(fn () => TrackingDetailData::fromApiArray(['scanTime' => 'now']))->toThrow(JntValidationException::class);

        $detail = TrackingDetailData::fromApiArray([
            'scanTime' => '2024-01-15 10:30:00',
            'desc' => 'Collected',
            'scanTypeCode' => '301',
            'scanTypeName' => 'Collection',
            'scanType' => 'PICKUP',
        ]);

        expect($detail->scanTypeCode)->toBe('301');
    });

    it('preserves duplicate identifiers across batch chunks', function (): void {
        config(['concurrency.default' => 'sync']);
        config(['jnt.batch.concurrency_chunk_size' => 1]);

        Http::fake([
            '*/api/logistics/trace' => Http::sequence()
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'DUP-1', 'billCode' => 'TN-DUP', 'details' => []]])
                ->push(['code' => '1', 'msg' => 'success', 'data' => ['txlogisticId' => 'DUP-1', 'billCode' => 'TN-DUP', 'details' => []]]),
        ]);

        $service = new JntExpressService(
            customerCode: 'TEST123',
            password: 'password',
            config: [
                'environment' => 'testing',
                'base_urls' => ['testing' => 'https://demo.api.test'],
                'api_account' => 'acct',
                'private_key' => 'key',
                'tracking' => ['polling_debounce_minutes' => 0],
            ],
        );
        app()->instance(JntExpressService::class, $service);

        $result = $service->batchTrackParcels(orderIds: ['DUP-1', 'DUP-1']);

        expect($result['successful'])->toHaveCount(2);
        Http::assertSentCount(2);
    });

    it('rejects inherited owners outside the ambient context', function (): void {
        config()->set('jnt.owner.enabled', true);
        config()->set('jnt.owner.auto_assign_on_create', false);

        $ownerA = User::query()->create(['name' => 'Inherit A', 'email' => 'inherit-a@example.test', 'password' => 'secret']);
        $ownerB = User::query()->create(['name' => 'Inherit B', 'email' => 'inherit-b@example.test', 'password' => 'secret']);

        $orderB = OwnerContext::withOwner($ownerB, fn () => JntOrder::query()->create([
            'order_id' => 'ORD-INHERIT-B',
            'customer_code' => 'CUST',
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
        ]));

        expect(fn () => OwnerContext::withOwner($ownerA, fn () => JntOrderItem::query()->create([
            'order_id' => $orderB->id,
            'name' => 'Widget',
            'quantity' => 1,
            'weight_grams' => 100,
            'unit_price_minor' => 1000,
            'currency' => 'MYR',
        ])))->toThrow(InvalidArgumentException::class);
    });

    it('blocks preset owner spoofing against the resolved context', function (): void {
        config()->set('jnt.owner.enabled', true);
        config()->set('jnt.owner.auto_assign_on_create', true);

        $ownerA = User::query()->create(['name' => 'Spoof A', 'email' => 'spoof-a@example.test', 'password' => 'secret']);
        $ownerB = User::query()->create(['name' => 'Spoof B', 'email' => 'spoof-b@example.test', 'password' => 'secret']);

        expect(fn () => OwnerContext::withOwner($ownerA, fn () => JntOrder::query()->create([
            'order_id' => 'ORD-SPOOF-1',
            'customer_code' => 'CUST',
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
        ])))->toThrow(AuthorizationException::class);
    });

    it('fails signature bypass closed in production', function (): void {
        config()->set('jnt.webhooks.verify_signature', false);
        config()->set('jnt.private_key', 'secret');
        app()->detectEnvironment(static fn (): string => 'production');

        try {
            $validator = new JntSpatieSignatureValidator;
            $request = Request::create('/webhooks/jnt/status', 'POST', ['bizContent' => '{}']);
            $request->headers->set('digest', 'anything');
            $config = new WebhookConfig([
                'name' => 'jnt.webhooks.status',
                'signing_secret' => 'secret',
                'signature_header_name' => 'digest',
                'signature_validator' => JntSpatieSignatureValidator::class,
                'webhook_profile' => JntWebhookProfile::class,
                'webhook_response' => JntWebhookResponse::class,
                'webhook_model' => WebhookCall::class,
                'store_headers' => ['digest'],
                'process_webhook_job' => ProcessJntWebhook::class,
            ]);

            expect($validator->isValid($request, $config))->toBeFalse();
        } finally {
            app()->detectEnvironment(static fn (): string => 'testing');
        }
    });

    it('resolves the webhook log table from configuration', function (): void {
        expect((new JntWebhookLog)->getTable())->toBe('webhook_calls');

        config(['jnt.database.tables.webhook_calls' => 'custom_webhook_calls']);

        expect((new JntWebhookLog)->getTable())->toBe('custom_webhook_calls');
    });

    it('deletes orders with their children atomically', function (): void {
        config()->set('jnt.owner.enabled', false);

        $order = JntOrder::query()->create([
            'order_id' => 'ORD-CASCADE-1',
            'tracking_number' => 'JNTCASCADE1',
            'customer_code' => 'CUST',
        ]);
        JntOrderItem::query()->create([
            'order_id' => $order->id,
            'name' => 'Widget',
            'quantity' => 1,
            'weight_grams' => 100,
            'unit_price_minor' => 1000,
            'currency' => 'MYR',
        ]);
        JntTrackingEvent::query()->create([
            'order_id' => $order->id,
            'tracking_number' => 'JNTCASCADE1',
            'scan_type_code' => '301',
        ]);

        $order->delete();

        expect(JntOrder::query()->whereKey($order->id)->exists())->toBeFalse()
            ->and(JntOrderItem::query()->where('order_id', $order->id)->exists())->toBeFalse()
            ->and(JntTrackingEvent::query()->where('order_id', $order->id)->exists())->toBeFalse();
    });

    it('loads the latest tracking event without per-order queries', function (): void {
        config()->set('jnt.owner.enabled', false);

        $order = JntOrder::query()->create([
            'order_id' => 'ORD-LATEST-1',
            'tracking_number' => 'JNTLATEST1',
            'customer_code' => 'CUST',
        ]);

        foreach (['2024-01-15 09:00:00', '2024-01-16 12:00:00'] as $index => $time) {
            JntTrackingEvent::query()->create([
                'order_id' => $order->id,
                'tracking_number' => 'JNTLATEST1',
                'scan_type_code' => $index === 0 ? '301' : '602',
                'scan_time' => $time,
            ]);
        }

        $loaded = JntOrder::query()->with('latestTrackingEventRelation')->findOrFail($order->id);

        DB::enableQueryLog();
        $latest = $loaded->latestTrackingEvent();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($latest?->scan_type_code)->toBe('602')->and($queries)->toBeEmpty();
    });

    it('sanitizes waybill output paths and filenames', function (): void {
        expect(OrderPrintCommand::safeFilename('../../etc/passwd'))->toBe('passwd.pdf');
        expect(OrderPrintCommand::safeFilename("o'hara\"\r\nX"))->toBe('o_hara_X.pdf');
        expect(OrderPrintCommand::safeDirectory('storage/waybills'))->toBe(base_path('storage/waybills'));
        expect(OrderPrintCommand::safeDirectory('../outside'))->toBeNull();
        expect(OrderPrintCommand::safeDirectory('/absolute/path'))->toBeNull();
        expect(OrderPrintCommand::safeDirectory('a/../../b'))->toBeNull();
    });

    it('sanitizes download filenames against header injection', function (): void {
        expect(AwbController::safeFilename('ORD-1', 'pdf'))->toBe('jnt_awb_ORD-1.pdf');
        expect(AwbController::safeFilename("ORD-1\"\r\nX-Inner: evil", 'pdf'))->toBe('jnt_awb_ORD-1_X-Inner_evil.pdf');
    });

    it('refuses metadata and link-local webhook test targets', function (): void {
        expect(WebhookTestCommand::isSafeTestUrl('https://example.com/webhooks/jnt/status'))->toBeTrue();
        expect(WebhookTestCommand::isSafeTestUrl('http://localhost:8000/webhooks/jnt/status'))->toBeTrue();
        expect(WebhookTestCommand::isSafeTestUrl('http://169.254.169.254/latest/meta-data'))->toBeFalse();
        expect(WebhookTestCommand::isSafeTestUrl('http://169.254.10.20/x'))->toBeFalse();
        expect(WebhookTestCommand::isSafeTestUrl('http://metadata.google.internal/x'))->toBeFalse();
        expect(WebhookTestCommand::isSafeTestUrl('ftp://example.com/x'))->toBeFalse();
        expect(WebhookTestCommand::isSafeTestUrl('https://user:pass@example.com/x'))->toBeFalse();
    });

    it('skips notifications for malformed stored email', function (): void {
        config()->set('jnt.owner.enabled', false);

        $trackingService = Mockery::mock(JntTrackingService::class);
        $listener = new SendShipmentNotifications($trackingService);
        $method = new ReflectionMethod($listener, 'resolveNotifiable');

        $arrayEmail = new JntOrder;
        $arrayEmail->forceFill(['metadata' => ['notification_email' => ['not-a-string']]]);

        $invalidEmail = new JntOrder;
        $invalidEmail->forceFill(['metadata' => ['notification_email' => 'not-an-email']]);

        $validEmail = new JntOrder;
        $validEmail->forceFill(['metadata' => ['notification_email' => 'ops@example.com']]);

        $resolved = $method->invoke($listener, $validEmail);

        expect($method->invoke($listener, $arrayEmail))->toBeNull()
            ->and($method->invoke($listener, $invalidEmail))->toBeNull()
            ->and($resolved)->not->toBeNull()
            ->and(unserialize(serialize($resolved)))->toBeInstanceOf($resolved::class);

        Mockery::close();
    });

    it('parses single-object webhook bodies as one tracking record', function (): void {
        $service = new WebhookService('secret');

        $records = $service->parseBizContent([
            'bizContent' => json_encode([
                'billCode' => 'JNTSINGLE1',
                'txlogisticId' => 'ORDER-SINGLE-1',
                'details' => [],
            ]),
        ]);

        expect($records)->toHaveCount(1)
            ->and($records[0]->trackingNumber)->toBe('JNTSINGLE1');

        expect(fn () => $service->parseBizContent(['bizContent' => json_encode(['nope', 'list'])]))
            ->toThrow(JntValidationException::class);
    });

    it('classifies only five-digit postcodes into region pricing', function (): void {
        $driver = new JntShippingDriver(
            Mockery::mock(JntExpressService::class),
            Mockery::mock(JntTrackingService::class),
            Mockery::mock(JntStatusMapper::class),
        );

        $multiplier = new ReflectionMethod($driver, 'getRegionMultiplierBasisPoints');
        $days = new ReflectionMethod($driver, 'getEstimatedDays');

        $address = static fn (string $postcode): AddressData => new AddressData(
            name: 'Test',
            phone: '+60123456789',
            line1: '123 Test St',
            postcode: $postcode,
            country: 'MYS',
        );

        expect($multiplier->invoke($driver, $address('88000')))->toBe(15000)
            ->and($multiplier->invoke($driver, $address('50000')))->toBe(10000)
            ->and($multiplier->invoke($driver, $address('8ABCD')))->toBe(10000)
            ->and($multiplier->invoke($driver, $address('880000')))->toBe(10000)
            ->and($days->invoke($driver, $address('8ABCD')))->toBe((int) config('jnt.shipping.default_estimated_days', 3));

        Mockery::close();
    });

    it('resolves credential-bearing services fresh every time', function (): void {
        expect(app(JntExpressService::class))->not->toBe(app(JntExpressService::class));
        expect(app(WebhookService::class))->not->toBe(app(WebhookService::class));
        expect(app(JntTrackingService::class))->not->toBe(app(JntTrackingService::class));
    });

    it('fails fast when owner mode has no real resolver', function (): void {
        config()->set('jnt.owner.enabled', true);
        app()->instance(OwnerResolverInterface::class, new NullOwnerResolver);

        $provider = new JntServiceProvider(app());
        $method = new ReflectionMethod($provider, 'assertOwnerResolverConfigured');

        expect(fn () => $method->invoke($provider))->toThrow(RuntimeException::class);
    });

    it('skips tracking details with unparseable scan times', function (): void {
        $service = new JntTrackingService(
            Mockery::mock(JntExpressService::class),
            new JntStatusMapper,
        );

        $tracking = TrackingData::make('JNTBADSCAN1', [
            new TrackingDetailData(
                scanTime: 'garbage-time',
                description: 'Broken',
                scanTypeCode: '20',
                scanTypeName: 'Transit',
                scanType: 'dispatch',
            ),
            new TrackingDetailData(
                scanTime: '2024-01-16 12:00:00',
                description: 'Parcel signed by recipient',
                scanTypeCode: '602',
                scanTypeName: 'Delivered',
                scanType: 'POD',
            ),
        ], 'ORDER-BADSCAN-1');

        $parsed = $service->parseTrackingData($tracking);

        expect($parsed['events'])->toHaveCount(1)
            ->and($parsed['current_status'])->toBe(TrackingStatus::Delivered);

        Mockery::close();
    });

    it('computes a stable canonical hash per event identity', function (): void {
        $first = TrackingEventHash::forIdentity('order-1', 'JNT1', '602', '2024-01-16 12:00:00', 'Delivered', 'User', 'user-1');
        $second = TrackingEventHash::forIdentity('order-1', 'JNT1', '602', '2024-01-16 12:00:00', 'Delivered', 'User', 'user-1');
        $otherOwner = TrackingEventHash::forIdentity('order-1', 'JNT1', '602', '2024-01-16 12:00:00', 'Delivered', 'User', 'user-2');

        expect($first)->toBe($second)->and($first)->not->toBe($otherOwner);
    });

    it('delivers status change notifications to the resolved owner', function (): void {
        config()->set('jnt.owner.enabled', false);
        config()->set('jnt.notifications.enabled', true);

        Notification::fake();

        $order = JntOrder::query()->create([
            'order_id' => 'ORD-NOTIFY-1',
            'tracking_number' => 'JNTNOTIFY1',
            'customer_code' => 'CUST',
            'metadata' => ['notification_email' => 'ops-notify@example.com'],
        ]);

        $trackingService = Mockery::mock(JntTrackingService::class);
        $trackingService->shouldReceive('track')->andReturn([
            'tracking_number' => 'JNTNOTIFY1',
            'order_id' => 'ORD-NOTIFY-1',
            'current_status' => TrackingStatus::Delivered,
            'events' => [],
        ]);

        $listener = new SendShipmentNotifications($trackingService);
        $listener->handle(JntOrderStatusChanged::fromOrder($order, TrackingStatus::Delivered));

        Notification::assertSentTimes(OrderDeliveredNotification::class, 1);

        Mockery::close();
    });
});
