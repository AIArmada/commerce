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
            ContactMethod::where('contactable_type', $contactMethod->contactable_type)
                ->where('contactable_id', $contactMethod->contactable_id)
                ->where('type', $contactMethod->type)
                ->where('purpose', $contactMethod->purpose)
                ->where('id', '!=', $contactMethod->id)
                ->update(['is_primary' => false]);

            $contactMethod->is_primary = true;
            $contactMethod->save();
        });

        return $contactMethod->refresh();
    }
}
