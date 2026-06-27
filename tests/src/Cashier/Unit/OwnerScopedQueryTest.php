<?php

declare(strict_types=1);

use AIArmada\Cashier\Support\OwnerScopedQuery;
use AIArmada\Commerce\Tests\Cashier\CashierTestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(CashierTestCase::class);

it('caches schema column checks by connection table and column', function (): void {
    $table = 'owner_scoped_query_test_' . bin2hex(random_bytes(4));

    $model = new class extends Model {};
    $model->setTable($table);

    $method = new ReflectionMethod(OwnerScopedQuery::class, 'modelHasColumn');
    $method->setAccessible(true);

    Schema::create($table, function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuid('user_id');
    });

    try {
        DB::connection()->flushQueryLog();
        DB::connection()->enableQueryLog();

        expect($method->invoke(null, $model, 'user_id'))->toBeTrue();

        $queryCountAfterFirstCheck = count(DB::connection()->getQueryLog());

        expect($method->invoke(null, $model, 'user_id'))->toBeTrue()
            ->and(DB::connection()->getQueryLog())->toHaveCount($queryCountAfterFirstCheck);
    } finally {
        Schema::dropIfExists($table);
    }
});
