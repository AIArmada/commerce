<?php /** @var \Illuminate\View\ComponentAttributeBag $attributes */ ?>
## Model Guidelines

**CRITICAL**: Never use database-level foreign key constraints or cascades (`->constrained()`, `->cascadeOnDelete()`). Handle all referential integrity and cascading **in application code only**.

### Required Model Structure

```php
<?php

declare(strict_types=1);

namespace {{ $namespace }}\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, {{ $childModel }}> ${{ $childPlural }}
 */
class {{ $modelClass }} extends Model
{
    use HasUuids;

    protected $fillable = [
        // List fillable columns matching migration
    ];

    public function getTable(): string
    {
        $tables = config('{{ $configKey }}.database.tables', []);
        $prefix = config('{{ $configKey }}.database.table_prefix', '{{ $tablePrefix}}_');

        return $tables['{{ $tableKey }}'] ?? $prefix.'{{ $tableName }}';
    }

    /**
     * @return HasMany<{{ $childModel }}, $this>
     */
    public function {{ $childPlural }}(): HasMany
    {
        return $this->hasMany({{ $childModel }}::class, '{{ $foreignKey }}');
    }

    /**
     * @return BelongsTo<{{ $parentModel }}, $this>
     */
    public function {{ $parentSnake }}(): BelongsTo
    {
        return $this->belongsTo({{ $parentModel }}::class, '{{ $foreignKey }}');
    }

    /**
     * Application-level cascade delete (NO database constraints!)
     */
    protected static function booted(): void
    {
        static::deleting(function ({{ $modelClass }} ${{ $modelVar }}): void {
            ${{ $modelVar }}->{{ $childPlural }}()->delete();
            // Add other cascades as needed
            // For nullable FKs: ${{ $modelVar }}->{{ $childPlural }}()->update(['{{ $foreignKey }}' => null]);
        });
    }

    protected function casts(): array
    {
        return [
            // Casts for dates, JSON, booleans, enums
            '{{ $jsonField }}' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
```

### Cascade Rules

| Relationship | Delete Action | Example |
|--------------|---------------|---------|
| `hasMany` children | `->delete()` | `$order->items()->delete();` |
| Nullable FK children | `->update(['fk' => null])` | `$order->webhookLogs()->update(['order_id' => null]);` |

### Verification Checklist
- ✅ `HasUuids` trait
- ✅ `getTable()` from config (no hardcoded names)
- ✅ `booted()` with cascade deletes
- ✅ **NO** `protected $table` property
- ✅ PHPDoc `@property` annotations
- ✅ Type-safe relations with generics
- ✅ PHPStan level 6 compliant

**Migration**: Use `foreignUuid('order_id')` **without** `->constrained()` or cascades.
