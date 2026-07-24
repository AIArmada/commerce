<?php

declare(strict_types=1);

namespace AIArmada\Persons\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Title> $titles
 */
class TitleCategory extends Model
{
    use HasFactory;
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'sort_order',
    ];

    public function getTable(): string
    {
        return config('persons.database.tables.title_categories', 'title_categories');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Title, $this>
     */
    public function titles(): HasMany
    {
        return $this->hasMany(Title::class, 'category_id');
    }
}
