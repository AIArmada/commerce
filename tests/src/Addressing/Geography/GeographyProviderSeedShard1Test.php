<?php

declare(strict_types=1);

it('seeds provider shard 1 of 4 with consistent states, areas, links, and metadata', function (): void {
    $failures = [];

    foreach ($this->geographySeedShard(0) as $providerClass) {
        array_push($failures, ...$this->seedProviderConsistently($providerClass));
    }

    expect($failures)->toBe([]);
});
