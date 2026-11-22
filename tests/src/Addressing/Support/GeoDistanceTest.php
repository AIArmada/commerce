<?php

declare(strict_types=1);

use AIArmada\Addressing\Support\GeoDistance;

it('computes a symmetric bounding box for positive radius', function (): void {
    $box = GeoDistance::boundingBox(3.1390, 101.6869, 25.0);

    expect($box['min_lat'])->toBeLessThan(3.1390)
        ->and($box['max_lat'])->toBeGreaterThan(3.1390)
        ->and($box['min_lng'])->toBeLessThan(101.6869)
        ->and($box['max_lng'])->toBeGreaterThan(101.6869);

    expect(GeoDistance::kilometersBetween(3.1390, 101.6869, 3.1390, 101.6869))->toBe(0.0);
});

it('clamps bounding box safely near poles and antimeridian', function (): void {
    $polar = GeoDistance::boundingBox(89.9, 10.0, 50.0);

    expect($polar['max_lat'])->toBe(90.0)
        ->and($polar['min_lng'])->toBe(-180.0)
        ->and($polar['max_lng'])->toBe(180.0);

    $zeroRadius = GeoDistance::boundingBox(3.1390, 101.6869, -5.0);

    expect($zeroRadius)->toBe([
        'min_lat' => 3.1390,
        'max_lat' => 3.1390,
        'min_lng' => 101.6869,
        'max_lng' => 101.6869,
    ]);
});

it('builds driver-specific clamped haversine SQL and matching bindings', function (): void {
    $sqliteSql = GeoDistance::haversineSql('addresses.latitude', 'addresses.longitude', 'sqlite');
    $pgsqlSql = GeoDistance::haversineSql('addresses.latitude', 'addresses.longitude', 'pgsql');

    expect($sqliteSql)
        ->toContain('min(1.0, max(-1.0,')
        ->toContain('acos(')
        ->and($pgsqlSql)
        ->toContain('least(1.0, greatest(-1.0,')
        ->and(GeoDistance::haversineBindings(3.1390, 101.6869))
        ->toBe([3.1390, 101.6869, 3.1390]);

    $pdo = new PDO('sqlite::memory:');
    $literalSql = GeoDistance::haversineSql('3.1390', '101.6869', 'sqlite');
    $stmt = $pdo->prepare("SELECT {$literalSql} AS distance_km");
    $stmt->execute(GeoDistance::haversineBindings(3.1390, 101.6869));

    expect((float) $stmt->fetchColumn())->toBeLessThan(0.0001);
});
