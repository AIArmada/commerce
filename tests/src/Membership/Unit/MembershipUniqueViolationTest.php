<?php

declare(strict_types=1);

use AIArmada\Membership\Persistence\MembershipUniqueViolation;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

it('distinguishes duplicate keys from other integrity violations', function (): void {
    $sql = 'insert into membership_applications (subject_type, subject_id, applicant_id, status) values (?, ?, ?, ?)';

    $foreignKey = new PDOException('FOREIGN KEY constraint failed');
    $foreignKey->errorInfo = ['23000', 19, 'FOREIGN KEY constraint failed'];
    $notNull = new PDOException('NOT NULL constraint failed: membership_applications.status');
    $notNull->errorInfo = ['23000', 19, 'NOT NULL constraint failed: membership_applications.status'];
    $duplicate = new PDOException('UNIQUE constraint failed: membership_applications.subject_type, membership_applications.subject_id, membership_applications.applicant_id, membership_applications.status');
    $duplicate->errorInfo = ['23000', 19, $duplicate->getMessage()];

    expect(MembershipUniqueViolation::matches(new QueryException('sqlite', $sql, [], $foreignKey)))->toBeFalse()
        ->and(MembershipUniqueViolation::matches(new QueryException('sqlite', $sql, [], $notNull)))->toBeFalse()
        ->and(MembershipUniqueViolation::matches(new UniqueConstraintViolationException('sqlite', $sql, [], $duplicate)))->toBeTrue()
        ->and(MembershipUniqueViolation::detail(new QueryException('sqlite', $sql, [], $foreignKey)))->toBe('foreign key constraint failed');
});
