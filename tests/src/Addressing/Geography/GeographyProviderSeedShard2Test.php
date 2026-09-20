<?php

declare(strict_types=1);

it('seeds provider shard 2 of 4 with consistent states, areas, links, and metadata', function (): void {
    $failures = [];

    foreach ($this->geographySeedShard(1) as $providerClass) {
        array_push($failures, ...$this->seedProviderConsistently($providerClass));
    }

    expect($failures)->toBe([]);
});
