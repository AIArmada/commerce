<?php

declare(strict_types=1);

use AIArmada\Communications\Models\CommunicationDestination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('communication destination address is ciphertext at rest and decrypts through the model', function (): void {
    $plaintext = 'destination-' . Str::uuid() . '@example.com';

    $destination = CommunicationDestination::create([
        'recipient_type' => 'stream-a-recipient',
        'recipient_id' => (string) Str::uuid(),
        'channel' => 'mail',
        'address' => $plaintext,
        'status' => 'active',
    ]);

    $rawAddress = DB::table($destination->getTable())
        ->where('id', $destination->id)
        ->value('address');

    expect(Schema::getColumnType($destination->getTable(), 'address'))->toBe('text')
        ->and($rawAddress)->toBeString()
        ->and($rawAddress)->not->toBe($plaintext)
        ->and($destination->fresh()->address)->toBe($plaintext);
});
