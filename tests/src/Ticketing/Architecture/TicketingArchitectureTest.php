<?php

declare(strict_types=1);

arch('ticketing')
    ->expect('AIArmada\Ticketing')
    ->not->toUse('AIArmada\Events');

test('ticketing service provider is concrete', function (): void {
    $provider = new \AIArmada\Ticketing\TicketingServiceProvider(app());
    expect($provider)->toBeInstanceOf(\Spatie\LaravelPackageTools\PackageServiceProvider::class);
});

test('ticketing config is accessible', function (): void {
    expect(config('ticketing.database.tables.passes'))->toBe('ticket_passes');
    expect(config('ticketing.defaults.pass_no_prefix'))->toBe('PASS-');
});
