<?php

declare(strict_types=1);

namespace AIArmada\FilamentEvents\Extensions;

use AIArmada\Events\Support\ModelResolver;
use AIArmada\FilamentEvents\Contracts\EventFormExtension;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Spatie\MediaLibrary\HasMedia;

/**
 * Default event media section: cover, poster, and gallery uploads.
 *
 * Registered out of the box via `filament-events.resources.event_form_extensions`.
 * Hosts with different media needs can remove or replace it in config; the
 * collections and conversions come from the `events` package media profile.
 * Renders nothing when the resolved event model does not support media.
 */
final class DefaultEventMediaExtension implements EventFormExtension
{
    /**
     * @return array<int, Component>
     */
    public function components(): array
    {
        if (! is_a(ModelResolver::eventClass(), HasMedia::class, true)) {
            return [];
        }

        return [
            Section::make('Media')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('cover')
                        ->label('Cover image')
                        ->collection('cover')
                        ->image()
                        ->imageEditor()
                        ->imageAspectRatio('16:9')
                        ->automaticallyOpenImageEditorForAspectRatio()
                        ->imageEditorAspectRatioOptions(['16:9', null])
                        ->automaticallyCropImagesToAspectRatio()
                        ->conversion('thumb')
                        ->responsiveImages(),
                    SpatieMediaLibraryFileUpload::make('poster')
                        ->label('Poster')
                        ->collection('poster')
                        ->image()
                        ->imageEditor()
                        ->imageAspectRatio('3:4')
                        ->automaticallyOpenImageEditorForAspectRatio()
                        ->imageEditorAspectRatioOptions(['3:4', null])
                        ->automaticallyCropImagesToAspectRatio()
                        ->rules(['dimensions:ratio=3/4'])
                        ->conversion('poster_thumb')
                        ->responsiveImages(),
                    SpatieMediaLibraryFileUpload::make('gallery')
                        ->label('Gallery')
                        ->collection('gallery')
                        ->multiple()
                        ->reorderable()
                        ->maxFiles(10)
                        ->image()
                        ->imageEditor()
                        ->conversion('gallery_thumb')
                        ->responsiveImages(),
                ])
                ->columns(2),
        ];
    }
}
