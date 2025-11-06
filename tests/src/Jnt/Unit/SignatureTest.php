<?php

declare(strict_types=1);

use AIArmada\Jnt\Http\JntClient;

it('generates correct signature digest', function (): void {
    $client = new JntClient(
        baseUrl: 'https://demoopenapi.jtexpress.my/webopenplatformapi',
        apiAccount: '640826271705595946',
        privateKey: '8e88c8477d4e4939859c560192fcafbc',
        config: []
    );

    $bizContent = '{"customerCode":"ITTEST0001","txlogisticId":"TEST123"}';

    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('generateDigest');

    $digest = $method->invoke($client, $bizContent);

    expect($digest)->toBeString()
        ->and(mb_strlen((string) $digest))->toBeGreaterThan(0);
});

it('verifies webhook signature correctly', function (): void {
    $client = new JntClient(
        baseUrl: 'https://demoopenapi.jtexpress.my/webopenplatformapi',
        apiAccount: '640826271705595946',
        privateKey: '8e88c8477d4e4939859c560192fcafbc',
        config: []
    );

    $bizContent = '{"customerCode":"ITTEST0001","txlogisticId":"TEST123"}';

    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('generateDigest');

    $digest = $method->invoke($client, $bizContent);

    expect($client->verifyWebhookSignature($bizContent, $digest))->toBeTrue();
});

it('rejects invalid webhook signature', function (): void {
    $client = new JntClient(
        baseUrl: 'https://demoopenapi.jtexpress.my/webopenplatformapi',
        apiAccount: '640826271705595946',
        privateKey: '8e88c8477d4e4939859c560192fcafbc',
        config: []
    );

    $bizContent = '{"customerCode":"ITTEST0001","txlogisticId":"TEST123"}';
    $invalidDigest = 'invalid_digest_string';

    expect($client->verifyWebhookSignature($bizContent, $invalidDigest))->toBeFalse();
});
