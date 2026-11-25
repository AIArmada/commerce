<?php

declare(strict_types=1);

namespace AIArmada\Authz\Contracts;

use AIArmada\CommerceSupport\Data\RecordReference;

interface AdminUserProvisioner
{
    public function execute(string $model, string $nameColumn, string $emailColumn, string $name, string $email, string $password): RecordReference;
}
