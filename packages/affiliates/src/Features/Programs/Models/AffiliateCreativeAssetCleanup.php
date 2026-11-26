<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $attempt_count
 * @property string $media_class
 * @property array<string, mixed> $media_attributes
 */
final class AffiliateCreativeAssetCleanup extends Model
{
    use HasUuids;

    protected $fillable = ['media_class', 'media_attributes'];

    protected $hidden = ['media_attributes'];

    public function getTable(): string
    {
        return $this->table ?? config('affiliates.database.tables.creative_asset_cleanups', parent::getTable());
    }

    protected function casts(): array
    {
        return ['media_attributes' => 'encrypted:array', 'attempt_count' => 'integer'];
    }
}
