<?php

declare(strict_types=1);

namespace AIArmada\CommerceSupport\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

final class PublishMigrationsCommand extends Command
{
    protected $signature = 'commerce:publish-migrations
                            {--all : Publish migrations for all detected Commerce packages}
                            {--tags=* : Publish only specific publish tags (e.g. affiliates-migrations)}
                            {--list : List available migration publish tags}
                            {--dry-run : Do not publish, only show what would be published}
                            {--force : Overwrite any existing published files}';

    protected $description = 'Publish migration files for installed Commerce packages';

    public function handle(): int
    {
        $available = $this->migrationPublishTagsByProvider();

        if ($available === []) {
            $this->components->warn('No Commerce migration publish tags detected.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('list')) {
            $this->renderAvailable($available);

            return self::SUCCESS;
        }

        $selectedTags = $this->selectedTags($available);

        if ($selectedTags === []) {
            $this->components->warn('No migration tags selected.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        foreach ($selectedTags as $tag) {
            if ($dryRun) {
                $this->line("Would publish: {$tag}");

                continue;
            }

            $this->components->info("Publishing: {$tag}");

            $exitCode = $this->call('vendor:publish', array_filter([
                '--tag' => $tag,
                '--force' => $force ? true : null,
            ]));

            if ($exitCode !== self::SUCCESS) {
                $this->components->error("Failed publishing tag: {$tag}");

                return $exitCode;
            }
        }

        if ($dryRun) {
            $this->components->info('Dry run complete.');
        } else {
            $this->components->info('Migration publishing complete.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<class-string, array<int, string>>
     */
    private function migrationPublishTagsByProvider(): array
    {
        $migrationTags = array_values(array_filter(
            ServiceProvider::publishableGroups(),
            static fn (string $group): bool => str_ends_with($group, '-migrations')
        ));

        if ($migrationTags === []) {
            return [];
        }

        $result = [];

        /** @var array<int, class-string> $providers */
        $providers = ServiceProvider::publishableProviders();

        foreach ($providers as $providerClass) {
            if (! str_starts_with($providerClass, 'AIArmada\\')) {
                continue;
            }

            $tagsForProvider = [];

            foreach ($migrationTags as $tag) {
                $paths = ServiceProvider::pathsToPublish($providerClass, $tag);

                if ($paths !== []) {
                    $tagsForProvider[] = $tag;
                }
            }

            if ($tagsForProvider !== []) {
                $result[$providerClass] = array_values(array_unique($tagsForProvider));
            }
        }

        ksort($result);

        return $result;
    }

    /**
     * @param  array<class-string, array<int, string>>  $available
     */
    private function renderAvailable(array $available): void
    {
        $rows = [];

        foreach ($available as $provider => $tags) {
            foreach ($tags as $tag) {
                $rows[] = [$tag, $provider];
            }
        }

        $this->table(['Tag', 'Provider'], $rows);
    }

    /**
     * @param  array<class-string, array<int, string>>  $available
     * @return array<int, string>
     */
    private function selectedTags(array $available): array
    {
        $allTags = collect(Arr::flatten(array_values($available)))
            ->unique()
            ->values()
            ->all();

        /** @var array<int, string> $tagsOption */
        $tagsOption = $this->option('tags');

        if ($tagsOption !== []) {
            $unknown = array_values(array_diff($tagsOption, $allTags));

            if ($unknown !== []) {
                $this->components->error('Unknown migration publish tag(s): ' . implode(', ', $unknown));

                return [];
            }

            return $tagsOption;
        }

        if ((bool) $this->option('all')) {
            return $allTags;
        }

        if (! $this->input->isInteractive()) {
            $this->components->warn('Non-interactive session: use --all or --tags=...');

            return [];
        }

        $choices = array_merge(['<all>'], $allTags);

        /** @var array<int, string> $selected */
        $selected = $this->choice(
            'Which migration groups do you want to publish?',
            $choices,
            default: '<all>',
            multiple: true,
        );

        if (in_array('<all>', $selected, true)) {
            return $allTags;
        }

        return $selected;
    }
}
