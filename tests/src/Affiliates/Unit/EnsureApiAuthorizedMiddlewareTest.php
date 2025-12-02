<?php

declare(strict_types=1);

use AIArmada\Affiliates\Support\Middleware\EnsureApiAuthorized;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

beforeEach(function (): void {
    $this->middleware = new EnsureApiAuthorized();
});

test('middleware passes when auth mode is none', function (): void {
    Config::set('affiliates.api.auth', 'none');

    $request = new Request();
    $response = $this->middleware->handle($request, fn ($req) => response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('middleware passes with correct token', function (): void {
    Config::set('affiliates.api.auth', 'token');
    Config::set('affiliates.api.token', 'secret123');

    $request = new Request();
    $request->headers->set('Authorization', 'Bearer secret123');
    $response = $this->middleware->handle($request, fn ($req) => response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('middleware fails with incorrect token', function (): void {
    Config::set('affiliates.api.auth', 'token');
    Config::set('affiliates.api.token', 'secret123');

    $request = new Request();
    $request->headers->set('Authorization', 'Bearer wrong');
    $response = $this->middleware->handle($request, fn ($req) => response('ok'));

    expect($response->getStatusCode())->toBe(401);
    expect($response->getData(true))->toBe(['message' => 'Unauthorized']);
});

test('middleware fails without token', function (): void {
    Config::set('affiliates.api.auth', 'token');
    Config::set('affiliates.api.token', 'secret123');

    $request = new Request();
    $response = $this->middleware->handle($request, fn ($req) => response('ok'));

    expect($response->getStatusCode())->toBe(401);
});
