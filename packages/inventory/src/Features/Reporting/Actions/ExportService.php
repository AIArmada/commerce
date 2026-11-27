<?php

declare(strict_types=1);

namespace AIArmada\Inventory\Features\Reporting\Actions;

use AIArmada\Inventory\Contracts\ExportableInterface;
use Generator;
use InvalidArgumentException;
use RuntimeException;

final class ExportService
{
    public function toCsv(ExportableInterface $export): string
    {
        $output = '';

        foreach ($this->stream($export) as $chunk) {
            $output .= $chunk;
        }

        return $output;
    }

    public function toCsvFile(ExportableInterface $export, string $path): string
    {
        $filename = $export->getFilename();
        $this->assertSafeExportPath($path, $filename);

        $fullPath = mb_rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $filename . '.csv';
        if (file_exists($fullPath) || is_link($fullPath)) {
            throw new RuntimeException('CSV export destination already exists.');
        }

        $output = fopen($fullPath, 'xb');

        if ($output === false) {
            throw new RuntimeException("Failed to create CSV export: {$fullPath}");
        }

        $complete = false;

        try {
            foreach ($this->stream($export) as $chunk) {
                if (fwrite($output, $chunk) !== mb_strlen($chunk)) {
                    throw new RuntimeException('Failed to write complete CSV row.');
                }
            }

            $complete = true;
        } finally {
            fclose($output);

            if (! $complete) {
                unlink($fullPath);
            }
        }

        return $fullPath;
    }

    /** @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>, filename: string} */
    public function toArray(ExportableInterface $export): array
    {
        $rows = [];

        foreach ($export->getRows() as $row) {
            $rows[] = $row;
        }

        return [
            'headers' => $export->getHeaders(),
            'rows' => $rows,
            'filename' => $export->getFilename(),
        ];
    }

    /** @return Generator<int, string> */
    public function stream(ExportableInterface $export): Generator
    {
        $output = fopen('php://temp', 'r+');

        if ($output === false) {
            throw new RuntimeException('Failed to open temporary CSV stream.');
        }

        try {
            yield $this->encodeRow($output, $export->getHeaders());

            foreach ($export->getRows() as $row) {
                yield $this->encodeRow($output, $row);
            }
        } finally {
            fclose($output);
        }
    }

    public function getRowCount(ExportableInterface $export): int
    {
        $count = 0;

        foreach ($export->getRows() as $_) {
            $count++;
        }

        return $count;
    }

    /** @param resource $output
     * @param  array<int, mixed>  $row
     */
    private function encodeRow($output, array $row): string
    {
        $cells = array_map(static function (mixed $cell): mixed {
            if (is_string($cell) && preg_match('/^(?:[\t\r\n]|[\x00-\x20]*[=+\-@])/u', $cell) === 1) {
                return "'" . $cell;
            }

            return $cell;
        }, $row);

        if (! ftruncate($output, 0) || ! rewind($output) || fputcsv($output, $cells, ',', '"', '') === false || ! rewind($output)) {
            throw new RuntimeException('Failed to encode CSV row.');
        }

        $chunk = stream_get_contents($output);

        if ($chunk === false) {
            throw new RuntimeException('Failed to read CSV row.');
        }

        return $chunk;
    }

    private function assertSafeExportPath(string $path, string $filename): void
    {
        if ($path === '' || $filename === '' || ! is_dir($path)) {
            throw new InvalidArgumentException('Export directory and filename must be valid.');
        }

        if (str_contains($path, '..') || str_contains($path, "\0") || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $filename) || str_contains($filename, '..')) {
            throw new InvalidArgumentException('Invalid export path or filename.');
        }
    }
}
