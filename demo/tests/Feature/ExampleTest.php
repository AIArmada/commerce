<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the application returns a successful response', function () {
    $owner = User::factory()->create();

    $response = $this->withSession(['demo_owner_id' => (string) $owner->getKey()])
        ->get('/');

    $response->assertStatus(200);
});
