<?php

declare(strict_types=1);

namespace AIArmada\Ticketing\Database\Factories;

use AIArmada\Ticketing\Models\Pass;
use AIArmada\Ticketing\Models\PassHolder;
use AIArmada\Ticketing\Models\PassTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PassTransfer>
 */
final class PassTransferFactory extends Factory
{
    protected $model = PassTransfer::class;

    public function definition(): array
    {
        return [
            'pass_id' => Pass::factory(),
            'from_holder_id' => PassHolder::factory(),
            'to_holder_id' => PassHolder::factory(),
            'reason' => null,
            'transferred_by_type' => null,
            'transferred_by_id' => null,
        ];
    }
}
