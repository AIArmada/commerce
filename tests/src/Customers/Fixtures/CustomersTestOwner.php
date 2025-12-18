<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class CustomersTestOwner extends Model
{
    use HasUuids;

    protected $table = 'test_owners';

    protected $fillable = [
        'name',
    ];
}
