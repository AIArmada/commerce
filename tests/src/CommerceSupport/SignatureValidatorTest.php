<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Webhooks\CommerceSignatureValidator;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProcessor;
use AIArmada\CommerceSupport\Webhooks\CommerceWebhookProfile;
use Illuminate\Http\Request;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\WebhookConfig;

final class SupportBaseSignatureValidator extends CommerceSignatureValidator
{
    protected function getSignatureHeader(): string
    {
        return 'X-Support-Signature';
    }
}

final class SupportTimestampedSignatureValidator extends CommerceSignatureValidator
{
    protected function getSignatureHeader(): string
    {
        return 'X-Support-Signature';
    }

    protected function getTimestampHeader(): ?string
    {
        return 'X-Support-Timestamp';
    }
}

function supportSignatureConfig(): WebhookConfig
{
    return new WebhookConfig([
        'name' => 'support-test',
        'signing_secret' => 'test-secret',
        'signature_header_name' => 'X-Support-Signature',
        'signature_validator' => SupportBaseSignatureValidator::class,
        'webhook_profile' => CommerceWebhookProfile::class,
        'webhook_model' => WebhookCall::class,
        'process_webhook_job' => CommerceWebhookProcessor::class,
    ]);
}

function supportSignedRequest(string $payload, string $signature, ?string $timestamp = null): Request
{
    $request = Request::create('/webhooks/support-test', 'POST', [], [], [], [], $payload);
    $request->headers->set('X-Support-Signature', $signature);

    if ($timestamp !== null) {
        $request->headers->set('X-Support-Timestamp', $timestamp);
    }

    return $request;
}

it('accepts plain and scheme-prefixed signatures', function (): void {
    $payload = '{"event":"ping"}';
    $hex = hash_hmac('sha256', $payload, 'test-secret');

    $validator = new SupportBaseSignatureValidator;

    expect($validator->isValid(supportSignedRequest($payload, $hex), supportSignatureConfig()))->toBeTrue()
        ->and($validator->isValid(supportSignedRequest($payload, 'sha256=' . $hex), supportSignatureConfig()))->toBeTrue()
        ->and($validator->isValid(supportSignedRequest($payload, 'SHA256=' . $hex), supportSignatureConfig()))->toBeTrue()
        ->and($validator->isValid(supportSignedRequest($payload, 'sha256='), supportSignatureConfig()))->toBeFalse()
        ->and($validator->isValid(supportSignedRequest($payload, 'deadbeef'), supportSignatureConfig()))->toBeFalse();
});

it('enforces the timestamp freshness window when opted in', function (): void {
    $payload = '{"event":"ping"}';
    $hex = hash_hmac('sha256', $payload, 'test-secret');

    $validator = new SupportTimestampedSignatureValidator;
    $now = (string) time();

    expect($validator->isValid(supportSignedRequest($payload, $hex, $now), supportSignatureConfig()))->toBeTrue()
        ->and($validator->isValid(supportSignedRequest($payload, $hex), supportSignatureConfig()))->toBeFalse()
        ->and($validator->isValid(supportSignedRequest($payload, $hex, 'not-a-time'), supportSignatureConfig()))->toBeFalse()
        ->and($validator->isValid(supportSignedRequest($payload, $hex, (string) (time() - 3600)), supportSignatureConfig()))->toBeFalse()
        ->and($validator->isValid(supportSignedRequest($payload, $hex, (string) (time() + 3600)), supportSignatureConfig()))->toBeFalse();
});
