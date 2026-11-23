<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\TestCase;
use AIArmada\Membership\Models\MembershipInvitation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

it('creates the invitation schema through the owning migration', function (): void {
    $exitCode = Artisan::call('migrate', [
        '--path' => 'packages/membership/database/migrations',
        '--realpath' => true,
    ]);

    expect($exitCode)->toBe(0);

    $schema = Schema::connection('testing');

    expect($schema->hasTable('membership_invitations'))->toBeTrue()
        ->and($schema->hasColumns('membership_invitations', [
            'channel', 'recipient', 'token', 'status', 'owner_type', 'owner_id',
        ]))->toBeTrue()
        ->and($schema->hasColumn('membership_invitations', 'email'))->toBeFalse();

    $invitation = new MembershipInvitation;
    $invitation->fill([
        'subject_type' => 'test_subject',
        'subject_id' => '00000000-0000-0000-0000-000000000001',
        'channel' => 'sms',
        'recipient' => '+60123456789',
        'role' => 'viewer',
        'invited_by' => '00000000-0000-0000-0000-000000000002',
    ]);
    $invitation->issue('token');
    $invitation->save();

    expect($invitation->fresh()->recipient)->toBe('+60123456789');
});
