<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('adds customer-first indexes to both customer pivots idempotently', function (): void {
    $segmentCustomerTable = config('customers.database.tables.segment_customer', 'customer_segment_customer');
    $groupMembersTable = config('customers.database.tables.group_members', 'customer_group_members');

    expect(Schema::hasIndex($segmentCustomerTable, 'customer_segment_customer_customer_id_index'))->toBeTrue()
        ->and(Schema::hasIndex($groupMembersTable, 'customer_group_members_customer_id_index'))->toBeTrue();

    $migrationPath = dirname(__DIR__, 4)
        . '/packages/customers/database/migrations/2026_09_11_000002_add_customer_first_pivot_indexes.php';
    $migration = require $migrationPath;

    $migration->up();
    $migration->up();

    expect(Schema::hasIndex($segmentCustomerTable, 'customer_segment_customer_customer_id_index'))->toBeTrue()
        ->and(Schema::hasIndex($groupMembersTable, 'customer_group_members_customer_id_index'))->toBeTrue();
});

it('adds owner filter indexes to customers and segments idempotently', function (): void {
    $customersTable = config('customers.database.tables.customers', 'customers');
    $segmentsTable = config('customers.database.tables.segments', 'customer_segments');

    $migrationPath = dirname(__DIR__, 4)
        . '/packages/customers/database/migrations/2026_09_13_000001_add_owner_filter_indexes_to_customer_tables.php';
    $migration = require $migrationPath;

    $migration->up();
    $migration->up();

    expect(Schema::hasIndex($customersTable, 'customers_owner_status_index'))->toBeTrue()
        ->and(Schema::hasIndex($customersTable, ['owner_type', 'owner_id', 'status']))->toBeTrue()
        ->and(Schema::hasIndex($segmentsTable, 'customer_segments_owner_is_active_index'))->toBeTrue()
        ->and(Schema::hasIndex($segmentsTable, ['owner_type', 'owner_id', 'is_active']))->toBeTrue();
});
