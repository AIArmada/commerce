<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Support\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TestOwner extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'test_owners';

    protected $fillable = ['name'];
}
