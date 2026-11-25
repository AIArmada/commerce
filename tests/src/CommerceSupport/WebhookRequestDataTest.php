<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Data\WebhookRequestData;
use AIArmada\CommerceSupport\EntryPoints\Http\WebhookRequestMapper;
use Illuminate\Http\Request;

it('rejects header values that are not strings', function (): void {
    /** @var array<string, array<int, mixed>> $headers */
    $headers = ['X-Signature' => ['valid', null]];

    expect(fn () => new WebhookRequestData('{}', $headers))
        ->toThrow(InvalidArgumentException::class, 'Invalid webhook header.');
});

it('filters non-string header values coming from an incoming request', function (): void {
    $request = Request::create('/webhooks/owner-dedup', 'POST');

    $headersProperty = new ReflectionProperty($request->headers, 'headers');
    $headersProperty->setAccessible(true);
    $headersProperty->setValue($request->headers, [
        'x-signature' => ['signed-value'],
        'x-nullable' => [null, 'kept'],
    ]);

    $data = WebhookRequestMapper::fromRequest($request);

    expect($data->header('x-signature'))->toBe('signed-value')
        ->and($data->headers)->toHaveKey('x-nullable')
        ->and($data->headers['x-nullable'])->toBe(['kept']);
});
