<?php

declare(strict_types=1);

namespace AIArmada\Membership\Tests\Fixtures;

use AIArmada\Membership\Traits\HasMembers;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class TestSubject extends Model
{
    use HasMembers;
    use HasUuids;

    protected $table = 'test_subjects';

    protected $fillable = ['name'];
}
