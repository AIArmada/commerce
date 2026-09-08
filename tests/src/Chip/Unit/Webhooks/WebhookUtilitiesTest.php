<?php

declare(strict_types=1);

use AIArmada\Chip\Webhooks\ChipWebhookProfile;
use AIArmada\Chip\Webhooks\WebhookValidator;
use Illuminate\Http\Request;

describe('ChipWebhookProfile', function (): void {
    it('can be instantiated', function (): void {
        $profile = new ChipWebhookProfile;
        expect($profile)->toBeInstanceOf(ChipWebhookProfile::class);
    });

    it('returns false when event_type is missing', function (): void {
        $profile = new ChipWebhookProfile;
        $request = Request::create('/webhook', 'POST', []);

        expect($profile->shouldProcess($request))->toBeFalse();
    });

    it('returns false for empty event_type', function (): void {
        $profile = new ChipWebhookProfile;
        $request = Request::create('/webhook', 'POST', ['event_type' => '']);

        expect($profile->shouldProcess($request))->toBeFalse();
    });

    it('returns true for purchase events', function (): void {
        $profile = new ChipWebhookProfile;

        $events = [
            'purchase.created',
            'purchase.paid',
            'purchase.cancelled',
            'purchase.payment_failure',
        ];

        foreach ($events as $event) {
            $request = Request::create('/webhook', 'POST', ['event_type' => $event]);
            expect($profile->shouldProcess($request))->toBeTrue("Failed for {$event}");
        }
    });

    it('returns true for payment events', function (): void {
        $profile = new ChipWebhookProfile;
        $request = Request::create('/webhook', 'POST', ['event_type' => 'payment.refunded']);

        expect($profile->shouldProcess($request))->toBeTrue();
    });

    it('returns true for payout events', function (): void {
        $profile = new ChipWebhookProfile;

        $events = [
            'payout.pending',
            'payout.success',
            'payout.failed',
        ];

        foreach ($events as $event) {
            $request = Request::create('/webhook', 'POST', ['event_type' => $event]);
            expect($profile->shouldProcess($request))->toBeTrue("Failed for {$event}");
        }
    });

    it('returns false for unsupported resource events', function (): void {
        $profile = new ChipWebhookProfile;
        $request = Request::create('/webhook', 'POST', [
            'event_type' => 'unknown.event',
        ]);

        expect($profile->shouldProcess($request))->toBeFalse();
    });

    it('returns false for unknown event types', function (): void {
        $profile = new ChipWebhookProfile;
        $request = Request::create('/webhook', 'POST', ['event_type' => 'unknown.event']);

        expect($profile->shouldProcess($request))->toBeFalse();
    });
});

describe('WebhookValidator', function (): void {
    afterEach(function (): void {
        // Ensure OpenSSL resources are freed
        if (isset($this->keyPair)) {
            openssl_free_key($this->keyPair);
        }
    });

    it('can be instantiated', function (): void {
        $validator = new WebhookValidator;
        expect($validator)->toBeInstanceOf(WebhookValidator::class);
    });

    it('returns false when signature header is missing', function (): void {
        $this->keyPair = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        expect($this->keyPair)->not->toBeFalse();

        openssl_pkey_export($this->keyPair, $privateKey);
        $publicKeyDetails = openssl_pkey_get_details($this->keyPair);

        expect($publicKeyDetails)->toBeArray()
            ->and($publicKeyDetails)->toHaveKey('key');

        config([
            'chip.webhooks.verify_signature' => true,
            'chip.collect.public_key' => $publicKeyDetails['key'],
            'chip.webhooks.collect.webhook_keys' => [$publicKeyDetails['key']],
        ]);

        $validator = new WebhookValidator;
        $request = Request::create('/webhook', 'POST', [], [], [], [], '{"test":"data"}');

        expect($validator->validate($request))->toBeFalse();
    });

    it('returns false when company public key is not configured', function (): void {
        config([
            'chip.webhooks.verify_signature' => true,
            'chip.collect.public_key' => null,
        ]);

        $validator = new WebhookValidator;
        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE' => 'some-signature',
        ], '{"test":"data"}');

        expect($validator->validate($request))->toBeFalse();
    });

    it('returns false for invalid signature', function (): void {
        $this->keyPair = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        expect($this->keyPair)->not->toBeFalse();

        $publicKeyDetails = openssl_pkey_get_details($this->keyPair);
        expect($publicKeyDetails)->toBeArray()
            ->and($publicKeyDetails)->toHaveKey('key');

        config([
            'chip.webhooks.verify_signature' => true,
            'chip.collect.public_key' => $publicKeyDetails['key'],
            'chip.webhooks.collect.webhook_keys' => [$publicKeyDetails['key']],
        ]);

        $validator = new WebhookValidator;
        $payload = '{"test":"data"}';
        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE' => base64_encode('definitely-not-a-real-signature'),
        ], $payload);

        expect($validator->validate($request))->toBeFalse();
    });

    it('returns true for valid signature', function (): void {
        $this->keyPair = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        expect($this->keyPair)->not->toBeFalse();

        openssl_pkey_export($this->keyPair, $privateKey);
        $publicKeyDetails = openssl_pkey_get_details($this->keyPair);

        expect($publicKeyDetails)->toBeArray()
            ->and($publicKeyDetails)->toHaveKey('key');

        $payload = '{"test":"data"}';
        $signature = '';
        $signed = openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        expect($signed)->toBeTrue();

        config([
            'chip.webhooks.verify_signature' => true,
            'chip.collect.public_key' => $publicKeyDetails['key'],
            'chip.webhooks.collect.webhook_keys' => [$publicKeyDetails['key']],
        ]);

        $validator = new WebhookValidator;
        $request = Request::create('/webhook', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE' => base64_encode($signature),
        ], $payload);

        expect($validator->validate($request))->toBeTrue();
    });
});
