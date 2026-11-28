<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Data;

final readonly class RewardVersionSnapshot
{
    /**
     * @param  list<string>  $componentTypes
     * @param  array<string, mixed>  $rules
     */
    public function __construct(
        public string $id,
        public array $componentTypes,
        public array $rules,
    ) {}
}
