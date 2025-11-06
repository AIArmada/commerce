<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

it('runs commerce migrations and creates core tables', function (): void {
    expect(Schema::hasTable('carts'))->toBeTrue();
    expect(Schema::hasTable('stock_transactions'))->toBeTrue();
    expect(Schema::hasTable('docs'))->toBeTrue();
})->group('migrations');
