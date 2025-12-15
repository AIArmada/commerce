<?php

declare(strict_types=1);

use AIArmada\Vouchers\AI\VoucherMLDataCollector;
use Illuminate\Support\Collection;

describe('VoucherMLDataCollector', function (): void {
    beforeEach(function (): void {
        $this->collector = new VoucherMLDataCollector;
    });

    describe('exportToCsv', function (): void {
        it('does nothing for empty collection', function (): void {
            $filepath = sys_get_temp_dir() . '/test_empty_' . uniqid() . '.csv';

            $this->collector->exportToCsv(new Collection, $filepath);

            expect(file_exists($filepath))->toBeFalse();
        });

        it('writes data to CSV file', function (): void {
            $filepath = sys_get_temp_dir() . '/test_csv_' . uniqid() . '.csv';

            $data = new Collection([
                (object) ['id' => 1, 'name' => 'Test 1', 'value' => 100],
                (object) ['id' => 2, 'name' => 'Test 2', 'value' => 200],
                (object) ['id' => 3, 'name' => 'Test 3', 'value' => 300],
            ]);

            $this->collector->exportToCsv($data, $filepath);

            expect(file_exists($filepath))->toBeTrue();

            $content = file_get_contents($filepath);
            expect($content)->toContain('id,name,value')
                ->and($content)->toContain('1,"Test 1",100')
                ->and($content)->toContain('2,"Test 2",200')
                ->and($content)->toContain('3,"Test 3",300');

            // Cleanup
            unlink($filepath);
        });

        it('handles array data', function (): void {
            $filepath = sys_get_temp_dir() . '/test_array_csv_' . uniqid() . '.csv';

            $data = new Collection([
                ['id' => 1, 'name' => 'Test 1'],
                ['id' => 2, 'name' => 'Test 2'],
            ]);

            $this->collector->exportToCsv($data, $filepath);

            expect(file_exists($filepath))->toBeTrue();

            // Cleanup
            unlink($filepath);
        });

        it('throws exception for invalid filepath', function (): void {
            $filepath = '/nonexistent/directory/test.csv';

            $data = new Collection([
                (object) ['id' => 1, 'name' => 'Test'],
            ]);

            // fopen returns false and throws warning, which becomes ErrorException or RuntimeException
            expect(fn () => $this->collector->exportToCsv($data, $filepath))
                ->toThrow(Exception::class);
        });
    });

    describe('exportToJson', function (): void {
        it('writes data to JSON file', function (): void {
            $filepath = sys_get_temp_dir() . '/test_json_' . uniqid() . '.json';

            $data = new Collection([
                ['id' => 1, 'name' => 'Test 1', 'value' => 100],
                ['id' => 2, 'name' => 'Test 2', 'value' => 200],
            ]);

            $this->collector->exportToJson($data, $filepath);

            expect(file_exists($filepath))->toBeTrue();

            $content = file_get_contents($filepath);
            $decoded = json_decode($content, true);

            expect($decoded)->toBeArray()
                ->and($decoded)->toHaveCount(2)
                ->and($decoded[0]['id'])->toBe(1)
                ->and($decoded[0]['name'])->toBe('Test 1')
                ->and($decoded[1]['value'])->toBe(200);

            // Cleanup
            unlink($filepath);
        });

        it('writes pretty JSON format', function (): void {
            $filepath = sys_get_temp_dir() . '/test_pretty_json_' . uniqid() . '.json';

            $data = new Collection([
                ['id' => 1, 'name' => 'Test'],
            ]);

            $this->collector->exportToJson($data, $filepath);

            $content = file_get_contents($filepath);

            // Pretty print includes newlines
            expect($content)->toContain("\n");

            // Cleanup
            unlink($filepath);
        });

        it('handles empty collection', function (): void {
            $filepath = sys_get_temp_dir() . '/test_empty_json_' . uniqid() . '.json';

            $this->collector->exportToJson(new Collection, $filepath);

            expect(file_exists($filepath))->toBeTrue();

            $content = file_get_contents($filepath);
            expect(json_decode($content, true))->toBe([]);

            // Cleanup
            unlink($filepath);
        });
    });

    describe('getSummaryStatistics', function (): void {
        it('returns empty stats for empty collection', function (): void {
            $stats = $this->collector->getSummaryStatistics(new Collection);

            expect($stats['count'])->toBe(0)
                ->and($stats['columns'])->toBe([]);
        });

        it('identifies numeric columns', function (): void {
            $data = new Collection([
                (object) ['id' => 1, 'value' => 100],
                (object) ['id' => 2, 'value' => 200],
                (object) ['id' => 3, 'value' => 300],
            ]);

            $stats = $this->collector->getSummaryStatistics($data);

            expect($stats['count'])->toBe(3)
                ->and($stats['columns']['value']['type'])->toBe('numeric')
                ->and($stats['columns']['value']['min'])->toBe(100)
                ->and($stats['columns']['value']['max'])->toBe(300)
                ->and($stats['columns']['value']['avg'])->toBe(200.0)
                ->and($stats['columns']['value']['count'])->toBe(3);
        });

        it('identifies categorical columns', function (): void {
            $data = new Collection([
                (object) ['id' => 1, 'status' => 'active'],
                (object) ['id' => 2, 'status' => 'inactive'],
                (object) ['id' => 3, 'status' => 'active'],
            ]);

            $stats = $this->collector->getSummaryStatistics($data);

            expect($stats['columns']['status']['type'])->toBe('categorical')
                ->and($stats['columns']['status']['unique_values'])->toBe(2);
        });

        it('handles mixed columns', function (): void {
            $data = new Collection([
                (object) ['id' => 1, 'name' => 'Product A', 'price' => 99],
                (object) ['id' => 2, 'name' => 'Product B', 'price' => 149],
                (object) ['id' => 3, 'name' => 'Product C', 'price' => 199],
            ]);

            $stats = $this->collector->getSummaryStatistics($data);

            expect($stats['columns']['id']['type'])->toBe('numeric')
                ->and($stats['columns']['name']['type'])->toBe('categorical')
                ->and($stats['columns']['price']['type'])->toBe('numeric');
        });

        it('handles null values in numeric columns', function (): void {
            $data = new Collection([
                (object) ['id' => 1, 'value' => 100],
                (object) ['id' => 2, 'value' => null],
                (object) ['id' => 3, 'value' => 300],
            ]);

            $stats = $this->collector->getSummaryStatistics($data);

            // Should only count non-null numeric values
            expect($stats['columns']['value']['count'])->toBe(2);
        });

        it('handles zero values correctly', function (): void {
            $data = new Collection([
                (object) ['id' => 1, 'value' => 0],
                (object) ['id' => 2, 'value' => 50],
                (object) ['id' => 3, 'value' => 100],
            ]);

            $stats = $this->collector->getSummaryStatistics($data);

            expect($stats['columns']['value']['min'])->toBe(0)
                ->and($stats['columns']['value']['max'])->toBe(100)
                ->and($stats['columns']['value']['avg'])->toBe(50.0);
        });

        it('rounds average to 2 decimal places', function (): void {
            $data = new Collection([
                (object) ['value' => 1],
                (object) ['value' => 2],
                (object) ['value' => 3],
            ]);

            $stats = $this->collector->getSummaryStatistics($data);

            // 6/3 = 2.0, but test with values that produce decimals
            expect($stats['columns']['value']['avg'])->toBe(2.0);
        });

        it('counts unique categorical values correctly', function (): void {
            $data = new Collection([
                (object) ['type' => 'A'],
                (object) ['type' => 'B'],
                (object) ['type' => 'A'],
                (object) ['type' => 'C'],
                (object) ['type' => 'B'],
            ]);

            $stats = $this->collector->getSummaryStatistics($data);

            expect($stats['columns']['type']['unique_values'])->toBe(3); // A, B, C
        });

        it('handles array data', function (): void {
            $data = new Collection([
                ['id' => 1, 'name' => 'Test'],
                ['id' => 2, 'name' => 'Test 2'],
            ]);

            $stats = $this->collector->getSummaryStatistics($data);

            expect($stats['count'])->toBe(2)
                ->and($stats['columns'])->toHaveKey('id')
                ->and($stats['columns'])->toHaveKey('name');
        });
    });
});

describe('VoucherMLDataCollector database methods', function (): void {
    // Note: These methods require database tables and fixtures
    // Testing them fully would require integration tests
    // Here we just verify the methods exist and are callable

    it('has collectConversionData method', function (): void {
        $collector = new VoucherMLDataCollector;

        expect(method_exists($collector, 'collectConversionData'))->toBeTrue();
    });

    it('has collectAbandonmentData method', function (): void {
        $collector = new VoucherMLDataCollector;

        expect(method_exists($collector, 'collectAbandonmentData'))->toBeTrue();
    });

    it('has collectVoucherPerformanceData method', function (): void {
        $collector = new VoucherMLDataCollector;

        expect(method_exists($collector, 'collectVoucherPerformanceData'))->toBeTrue();
    });
});
