<?php

declare(strict_types=1);

namespace AIArmada\Membership\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class MakePivotCommand extends Command
{
    protected $signature = 'membership:make-pivot {model : The model class name} {--force : Overwrite existing migration}';

    protected $description = 'Create a migration for the membership pivot table of a model.';

    public function handle(Filesystem $files): int
    {
        $model = class_basename((string) $this->argument('model'));
        $table = Str::snake($model) . '_members';
        $subjectIdColumn = Str::snake($model) . '_id';

        $existingPath = collect($files->glob(database_path("migrations/*_create_{$table}_table.php")))
            ->sort()
            ->last();

        if (is_string($existingPath) && ! $this->option('force')) {
            $this->error("Migration already exists at {$existingPath}. Use --force to overwrite.");

            return self::FAILURE;
        }

        $fileName = is_string($existingPath)
            ? basename($existingPath)
            : date('Y_m_d_His') . '_create_' . $table . '_table.php';
        $path = $existingPath ?: database_path('migrations/' . $fileName);

        $stub = $files->get(__DIR__ . '/stubs/make-pivot.stub');

        $stub = str_replace(
            ['{{ table }}', '{{ subject_id_column }}'],
            [$table, $subjectIdColumn],
            $stub,
        );

        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $stub);

        $this->info("Created migration: {$fileName}");

        return self::SUCCESS;
    }
}
