<?php

declare(strict_types=1);

namespace AIArmada\Rewards\Drivers;

use AIArmada\Rewards\Data\RewardSourceFact;

use AIArmada\Rewards\Contracts\RewardSourceFactReader;
use Closure;
use InvalidArgumentException;

/**
 * Routes canonical source keys by prefix to the registered reader or lazy resolver.
 */
final class CompositeRewardSourceFactReader implements RewardSourceFactReader
{
    /**
     * @var array<string, RewardSourceFactReader|Closure(): RewardSourceFactReader>
     */
    private array $readers = [];

    /**
     * @param  array<string, RewardSourceFactReader|Closure(): RewardSourceFactReader>  $readers
     */
    public function __construct(array $readers = [])
    {
        foreach ($readers as $prefix => $reader) {
            $this->register($prefix, $reader);
        }
    }

    /**
     * @param  RewardSourceFactReader|Closure(): RewardSourceFactReader  $reader
     */
    public function register(string $prefix, RewardSourceFactReader | Closure $reader): self
    {
        if ($prefix === '') {
            throw new InvalidArgumentException('Reward source fact reader prefix cannot be empty.');
        }

        $this->readers[$prefix] = $reader;

        return $this;
    }

    public function read(string $sourceBusinessKey): ?RewardSourceFact
    {
        foreach ($this->readers as $prefix => $reader) {
            if (! str_starts_with($sourceBusinessKey, $prefix)) {
                continue;
            }

            $resolved = $reader instanceof Closure ? $reader() : $reader;

            return $resolved->read($sourceBusinessKey);
        }

        return null;
    }
}
