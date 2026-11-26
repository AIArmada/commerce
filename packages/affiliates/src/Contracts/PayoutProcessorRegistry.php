<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Contracts;

interface PayoutProcessorRegistry
{
    public function make(string $type): PayoutProcessorInterface;

    public function register(string $type, string $processorClass): void;

    /** @return array<string> */
    public function getAvailableProcessors(): array;

    public function hasProcessor(string $type): bool;
}
