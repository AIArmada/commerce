<?php

declare(strict_types=1);

namespace AIArmada\Chip\DataObjects;

use Carbon\Carbon;

final class SendWebhook
{
    /**
     * @param  array<int, string>  $event_hooks
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $public_key,
        public readonly string $callback_url,
        public readonly string $email,
        public readonly array $event_hooks,
        public readonly string $created_at,
        public readonly string $updated_at,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $callbackKey = array_key_exists('callback_url', $data) ? 'callback_url' : 'callback_url"';

        $eventHooks = $data['event_hooks'] ?? [];
        $eventHooks = is_array($eventHooks) ? array_values(array_map('strval', $eventHooks)) : [];

        return new self(
            id: (int) $data['id'],
            name: $data['name'] ?? '',
            public_key: $data['public_key'] ?? '',
            callback_url: (string) ($data[$callbackKey] ?? ''),
            email: $data['email'] ?? '',
            event_hooks: $eventHooks,
            created_at: (string) ($data['created_at'] ?? Carbon::now()->toISOString()),
            updated_at: (string) ($data['updated_at'] ?? Carbon::now()->toISOString()),
        );
    }

    public function handlesEvent(string $hook): bool
    {
        return in_array($hook, $this->event_hooks, true);
    }

    public function getCreatedAt(): Carbon
    {
        return $this->parseTimestamp($this->created_at);
    }

    public function getUpdatedAt(): Carbon
    {
        return $this->parseTimestamp($this->updated_at);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'public_key' => $this->public_key,
            'callback_url' => $this->callback_url,
            'email' => $this->email,
            'event_hooks' => $this->event_hooks,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function parseTimestamp(string $value): Carbon
    {
        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }

        return Carbon::parse($value);
    }
}
