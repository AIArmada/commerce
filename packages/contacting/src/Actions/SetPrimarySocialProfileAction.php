<?php

declare(strict_types=1);

namespace AIArmada\Contacting\Actions;

use AIArmada\Contacting\Models\SocialProfile;
use Illuminate\Support\Facades\DB;

final class SetPrimarySocialProfileAction
{
    public function execute(SocialProfile $profile): SocialProfile
    {
        DB::transaction(function () use ($profile): void {
            $profile->is_primary = true;
            $profile->save();
        });

        return $profile->refresh();
    }
}
