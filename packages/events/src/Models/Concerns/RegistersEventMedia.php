<?php

declare(strict_types=1);

namespace AIArmada\Events\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait RegistersEventMedia
{
    public function registerMediaCollections(): void
    {
        /** @var array{collections?:array<string,array<string,mixed>>} $profile */
        $profile = config('events.media.profiles.' . $this->eventMediaProfile(), []);

        foreach ($profile['collections'] ?? [] as $name => $options) {
            if (! is_string($name) || ! is_array($options)) {
                continue;
            }

            $collection = $this->addMediaCollection($name);

            $disk = $options['disk'] ?? config('media-library.disk_name');
            if (is_string($disk) && $disk !== '') {
                $collection->useDisk($disk);
            }

            $mimes = $options['mimes'] ?? [];
            if (is_array($mimes)) {
                /** @var list<string> $mimes */
                $mimes = array_values(array_filter($mimes, static fn (mixed $mime): bool => is_string($mime) && $mime !== ''));

                if ($mimes !== []) {
                    $collection->acceptsMimeTypes($mimes);
                }
            }

            if (($options['responsive'] ?? false) === true) {
                $collection->withResponsiveImages();
            }

            if (($options['single_file'] ?? false) === true) {
                $collection->singleFile();
            }

            if (is_string($options['fallback_url'] ?? null)) {
                $collection->useFallbackUrl($options['fallback_url']);
            }

            if (is_string($options['fallback_path'] ?? null)) {
                $collection->useFallbackPath($options['fallback_path']);
            }

            if (is_int($options['limit'] ?? null) && $options['limit'] > 0) {
                $collection->onlyKeepLatest($options['limit']);
            }
        }
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        /** @var array{conversions?:array<string,array<string,mixed>>} $profile */
        $profile = config('events.media.profiles.' . $this->eventMediaProfile(), []);

        foreach ($profile['conversions'] ?? [] as $name => $options) {
            if (! is_string($name) || ! is_array($options)) {
                continue;
            }

            $conversion = $this->addMediaConversion($name);

            $collections = $options['collections'] ?? [];
            if (is_string($collections)) {
                $collections = [$collections];
            }

            if (is_array($collections)) {
                /** @var list<string> $collections */
                $collections = array_values(array_filter($collections, static fn (mixed $collection): bool => is_string($collection) && $collection !== ''));

                if ($collections !== []) {
                    $conversion->performOnCollections(...$collections);
                }
            }

            $fit = $options['fit'] ?? null;
            $width = $options['width'] ?? null;
            $height = $options['height'] ?? null;

            if (is_string($fit) && is_int($width) && is_int($height)) {
                $conversion->fit(Fit::from($fit), $width, $height);
            } else {
                if (is_int($width)) {
                    $conversion->width($width);
                }

                if (is_int($height)) {
                    $conversion->height($height);
                }
            }

            if (is_int($options['sharpen'] ?? null)) {
                $conversion->sharpen($options['sharpen']);
            }

            if (is_string($options['format'] ?? null)) {
                $conversion->format($options['format']);
            }
        }
    }

    protected function eventMediaProfile(): string
    {
        return 'event';
    }
}
