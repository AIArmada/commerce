<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerScopeConfig;
use AIArmada\Membership\Support\MembershipSubjectGuard;
use AIArmada\Membership\Tests\MembershipTestCase;
use Illuminate\Database\Eloquent\Model;

uses(MembershipTestCase::class);

it('does not invoke the owner guard for an explicitly unscoped subject', function (): void {
    expect(fn (): mixed => (new MembershipSubjectGuard)->validate(new MembershipSubjectGuardUnscopedSubject))
        ->not->toThrow(Throwable::class);
});

final class MembershipSubjectGuardUnscopedSubject extends Model
{
    public static function ownerScopeConfig(): OwnerScopeConfig
    {
        return new OwnerScopeConfig(enabled: false);
    }
}
