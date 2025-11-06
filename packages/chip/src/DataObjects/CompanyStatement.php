<?php

declare(strict_types=1);

namespace AIArmada\Chip\DataObjects;

use Carbon\Carbon;

final class CompanyStatement
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $format,
        public readonly string $timezone,
        public readonly bool $is_test,
        public readonly string $company_uid,
        public readonly ?string $query_string,
        public readonly string $status,
        public readonly ?string $download_url,
        public readonly ?int $began_on,
        public readonly ?int $finished_on,
        public readonly int $created_on,
        public readonly int $updated_on,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            type: (string) ($data['type'] ?? 'statement'),
            format: (string) ($data['format'] ?? ''),
            timezone: (string) ($data['timezone'] ?? 'UTC'),
            is_test: (bool) ($data['is_test'] ?? false),
            company_uid: (string) ($data['company_uid'] ?? ''),
            query_string: isset($data['query_string']) ? (string) $data['query_string'] : null,
            status: (string) ($data['status'] ?? ''),
            download_url: isset($data['download_url']) ? (string) $data['download_url'] : null,
            began_on: isset($data['began_on']) ? (int) $data['began_on'] : null,
            finished_on: isset($data['finished_on']) ? (int) $data['finished_on'] : null,
            created_on: (int) ($data['created_on'] ?? time()),
            updated_on: (int) ($data['updated_on'] ?? time()),
        );
    }

    public function getBeganAt(): ?Carbon
    {
        return $this->began_on ? Carbon::createFromTimestamp($this->began_on) : null;
    }

    public function getFinishedAt(): ?Carbon
    {
        return $this->finished_on ? Carbon::createFromTimestamp($this->finished_on) : null;
    }

    public function getCreatedAt(): Carbon
    {
        return Carbon::createFromTimestamp($this->created_on);
    }

    public function getUpdatedAt(): Carbon
    {
        return Carbon::createFromTimestamp($this->updated_on);
    }

    public function isReady(): bool
    {
        return $this->status === 'finished';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'format' => $this->format,
            'timezone' => $this->timezone,
            'is_test' => $this->is_test,
            'company_uid' => $this->company_uid,
            'query_string' => $this->query_string,
            'status' => $this->status,
            'download_url' => $this->download_url,
            'began_on' => $this->began_on,
            'finished_on' => $this->finished_on,
            'created_on' => $this->created_on,
            'updated_on' => $this->updated_on,
        ];
    }
}
