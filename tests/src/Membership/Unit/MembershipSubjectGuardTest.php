<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use AIArmada\Membership\Support\MembershipSubjectGuard;
use AIArmada\Membership\Tests\MembershipTestCase;
use Illuminate\Database\Eloquent\Model;

uses(MembershipTestCase::class);

it('does not invoke the owner guard for an explicitly unscoped subject', function (): void {
    // Explicit try/catch: not->toThrow(Throwable::class) is vacuous on interfaces.
    $thrown = null;

    try {
        (new MembershipSubjectGuard)->validate(new MembershipSubjectGuardUnscopedSubject);
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeNull();
});

final class MembershipSubjectGuardUnscopedSubject extends Model
{
    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: false);
    }
}
