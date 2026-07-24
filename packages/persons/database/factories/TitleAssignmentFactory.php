<?php

declare(strict_types=1);

namespace AIArmada\Persons\Database\Factories;

use AIArmada\Persons\Enums\AssignmentStatus;
use AIArmada\Persons\Models\Title;
use AIArmada\Persons\Models\TitleAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TitleAssignment>
 */
final class TitleAssignmentFactory extends Factory
{
    protected $model = TitleAssignment::class;

    public function definition(): array
    {
        return [
            'titleable_type' => 'person',
            'title_id' => Title::factory(),
            'status' => AssignmentStatus::Active,
        ];
    }
}
