<?php

declare(strict_types=1);

namespace AIArmada\Authz\Features\Authorization\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $scopeable_type
 * @property string $scopeable_id
 * @property string|null $label
 */
final class AuthzScope extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'scopeable_type',
        'scopeable_id',
        'label',
    ];

    public function getTable(): string
    {
        $tables = config('authz.database.tables', []);
        $prefix = config('authz.database.table_prefix', '');

        return $prefix . ($tables['scopes'] ?? 'authz_scopes');
    }

    public function scopeable(): MorphTo
    {
        return $this->morphTo();
    }

}
