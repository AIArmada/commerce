<?php

declare(strict_types=1);

namespace AIArmada\Chip\Data;

use AIArmada\Chip\Enums\SendWebhookHook;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class SendWebhookData extends ChipData
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

    public static function from(mixed ...$payloads): static
    {
        $data = self::resolvePayload(...$payloads);

        $eventHooks = $data['event_hooks'];

        if (! is_array($eventHooks)) {
            throw new InvalidArgumentException('CHIP Send webhook event_hooks must be an array.');
        }

        $eventHooks = array_values(array_map(
            static fn (mixed $hook): string => SendWebhookHook::from((string) $hook)->value,
            $eventHooks,
        ));

        return new self(
            id: (int) $data['id'],
            name: (string) $data['name'],
            public_key: (string) $data['public_key'],
            callback_url: (string) $data['callback_url'],
            email: (string) $data['email'],
            event_hooks: $eventHooks,
            created_at: (string) $data['created_at'],
            updated_at: (string) $data['updated_at'],
        );
    }

    public function handlesEvent(string $hook): bool
    {
        return in_array($hook, $this->event_hooks, true);
    }

    public function getCreatedAt(): CarbonImmutable
    {
        return $this->parseTimestamp($this->created_at);
    }

    public function getUpdatedAt(): CarbonImmutable
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

    private function parseTimestamp(string $value): CarbonImmutable
    {
        if (is_numeric($value)) {
            return CarbonImmutable::createFromTimestamp((int) $value);
        }

        return CarbonImmutable::parse($value);
    }
}
