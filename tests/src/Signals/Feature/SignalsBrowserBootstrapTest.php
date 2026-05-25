<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Signals\Support\Http\Middleware\BootstrapSignalsBrowserContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

uses(SignalsTestCase::class);

it('issues browser cookies on the first web request', function (): void {
    $request = Request::create('/signals-browser-bootstrap', 'GET');

    $response = app(BootstrapSignalsBrowserContext::class)->handle($request, static function () {
        return response('<!doctype html><html><body>Signals browser bootstrap</body></html>');
    });

    $cookies = collect($response->headers->getCookies())
        ->mapWithKeys(static fn (Cookie $cookie): array => [$cookie->getName() => $cookie])
        ->all();

    expect($response->getStatusCode())->toBe(200)
        ->and($cookies)->toHaveKeys(['sig_vid', 'sig_sid'])
        ->and($cookies['sig_vid']->getValue())->toStartWith('sigv_')
        ->and($cookies['sig_sid']->getValue())->toStartWith('sigs_');
});
