<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('ships customer-first indexes in both customer pivot creates', function (): void {
    $segmentCustomerTable = config('customers.database.tables.segment_customer', 'customer_segment_customer');
    $groupMembersTable = config('customers.database.tables.group_members', 'customer_group_members');

    expect(Schema::hasIndex($segmentCustomerTable, 'customer_segment_customer_customer_id_index'))->toBeTrue()
        ->and(Schema::hasIndex($groupMembersTable, 'customer_group_members_customer_id_index'))->toBeTrue();

    $migrationBase = dirname(__DIR__, 4)
        . '/packages/customers/database/migrations/';

    foreach ([
        '2000_05_01_000003_create_customer_segment_customer_table.php' => 'customer_segment_customer_customer_id_index',
        '2000_05_01_000005_create_customer_group_members_table.php' => 'customer_group_members_customer_id_index',
    ] as $migrationFile => $indexName) {
        $create = (string) file_get_contents($migrationBase . $migrationFile);

        expect($create)->toContain($indexName)
            ->and($create)->toContain('Schema::create')
            ->and($create)->not->toContain('hasTable');
    }
});

it('ships owner filter indexes in the customers and segments creates', function (): void {
    $customersTable = config('customers.database.tables.customers', 'customers');
    $segmentsTable = config('customers.database.tables.segments', 'customer_segments');

    $migrationBase = dirname(__DIR__, 4)
        . '/packages/customers/database/migrations/';

    foreach ([
        '2000_05_01_000001_create_customers_table.php' => 'customers_owner_status_index',
        '2000_05_01_000002_create_customer_segments_table.php' => 'customer_segments_owner_is_active_index',
    ] as $migrationFile => $indexName) {
        $create = (string) file_get_contents($migrationBase . $migrationFile);

        expect($create)->toContain($indexName)
            ->and($create)->toContain('Schema::create');
    }

    expect(Schema::hasIndex($customersTable, 'customers_owner_status_index'))->toBeTrue()
        ->and(Schema::hasIndex($customersTable, ['owner_type', 'owner_id', 'status']))->toBeTrue()
        ->and(Schema::hasIndex($segmentsTable, 'customer_segments_owner_is_active_index'))->toBeTrue()
        ->and(Schema::hasIndex($segmentsTable, ['owner_type', 'owner_id', 'is_active']))->toBeTrue();
});
