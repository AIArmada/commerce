<?php

declare(strict_types=1);

namespace AIArmada\Chip\Contracts;

interface ChipSendTransport
{
    /**
     * @return array<string, mixed>
     */
    public function get(string $endpoint): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $data = []): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function put(string $endpoint, array $data = []): array;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function patch(string $endpoint, array $data = []): array;

    /**
     * @return array<string, mixed>
     */
    public function delete(string $endpoint): array;

    public function getEnvironment(): string;
}
