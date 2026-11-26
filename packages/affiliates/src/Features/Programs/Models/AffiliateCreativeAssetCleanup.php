<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $attempt_count
 * @property string $media_class
 * @property array<string, mixed> $media_attributes
 * @property string|null $claim_token
 * @property CarbonImmutable|null $claimed_at
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
        return [
            'media_attributes' => 'encrypted:array',
            'attempt_count' => 'integer',
            'claimed_at' => 'immutable_datetime',
        ];
    }
}
