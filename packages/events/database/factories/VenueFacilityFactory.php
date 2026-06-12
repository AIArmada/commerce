<?php

declare(strict_types=1);

namespace AIArmada\Events\Database\Factories;

use AIArmada\Events\Models\VenueFacility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VenueFacility>
 */
final class VenueFacilityFactory extends Factory
{
    protected $model = VenueFacility::class;

    public function definition(): array
    {
        return [
            'availability' => 'available',
            'visibility' => 'public',
        ];
    }
}
