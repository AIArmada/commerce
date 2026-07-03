<?php

declare(strict_types=1);

namespace AIArmada\FilamentDocs\Rendering;

use AIArmada\Docs\Support\DocRichContentStorage;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\RichContentAttribute;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use LogicException;
use Throwable;

final class DocsRichContentFileAttachmentProvider implements FileAttachmentProvider
{
    public function attribute(RichContentAttribute $attribute): static
    {
        return $this;
    }

    public function getFileAttachmentUrl(mixed $file): ?string
    {
        if (! DocRichContentStorage::isAllowedFileId($file)) {
            return null;
        }

        $disk = (string) config('docs.storage.disk', 'local');
        $visibility = $this->getDefaultFileAttachmentVisibility() ?? 'private';
        $storage = Storage::disk($disk);

        try {
            if (! $storage->exists($file)) {
                return null;
            }
        } catch (UnableToCheckFileExistence) {
            return null;
        }

        if ($visibility === 'private') {
            try {
                return $storage->temporaryUrl(
                    $file,
                    now()->addMinutes(config('filament.temporary_file_url_expiry_minutes', 30))->endOfHour(),
                );
            } catch (Throwable) {
                return null;
            }
        }

        return $storage->url($file);
    }

    public function saveUploadedFileAttachment(TemporaryUploadedFile $file): mixed
    {
        throw new LogicException('Document rich-content attachments are saved by the Filament field, not the renderer.');
    }

    public function getDefaultFileAttachmentVisibility(): ?string
    {
        return (string) config('docs.storage.rich_content_visibility', 'private');
    }

    public function isExistingRecordRequiredToSaveNewFileAttachments(): bool
    {
        return true;
    }

    /**
     * @param  array<mixed>  $exceptIds
     */
    /** @phpstan-ignore-next-line interface contract, cleanup handled by Filament field lifecycle */
    public function cleanUpFileAttachments(array $exceptIds): void {}
}
