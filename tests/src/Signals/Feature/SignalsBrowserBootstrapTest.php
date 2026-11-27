<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\Signals\Features\Browser\EntryPoints\Http\Middleware\BootstrapSignalsBrowserContext;
use AIArmada\Signals\Support\Http\SignalsBrowserContextManager;
use Illuminate\Contracts\Http\Kernel;
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

it('registers the browser context middleware on the bound http kernel web group', function (): void {
    $kernel = app(Kernel::class);

    expect($kernel->getMiddlewareGroups()['web'] ?? [])
        ->toContain(BootstrapSignalsBrowserContext::class)
        ->and(app('router')->getMiddlewareGroups()['web'] ?? [])
        ->toContain(BootstrapSignalsBrowserContext::class);
});

it('keeps explicit request context isolated from the bound container request', function (): void {
    $boundRequest = Request::create('/bound-request', 'GET');
    app()->instance('request', $boundRequest);

    $suppliedRequest = Request::create('/supplied-request', 'GET');
    $manager = app(SignalsBrowserContextManager::class);

    expect($manager->current($suppliedRequest))->toBeNull();

    $context = $manager->resolveOrCreate($suppliedRequest);

    expect($manager->current($suppliedRequest))->toBe($context)
        ->and($manager->current($boundRequest))->toBeNull()
        ->and($manager->resolveOrCreate($suppliedRequest))->toBe($context)
        ->and($boundRequest->attributes->has(SignalsBrowserContextManager::CONTEXT_ATTRIBUTE))->toBeFalse();
});

it('rejects control bytes in browser cookies without losing valid identifiers', function (): void {
    $invalid = Request::create('/signals-browser-bootstrap', 'GET');
    $invalid->cookies->set('sig_vid', "visitor\r\n");
    $invalid->cookies->set('sig_sid', "session\nid");

    $response = app(BootstrapSignalsBrowserContext::class)->handle($invalid, static function () {
        return response('<!doctype html><html><body>Signals browser bootstrap</body></html>');
    });

    $cookies = collect($response->headers->getCookies())
        ->mapWithKeys(static fn (Cookie $cookie): array => [$cookie->getName() => $cookie])
        ->all();

    expect($cookies['sig_vid']->getValue())->toStartWith('sigv_')
        ->and($cookies['sig_sid']->getValue())->toStartWith('sigs_')
        ->and($cookies['sig_vid']->getValue())->not->toContain("\r")
        ->and($cookies['sig_sid']->getValue())->not->toContain("\n");

    $valid = Request::create('/signals-browser-bootstrap', 'GET');
    $valid->cookies->set('sig_vid', 'visitor-é');
    $valid->cookies->set('sig_sid', 'session-✓');

    $validResponse = app(BootstrapSignalsBrowserContext::class)->handle($valid, static function () {
        return response('<!doctype html><html><body>Signals browser bootstrap</body></html>');
    });

    $validCookies = collect($validResponse->headers->getCookies())
        ->mapWithKeys(static fn (Cookie $cookie): array => [$cookie->getName() => $cookie])
        ->all();

    expect($validCookies['sig_vid']->getValue())->toBe('visitor-é')
        ->and($validCookies['sig_sid']->getValue())->toBe('session-✓');
});
