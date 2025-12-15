<?php

declare(strict_types=1);

use AIArmada\Jnt\Webhooks\JntWebhookProfile;
use Illuminate\Http\Request;

describe('JntWebhookProfile', function (): void {
    it('should not process when event is missing', function (): void {
        $request = Request::create('/webhook', 'POST', []);

        $profile = new JntWebhookProfile;

        expect($profile->shouldProcess($request))->toBeFalse();
    });

    it('should not process when event type is empty', function (): void {
        $request = Request::create('/webhook', 'POST', ['event' => '']);

        $profile = new JntWebhookProfile;

        expect($profile->shouldProcess($request))->toBeFalse();
    });

    it('should process shipment events', function (): void {
        $request = Request::create('/webhook', 'POST', ['event' => 'shipment.created']);

        $profile = new JntWebhookProfile;

        expect($profile->shouldProcess($request))->toBeTrue();
    });

    it('should process tracking events', function (): void {
        $request = Request::create('/webhook', 'POST', ['event' => 'tracking.update']);

        $profile = new JntWebhookProfile;

        expect($profile->shouldProcess($request))->toBeTrue();
    });

    it('should process delivery events', function (): void {
        $request = Request::create('/webhook', 'POST', ['event' => 'delivery.completed']);

        $profile = new JntWebhookProfile;

        expect($profile->shouldProcess($request))->toBeTrue();
    });

    it('should process pickup events', function (): void {
        $request = Request::create('/webhook', 'POST', ['event' => 'pickup.scheduled']);

        $profile = new JntWebhookProfile;

        expect($profile->shouldProcess($request))->toBeTrue();
    });

    it('should handle event_type field', function (): void {
        $request = Request::create('/webhook', 'POST', ['event_type' => 'shipment.updated']);

        $profile = new JntWebhookProfile;

        expect($profile->shouldProcess($request))->toBeTrue();
    });

    it('should not process unknown event types', function (): void {
        $request = Request::create('/webhook', 'POST', ['event' => 'unknown.event']);

        $profile = new JntWebhookProfile;

        expect($profile->shouldProcess($request))->toBeFalse();
    });

    it('should not process events without valid prefix', function (): void {
        $request = Request::create('/webhook', 'POST', ['event' => 'order.created']);

        $profile = new JntWebhookProfile;

        expect($profile->shouldProcess($request))->toBeFalse();
    });
});
