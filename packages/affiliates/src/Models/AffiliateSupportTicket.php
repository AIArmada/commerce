<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $affiliate_id
 * @property string $subject
 * @property string $category
 * @property string $priority
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Affiliate $affiliate
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliateSupportMessage> $messages
 */
final class AffiliateSupportTicket extends Model
{
    use HasUuids;

    protected $fillable = [
        'affiliate_id',
        'subject',
        'category',
        'priority',
        'status',
    ];

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AffiliateSupportMessage::class, 'ticket_id')
            ->orderBy('created_at');
    }
}
