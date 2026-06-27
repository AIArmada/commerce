<?php

declare(strict_types=1);

namespace AIArmada\Cashier\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class UnifiedInvoiceRecord extends Model
{
    use HasUuids;

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'billable_type',
        'billable_id',
        'number',
        'reference',
        'currency',
        'date',
        'due_date',
        'paid_at',
        'pdf_url',
        'created_at',
        'updated_at',
    ];

    public function getTable(): string
    {
        return 'purchases';
    }
}
