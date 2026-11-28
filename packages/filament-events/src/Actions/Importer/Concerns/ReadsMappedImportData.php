<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Actions\Importer\Concerns;

trait ReadsMappedImportData
{
    /** @return array<string, mixed> */
    private function mappedImportData(): array
    {
        $mapped = [];

        foreach (static::getColumns() as $column) {
            $name = $column->getName();

            if (filled($this->columnMap[$name] ?? null) && array_key_exists($name, $this->data)) {
                $mapped[$name] = $this->data[$name];
            }
        }

        return $mapped;
    }
}
