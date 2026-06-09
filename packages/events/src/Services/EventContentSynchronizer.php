<?php

declare(strict_types=1);

namespace AIArmada\Events\Services;

use AIArmada\Events\Contracts\EventAssetResolver;
use AIArmada\Events\Contracts\EventClassificationResolver;
use AIArmada\Events\Contracts\EventReferenceResolver;
use AIArmada\Events\Contracts\EventRelationalContentSubject;
use AIArmada\Events\Support\Normalization\EventContentNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class EventContentSynchronizer
{
    public function __construct(
        private readonly EventClassificationResolver $classificationResolver,
        private readonly EventAssetResolver $assetResolver,
        private readonly EventReferenceResolver $referenceResolver,
    ) {}

    public function sync(EventRelationalContentSubject & Model $subject): void
    {
        DB::transaction(function () use ($subject): void {
            $this->syncClassifications($subject);
            $this->syncAssets($subject);
            $this->syncReferences($subject);
        });
    }

    private function syncClassifications(EventRelationalContentSubject & Model $subject): void
    {
        $assignments = EventContentNormalizer::normalizeClassifications(
            $this->classificationResolver->resolve($subject),
        );

        $subject->classifications()->delete();

        if ($assignments === []) {
            return;
        }

        $subject->classifications()->createMany(array_map(
            static fn (array $assignment): array => [
                'source_type' => $assignment['source_type'] ?? null,
                'source_id' => $assignment['source_id'] ?? null,
                'group_key' => $assignment['group_key'],
                'term_key' => $assignment['term_key'],
                'term_label' => $assignment['term_label'] ?? null,
                'order_column' => $assignment['order_column'] ?? null,
                'metadata' => $assignment['metadata'] ?? null,
            ],
            $assignments,
        ));
    }

    private function syncAssets(EventRelationalContentSubject & Model $subject): void
    {
        $assignments = EventContentNormalizer::normalizeAssets(
            $this->assetResolver->resolve($subject),
        );

        $subject->assets()->delete();

        if ($assignments === []) {
            return;
        }

        $subject->assets()->createMany(array_map(
            static fn (array $assignment): array => [
                'role_key' => $assignment['role_key'],
                'provider' => $assignment['provider'] ?? null,
                'provider_reference' => $assignment['provider_reference'] ?? null,
                'url' => $assignment['url'] ?? null,
                'title' => $assignment['title'] ?? null,
                'alt_text' => $assignment['alt_text'] ?? null,
                'visibility' => $assignment['visibility'] ?? 'public',
                'order_column' => $assignment['order_column'] ?? null,
                'metadata' => $assignment['metadata'] ?? null,
            ],
            $assignments,
        ));
    }

    private function syncReferences(EventRelationalContentSubject & Model $subject): void
    {
        $assignments = EventContentNormalizer::normalizeReferences(
            $this->referenceResolver->resolve($subject),
        );

        $subject->references()->delete();

        if ($assignments === []) {
            return;
        }

        $subject->references()->createMany(array_map(
            static fn (array $assignment): array => [
                'reference_kind' => $assignment['reference_kind'],
                'reference_type' => $assignment['reference_type'] ?? null,
                'reference_id' => $assignment['reference_id'] ?? null,
                'display_label' => $assignment['display_label'] ?? null,
                'source_label' => $assignment['source_label'] ?? null,
                'url' => $assignment['url'] ?? null,
                'order_column' => $assignment['order_column'] ?? null,
                'metadata' => $assignment['metadata'] ?? null,
            ],
            $assignments,
        ));
    }
}
