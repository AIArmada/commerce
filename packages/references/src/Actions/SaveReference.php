<?php

declare(strict_types=1);

namespace AIArmada\References\Actions;

use AIArmada\References\Models\Reference;
use Closure;
use Illuminate\Validation\ValidationException;

final readonly class SaveReference
{
    public function __construct(private LockReferenceForWrite $lock = new LockReferenceForWrite) {}

    /**
     * @param  Closure(): bool  $save
     */
    public function handle(Reference $reference, Closure $save): bool
    {
        return $reference->getConnection()->transaction(function () use ($reference, $save): bool {
            if ($reference->exists) {
                $locked = $this->lock->handle($reference);

                foreach (['record_kind', 'parent_id'] as $attribute) {
                    if ($reference->getRawOriginal($attribute) !== $locked->getRawOriginal($attribute)) {
                        throw ValidationException::withMessages([$attribute => 'The reference hierarchy has changed. Reload the reference before saving.']);
                    }
                }

                if ($reference->getRawOriginal('status') !== $locked->getRawOriginal('status')
                    || $reference->getOriginal('published_at') !== $locked->published_at) {
                    throw ValidationException::withMessages(['status' => 'The reference publication state has changed. Reload the reference before saving.']);
                }
            }

            return $save();
        });
    }
}
