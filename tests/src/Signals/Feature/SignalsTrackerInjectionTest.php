<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Signals\SignalsTestCase;
use AIArmada\CommerceSupport\Tests\OwnerResolvers\FixedOwnerResolver;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\Signals\Models\TrackedProperty;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

uses(SignalsTestCase::class);

it('renders the explicit signals tracker directive into html responses', function (): void {
    config()->set('signals.integrations.browser.auto_inject', false);

    $owner = User::query()->firstOrFail();
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Signals Browser Property',
        'slug' => 'signals-browser-property',
        'write_key' => 'browser-write-key-0000000000000000000000',
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]);
    $property->assignOwner($owner)->save();

    $path = '/signals-explicit-' . Str::lower(Str::random(8));

    $this->app['router']->middleware('web')->get($path, static function () {
        return response(Blade::render('<!doctype html><html><body>@signalsTracker</body></html>'));
    });

    $response = $this->get($path);

    $response->assertOk()
        ->assertSee('/api/signals/tracker.js', false)
        ->assertSee('data-write-key="browser-write-key-0000000000000000000000"', false)
        ->assertSee('data-anonymous-id="sigv_', false)
        ->assertSee('data-session-id="sigs_', false);
});

it('uses the authenticated model email when auth tracking is enabled', function (): void {
    config()->set('signals.integrations.browser.auto_inject', false);
    config()->set('signals.features.auth_tracking.enabled', true);

    $owner = User::query()->firstOrFail();
    $this->actingAs($owner);

    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Signals Auth Tracking Property',
        'slug' => 'signals-auth-tracking-property',
        'write_key' => 'auth-tracking-write-key-000000000000000',
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]);
    $property->assignOwner($owner)->save();

    $path = '/signals-auth-tracking-' . Str::lower(Str::random(8));

    $this->app['router']->middleware('web')->get($path, static function () {
        return response(Blade::render('<!doctype html><html><body>@signalsTracker</body></html>'));
    });

    $response = $this->get($path);

    $response->assertOk()
        ->assertSee('data-external-id="' . $owner->getAuthIdentifier() . '"', false)
        ->assertSee('data-email="signals-owner@example.com"', false);
});

it('auto injects the tracker into eligible html responses', function (): void {
    config()->set('signals.integrations.browser.auto_inject', true);

    $owner = User::query()->firstOrFail();
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Signals Auto Inject Property',
        'slug' => 'signals-auto-inject-property',
        'write_key' => 'auto-inject-write-key-0000000000000000',
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]);
    $property->assignOwner($owner)->save();

    $path = '/signals-auto-inject-' . Str::lower(Str::random(8));

    $this->app['router']->middleware('web')->get($path, static function () {
        return response('<!doctype html><html><body>Signals auto inject</body></html>');
    });

    $response = $this->get($path);

    $response->assertOk()
        ->assertSee('/api/signals/tracker.js', false)
        ->assertSee('data-write-key="auto-inject-write-key-0000000000000000"', false);
});

it('does not duplicate the tracker when explicit rendering and auto injection are both enabled', function (): void {
    config()->set('signals.integrations.browser.auto_inject', true);

    $owner = User::query()->firstOrFail();
    app()->instance(OwnerResolverInterface::class, new FixedOwnerResolver($owner));

    $property = TrackedProperty::query()->create([
        'name' => 'Signals Dedupe Property',
        'slug' => 'signals-dedupe-property',
        'write_key' => 'dedupe-write-key-0000000000000000000000',
        'type' => 'website',
        'timezone' => 'UTC',
        'currency' => 'MYR',
        'is_active' => true,
    ]);
    $property->assignOwner($owner)->save();

    $path = '/signals-dedupe-' . Str::lower(Str::random(8));

    $this->app['router']->middleware('web')->get($path, static function () {
        return response(Blade::render('<!doctype html><html><body>@signalsTracker</body></html>'));
    });

    $content = $this->get($path)->getContent();

    expect(mb_substr_count($content, 'data-signals-tracker="1"'))->toBe(1)
        ->and(mb_substr_count($content, '/api/signals/tracker.js'))->toBe(1);
});
