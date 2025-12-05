<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $ticket_id
 * @property string|null $affiliate_id
 * @property string|null $staff_id
 * @property string $message
 * @property bool $is_staff_reply
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read AffiliateSupportTicket $ticket
 * @property-read Affiliate|null $affiliate
 */
final class AffiliateSupportMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'ticket_id',
        'affiliate_id',
        'staff_id',
        'message',
        'is_staff_reply',
    ];

    protected $casts = [
        'is_staff_reply' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(AffiliateSupportTicket::class, 'ticket_id');
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }
}
