<?php

declare(strict_types=1);

namespace AIArmada\Addressing\Features\ResolutionGaps\Actions;

use AIArmada\Addressing\Contracts\ResolutionGapCuration;
use AIArmada\Addressing\Support\ModelResolver;

final readonly class CurateResolutionGapAction implements ResolutionGapCuration
{
    public function __construct(private MatchGapToAreaAction $matcher, private IgnoreResolutionGapAction $ignorer) {}

    public function match(string $gapId, string $areaId, ?string $actor = null): void
    {
        $gap = ModelResolver::resolutionGapClass()::query()->findOrFail($gapId);
        $area = ModelResolver::areaClass()::query()->findOrFail($areaId);
        $this->matcher->execute($gap, $area, $actor);
    }

    public function ignore(string $gapId): void
    {
        $gap = ModelResolver::resolutionGapClass()::query()->findOrFail($gapId);
        $this->ignorer->execute($gap);
    }
}
