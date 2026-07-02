<?php

declare(strict_types=1);
use AIArmada\Ticketing\TicketingServiceProvider;
use Spatie\LaravelPackageTools\PackageServiceProvider;

arch('ticketing')
    ->expect('AIArmada\Ticketing')
    ->not->toUse('AIArmada\Events');

test('ticketing service provider is concrete', function (): void {
    $provider = new TicketingServiceProvider(app());
    expect($provider)->toBeInstanceOf(PackageServiceProvider::class);
});

test('ticketing config is accessible', function (): void {
    expect(config('ticketing.database.tables.passes'))->toBe('ticket_passes');
    expect(config('ticketing.defaults.pass_no_prefix'))->toBe('PASS-');
});
