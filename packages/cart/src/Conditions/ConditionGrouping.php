<?php

declare(strict_types=1);

namespace AIArmada\Cart\Conditions;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;

final class ConditionGrouping implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly ?string $groupBy = null,
        public readonly ?string $weightField = null,
        public readonly ?int $limit = null,
        public readonly ?string $preset = null
    ) {
        if ($this->groupBy === null && $this->preset === null) {
            return;
        }

        if ($this->groupBy !== null && mb_trim($this->groupBy) === '') {
            throw new InvalidArgumentException('Grouping field cannot be empty.');
        }
    }

    public static function forPreset(string $preset): self
    {
        return new self(preset: $preset);
    }

    /**
     * @param  array{group_by?:string|null, weight_field?:string|null, limit?:int|null, preset?:string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['group_by'] ?? null,
            $data['weight_field'] ?? null,
            $data['limit'] ?? null,
            $data['preset'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'group_by' => $this->groupBy,
            'weight_field' => $this->weightField,
            'limit' => $this->limit,
            'preset' => $this->preset,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
