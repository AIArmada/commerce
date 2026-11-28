<?php

declare(strict_types=1);

use AIArmada\Inventory\Contracts\ExportableInterface;
use AIArmada\Inventory\Features\Reporting\Actions\ExportService;

beforeEach(function (): void {
    $this->service = new ExportService;
});

describe('toCsv', function (): void {
    it('generates CSV from exportable', function (): void {
        $export = new class implements ExportableInterface
        {
            public function getHeaders(): array
            {
                return ['ID', 'Name', 'Quantity'];
            }

            public function getRows(): iterable
            {
                yield ['1', 'Product A', '100'];
                yield ['2', 'Product B', '200'];
            }

            public function getFilename(): string
            {
                return 'test-export';
            }
        };

        $csv = $this->service->toCsv($export);

        expect($csv)->toContain('ID,Name,Quantity');
        expect($csv)->toContain('1,"Product A",100');
        expect($csv)->toContain('2,"Product B",200');
    });

    it('handles empty rows', function (): void {
        $export = new class implements ExportableInterface
        {
            public function getHeaders(): array
            {
                return ['ID', 'Name'];
            }

            public function getRows(): iterable
            {
                return [];
            }

            public function getFilename(): string
            {
                return 'empty-export';
            }
        };

        $csv = $this->service->toCsv($export);

        expect($csv)->toContain('ID,Name');
        expect(mb_trim($csv))->toBe('ID,Name');
    });
});

describe('toCsvFile', function (): void {
    it('writes CSV to file', function (): void {
        $export = new class implements ExportableInterface
        {
            public function getHeaders(): array
            {
                return ['ID', 'Name'];
            }

            public function getRows(): iterable
            {
                yield ['1', 'Test'];
            }

            public function getFilename(): string
            {
                return 'file-export';
            }
        };

        $tempDir = sys_get_temp_dir();
        $path = $this->service->toCsvFile($export, $tempDir);

        expect(file_exists($path))->toBeTrue();
        expect($path)->toEndWith('file-export.csv');

        $contents = file_get_contents($path);
        expect($contents)->toContain('ID,Name');
        expect($contents)->toContain('1,Test');

        unlink($path);
    });
});

describe('toArray', function (): void {
    it('converts export to array', function (): void {
        $export = new class implements ExportableInterface
        {
            public function getHeaders(): array
            {
                return ['A', 'B'];
            }

            public function getRows(): iterable
            {
                yield ['1', '2'];
                yield ['3', '4'];
            }

            public function getFilename(): string
            {
                return 'array-export';
            }
        };

        $result = $this->service->toArray($export);

        expect($result)->toHaveKey('headers');
        expect($result)->toHaveKey('rows');
        expect($result)->toHaveKey('filename');
        expect($result['headers'])->toBe(['A', 'B']);
        expect($result['rows'])->toBe([['1', '2'], ['3', '4']]);
        expect($result['filename'])->toBe('array-export');
    });
});

describe('stream', function (): void {
    it('yields CSV content row by row', function (): void {
        $export = new class implements ExportableInterface
        {
            public function getHeaders(): array
            {
                return ['Col1', 'Col2'];
            }

            public function getRows(): iterable
            {
                yield ['A', 'B'];
                yield ['C', 'D'];
            }

            public function getFilename(): string
            {
                return 'stream-export';
            }
        };

        $chunks = [];
        foreach ($this->service->stream($export) as $chunk) {
            $chunks[] = mb_trim($chunk);
        }

        expect($chunks)->toHaveCount(3);
        expect($chunks[0])->toBe('Col1,Col2');
        expect($chunks[1])->toBe('A,B');
        expect($chunks[2])->toBe('C,D');
    });
});

describe('getRowCount', function (): void {
    it('counts rows', function (): void {
        $export = new class implements ExportableInterface
        {
            public function getHeaders(): array
            {
                return ['ID'];
            }

            public function getRows(): iterable
            {
                yield ['1'];
                yield ['2'];
                yield ['3'];
            }

            public function getFilename(): string
            {
                return 'count-export';
            }
        };

        $count = $this->service->getRowCount($export);

        expect($count)->toBe(3);
    });

    it('returns zero for empty export', function (): void {
        $export = new class implements ExportableInterface
        {
            public function getHeaders(): array
            {
                return ['ID'];
            }

            public function getRows(): iterable
            {
                return [];
            }

            public function getFilename(): string
            {
                return 'empty';
            }
        };

        expect($this->service->getRowCount($export))->toBe(0);
    });
});

it('neutralizes spreadsheet formulas in every CSV output without altering raw arrays', function (): void {
    $export = new class implements ExportableInterface
    {
        public function getHeaders(): array
        {
            return ['Text', 'Number'];
        }

        public function getRows(): iterable
        {
            yield ['=1+1', -5];
            yield ['@SUM(A1:A2)', 2];
            yield [' +1+1', 3];
        }

        public function getFilename(): string
        {
            return 'formula-export-' . bin2hex(random_bytes(8));
        }
    };

    $csv = $this->service->toCsv($export);

    expect($csv)->toContain("'=1+1,-5")
        ->and($csv)->toContain("'@SUM(A1:A2),2")
        ->and($csv)->toContain("' +1+1")
        ->and($this->service->toArray($export)['rows'][0][0])->toBe('=1+1')
        ->and(implode('', iterator_to_array($this->service->stream($export))))->toBe($csv);
});

it('does not overwrite an existing CSV destination', function (): void {
    $export = new class implements ExportableInterface
    {
        public function getHeaders(): array
        {
            return ['ID'];
        }

        public function getRows(): iterable
        {
            yield [1];
        }

        public function getFilename(): string
        {
            return 'existing-export';
        }
    };

    $directory = sys_get_temp_dir() . '/inventory-export-' . bin2hex(random_bytes(8));
    mkdir($directory);
    $path = $directory . '/existing-export.csv';
    file_put_contents($path, 'original');

    try {
        expect(fn () => $this->service->toCsvFile($export, $directory))->toThrow(RuntimeException::class)
            ->and(file_get_contents($path))->toBe('original');
    } finally {
        unlink($path);
        rmdir($directory);
    }
});

it('writes multilingual CSV using byte lengths rather than character counts', function (): void {
    $export = new class implements ExportableInterface
    {
        public function getHeaders(): array
        {
            return ['Nama', '名称'];
        }

        public function getRows(): iterable
        {
            yield ['Kuala Lumpur', '日本語'];
            yield ['Café', 'مرحبا'];
        }

        public function getFilename(): string
        {
            return 'unicode-export-' . bin2hex(random_bytes(8));
        }
    };

    $path = $this->service->toCsvFile($export, sys_get_temp_dir());

    try {
        expect(file_get_contents($path))->toBe($this->service->toCsv($export));
    } finally {
        unlink($path);
    }
});
