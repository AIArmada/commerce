<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\Contacting\Models\ContactMethod;
use Illuminate\Support\Facades\DB;

final class SetPrimaryContactMethodAction
{
    public function execute(ContactMethod $contactMethod): ContactMethod
    {
        DB::transaction(function () use ($contactMethod): void {
            $contactMethod->is_primary = true;
            $contactMethod->save();
        });

        return $contactMethod->refresh();
    }
}
