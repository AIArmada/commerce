<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $media_class
 * @property array<string, mixed> $media_attributes
 */
final class AffiliateCreativeAssetCleanup extends Model
{
    use HasUuids;

    protected $table = 'affiliate_creative_asset_cleanups';

    protected $fillable = ['media_class', 'media_attributes'];

    protected $hidden = ['media_attributes'];

    public function getTable(): string
    {
        return config('affiliates.database.tables.creative_asset_cleanups', parent::getTable());
    }

    protected function casts(): array
    {
        return ['media_attributes' => 'encrypted:array'];
    }
}
