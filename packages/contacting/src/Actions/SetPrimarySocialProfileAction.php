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
            SocialProfile::where('socialable_type', $profile->socialable_type)
                ->where('socialable_id', $profile->socialable_id)
                ->where('platform', $profile->platform)
                ->where('purpose', $profile->purpose)
                ->where('id', '!=', $profile->id)
                ->update(['is_primary' => false]);

            $profile->is_primary = true;
            $profile->save();
        });

        return $profile->refresh();
    }
}
