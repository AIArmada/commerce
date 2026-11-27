<?php

declare(strict_types=1);

namespace AIArmada\Chip\Contracts;

interface ChipCollectTransport
{
    /**
     * @return array<string, mixed>|string
     */
    public function get(string $endpoint): array | string;

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $data = [], array $headers = []): array;

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function put(string $endpoint, array $data = [], array $headers = []): array;

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function patch(string $endpoint, array $data = [], array $headers = []): array;

    /**
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function delete(string $endpoint, array $headers = []): array;

    public function getBrandId(): string;
}
