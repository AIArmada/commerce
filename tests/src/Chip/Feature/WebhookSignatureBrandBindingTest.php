<?php

declare(strict_types=1);

use AIArmada\Chip\Exceptions\WebhookVerificationException;
use AIArmada\Chip\Features\WebhookDelivery\Api\PublicKeyCache;
use AIArmada\Chip\Services\WebhookService;
use AIArmada\Chip\Webhooks\ChipSpatieSignatureValidator;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;

final class CountingChipWebhookService extends WebhookService
{
    public int $calls = 0;

    public function verifySignature(Request | string $payloadOrRequest, ?string $signature = null, ?string $publicKey = null): bool
    {
        $this->calls++;

        return parent::verifySignature($payloadOrRequest, $signature, $publicKey);
    }
}

/**
 * @return array{private: OpenSSLAsymmetricKey, public: string}
 */
function chipWebhookKeyPair(): array
{
    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    if ($key === false) {
        throw new RuntimeException('Unable to generate webhook test key.');
    }

    $details = openssl_pkey_get_details($key);
    $public = $details['key'] ?? null;

    if (! is_string($public) || $public === '') {
        throw new RuntimeException('Unable to export webhook test public key.');
    }

    return ['private' => $key, 'public' => $public];
}

function chipWebhookRequest(string $body, OpenSSLAsymmetricKey $privateKey): Request
{
    openssl_sign($body, $signature, $privateKey, OPENSSL_ALGO_SHA256);

    return Request::create(
        '/chip/webhooks',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X_Signature' => base64_encode($signature)],
        $body,
    );
}

it('binds collect webhook verification to the brand key in owner mode', function (): void {
    $brandA = chipWebhookKeyPair();
    $brandB = chipWebhookKeyPair();

    config()->set('chip.owner.enabled', true);
    config()->set('chip.webhooks.verify_signature', true);
    config()->set('chip.webhooks.collect.brand_keys', [
        'brand-a' => $brandA['public'],
        'brand-b' => $brandB['public'],
    ]);

    $body = json_encode(['brand_id' => 'brand-a', 'event_type' => 'purchase.paid'], JSON_THROW_ON_ERROR);

    expect(app(WebhookService::class)->verifySignature(chipWebhookRequest($body, $brandA['private'])))->toBeTrue();

    $wrongBrandBody = json_encode(['brand_id' => 'brand-b', 'event_type' => 'purchase.paid'], JSON_THROW_ON_ERROR);

    expect(app(WebhookService::class)->verifySignature(chipWebhookRequest($wrongBrandBody, $brandA['private'])))->toBeFalse();
});

it('fails closed for an unknown brand when brand-bound verification is configured', function (): void {
    $brandA = chipWebhookKeyPair();

    config()->set('chip.owner.enabled', true);
    config()->set('chip.webhooks.verify_signature', true);
    config()->set('chip.webhooks.collect.brand_keys', ['brand-a' => $brandA['public']]);

    $body = json_encode(['brand_id' => 'brand-unknown', 'event_type' => 'purchase.paid'], JSON_THROW_ON_ERROR);

    expect(fn () => app(WebhookService::class)->verifySignature(chipWebhookRequest($body, $brandA['private'])))
        ->toThrow(WebhookVerificationException::class, 'public key is configured for the received brand');

    $validator = new ChipSpatieSignatureValidator;
    $config = Mockery::mock(WebhookConfig::class);

    expect($validator->isValid(chipWebhookRequest($body, $brandA['private']), $config))->toBeFalse();
});

it('validates a request body only once when the validator runs again', function (): void {
    $brandA = chipWebhookKeyPair();

    config()->set('chip.owner.enabled', true);
    config()->set('chip.webhooks.verify_signature', true);
    config()->set('chip.webhooks.collect.brand_keys', ['brand-a' => $brandA['public']]);

    $service = new CountingChipWebhookService;

    app()->instance(WebhookService::class, $service);

    $validator = new ChipSpatieSignatureValidator;
    $config = Mockery::mock(WebhookConfig::class);

    $body = json_encode(['brand_id' => 'brand-a', 'event_type' => 'purchase.paid'], JSON_THROW_ON_ERROR);
    $request = chipWebhookRequest($body, $brandA['private']);

    expect($validator->isValid($request, $config))->toBeTrue()
        ->and($validator->isValid($request, $config))->toBeTrue()
        ->and($service->calls)->toBe(1);
});

final class RecordingPublicKeyCache implements PublicKeyCache
{
    /** @var array<int, string> */
    public array $keys = [];

    public function remember(string $cacheKey, Closure $fetch): string
    {
        $this->keys[] = $cacheKey;

        return $fetch();
    }
}

it('scopes dynamic public key cache keys to the owner and credential context', function (): void {
    $cache = new RecordingPublicKeyCache;
    app()->instance(PublicKeyCache::class, $cache);

    config()->set('chip.owner.enabled', true);
    config()->set('chip.collect.public_key', 'company-public-key');

    $ownerA = new class extends Model
    {
        public function getMorphClass(): string
        {
            return 'team';
        }

        public function getKey(): string
        {
            return 'team-a';
        }
    };

    $ownerB = new class extends Model
    {
        public function getMorphClass(): string
        {
            return 'team';
        }

        public function getKey(): string
        {
            return 'team-b';
        }
    };

    $service = app(WebhookService::class);

    OwnerContext::withOwner($ownerA, fn () => $service->getPublicKey());
    OwnerContext::withOwner($ownerB, fn () => $service->getPublicKey());

    expect($cache->keys)->toHaveCount(2)
        ->and($cache->keys[0])->not->toBe($cache->keys[1]);
});
