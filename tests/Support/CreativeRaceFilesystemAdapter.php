<?php

declare(strict_types=1);

namespace Tests\Support;

use League\Flysystem\Config;
use League\Flysystem\Local\LocalFilesystemAdapter;
use RuntimeException;

final class CreativeRaceFilesystemAdapter extends LocalFilesystemAdapter
{
    public function __construct(string $location, private readonly string $gate)
    {
        parent::__construct($location);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->awaitRelease();

        parent::write($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->awaitRelease();

        parent::writeStream($path, $contents, $config);
    }

    private function awaitRelease(): void
    {
        if (! is_file($this->gate . '/hold')) {
            return;
        }

        file_put_contents($this->gate . '/started', (string) getmypid());

        $deadline = microtime(true) + 60;

        while (! is_file($this->gate . '/release')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Creative race storage gate timed out.');
            }

            usleep(1000);
        }
    }
}
