<?php

declare(strict_types=1);

namespace AIArmada\Products\Support;

use Spatie\MediaLibrary\HasMedia;

final class ProductMedia
{
    /**
     * @param  list<string>  $collections
     */
    public static function firstAvailableUrl(HasMedia $model, array $collections, string $conversion): ?string
    {
        foreach ($collections as $collection) {
            $media = $model->getFirstMedia($collection);

            if ($media !== null) {
                return $media->getUrl($conversion);
            }
        }

        return null;
    }
}
