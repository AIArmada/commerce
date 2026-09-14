<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\FilamentSignals\FilamentSignalsTestCase;
use AIArmada\FilamentSignals\Support\InteractionRuleScanner;
use Illuminate\Support\Facades\Route;

uses(FilamentSignalsTestCase::class);

function scannerBladeDir(string $name): string
{
    $dir = sys_get_temp_dir() . '/signals-scanner-' . $name . '-' . uniqid();

    mkdir($dir, 0777, true);

    return $dir;
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir() . '/signals-scanner-*') ?: [] as $dir) {
        foreach (glob($dir . '/*.blade.php') ?: [] as $file) {
            unlink($file);
        }

        rmdir($dir);
    }
});

it('caps the number of files a local scan touches', function (): void {
    $dir = scannerBladeDir('many');

    for ($i = 0; $i < InteractionRuleScanner::MAX_SCAN_FILES + 100; $i++) {
        file_put_contents(
            $dir . "/view-{$i}.blade.php",
            "<button data-track=\"file-{$i}\">Track</button>\n"
        );
    }

    $candidates = app(InteractionRuleScanner::class)
        ->scanLocalSource('click', null, 100000, [$dir]);

    expect($candidates)->toHaveCount(InteractionRuleScanner::MAX_SCAN_FILES);
});

it('skips oversized views instead of loading them fully', function (): void {
    $dir = scannerBladeDir('huge');

    file_put_contents(
        $dir . '/huge.blade.php',
        "<button data-track=\"huge-view\">Track</button>\n" . str_repeat('x', InteractionRuleScanner::MAX_SCAN_FILE_BYTES + 1024)
    );
    file_put_contents($dir . '/tiny.blade.php', "<button data-track=\"tiny-view\">Track</button>\n");

    $candidates = app(InteractionRuleScanner::class)->scanLocalSource('click', null, 100, [$dir]);

    $selectors = collect($candidates)->pluck('selector')->all();

    expect($selectors)->toContain('[data-track="tiny-view"]')
        ->not->toContain('[data-track="huge-view"]');
});

it('keeps back-office routes out of the scan datalist', function (): void {
    Route::get('/admin/reports', fn (): string => 'admin')->name('scan-probe.admin');
    Route::get('/api/widgets', fn (): string => 'api')->name('scan-probe.api');
    Route::get('/shop/widgets', fn (): string => 'shop')->name('scan-probe.shop');

    $patterns = app(InteractionRuleScanner::class)->discoverRoutePatterns();

    expect($patterns)->toContain('/shop/widgets')
        ->not->toContain('/admin/reports')
        ->not->toContain('/api/widgets');
});
