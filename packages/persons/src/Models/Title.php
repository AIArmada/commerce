<?php

declare(strict_types=1);

namespace AIArmada\Persons\Models;

use AIArmada\Persons\Enums\TitleUsagePosition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $category_id
 * @property string $name
 * @property string|null $short_form
 * @property string|null $country_id
 * @property string|null $language_code
 * @property TitleUsagePosition $usage_position
 * @property int $sort_order
 * @property string|null $description
 * @property-read TitleCategory $category
 * @property-read Collection<int, TitleAssignment> $assignments
 */
class Title extends Model
{
    use HasFactory;
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'category_id',
        'name',
        'short_form',
        'country_id',
        'language_code',
        'usage_position',
        'sort_order',
        'description',
    ];

    public function getTable(): string
    {
        return config('persons.database.tables.titles', 'titles');
    }

    protected function casts(): array
    {
        return [
            'usage_position' => TitleUsagePosition::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TitleCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TitleCategory::class, 'category_id');
    }

    /**
     * @return HasMany<TitleAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TitleAssignment::class, 'title_id');
    }
}
