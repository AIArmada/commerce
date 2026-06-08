<?php

declare(strict_types=1);

namespace AIArmada\Events\Contracts;

use AIArmada\Events\Data\EventSearchCriteria;
use AIArmada\Events\Data\EventSearchResultData;

interface EventSearchEngine
{
    public function search(EventSearchCriteria $criteria): EventSearchResultData;
}
