<?php

declare(strict_types=1);

it('uses nullableMorphs for JNT owner columns', function (): void {
    $repoRoot = dirname(__DIR__, 4);

    // Check the initial migration files which already include owner columns
    $ordersMigration = $repoRoot . '/packages/jnt/database/migrations/2000_10_01_000001_create_jnt_orders_table.php';
    $itemsMigration = $repoRoot . '/packages/jnt/database/migrations/2000_10_01_000002_create_jnt_order_items_table.php';
    $parcelsMigration = $repoRoot . '/packages/jnt/database/migrations/2000_10_01_000003_create_jnt_order_parcels_table.php';
    $eventsMigration = $repoRoot . '/packages/jnt/database/migrations/2000_10_01_000004_create_jnt_tracking_events_table.php';
    $webhooksMigration = $repoRoot . '/packages/jnt/database/migrations/2000_10_01_000005_create_jnt_webhook_logs_table.php';

    $orders = file_get_contents($ordersMigration);
    $items = file_get_contents($itemsMigration);
    $parcels = file_get_contents($parcelsMigration);
    $events = file_get_contents($eventsMigration);
    $webhooks = file_get_contents($webhooksMigration);

    expect($orders)->toBeString();
    expect($orders)->toContain("nullableMorphs('owner')");
    expect($orders)->not->toContain("nullableUuidMorphs('owner')");

    expect($items)->toBeString();
    expect($items)->toContain("nullableMorphs('owner')");
    expect($items)->not->toContain("nullableUuidMorphs('owner')");

    expect($parcels)->toBeString();
    expect($parcels)->toContain("nullableMorphs('owner')");
    expect($parcels)->not->toContain("nullableUuidMorphs('owner')");

    expect($events)->toBeString();
    expect($events)->toContain("nullableMorphs('owner')");
    expect($events)->not->toContain("nullableUuidMorphs('owner')");

    expect($webhooks)->toBeString();
    expect($webhooks)->toContain("nullableMorphs('owner')");
    expect($webhooks)->not->toContain("nullableUuidMorphs('owner')");
});
