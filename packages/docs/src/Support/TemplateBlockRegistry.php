<?php

declare(strict_types=1);

namespace AIArmada\Docs\Support;

use AIArmada\Docs\Enums\DocTemplateBlockType;
use InvalidArgumentException;

final class TemplateBlockRegistry
{
    /**
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    public static function defaultLayout(): array
    {
        return [
            ['type' => DocTemplateBlockType::DocumentHeader->value, 'data' => []],
            ['type' => DocTemplateBlockType::Parties->value, 'data' => []],
            ['type' => DocTemplateBlockType::DocumentMetadata->value, 'data' => []],
            ['type' => DocTemplateBlockType::RichBody->value, 'data' => []],
            ['type' => DocTemplateBlockType::LineItems->value, 'data' => []],
            ['type' => DocTemplateBlockType::Totals->value, 'data' => []],
            ['type' => DocTemplateBlockType::NotesTerms->value, 'data' => []],
            ['type' => DocTemplateBlockType::Footer->value, 'data' => []],
        ];
    }

    /**
     * @return array<string>
     */
    public static function allowedTypes(): array
    {
        return array_map(
            static fn (DocTemplateBlockType $type): string => $type->value,
            DocTemplateBlockType::cases(),
        );
    }

    /**
     * @param  array<int, mixed>|null  $layout
     */
    public static function hasBlock(?array $layout, DocTemplateBlockType $type): bool
    {
        foreach ($layout ?? [] as $block) {
            if (! is_array($block)) {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            if (($block['type'] ?? null) === $type->value && ($data['visible'] ?? true) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>|null  $layout
     */
    public static function assertValid(?array $layout): void
    {
        if ($layout === null || $layout === []) {
            throw new InvalidArgumentException('Document templates require at least one layout block.');
        }

        $allowedTypes = self::allowedTypes();

        foreach ($layout as $index => $block) {
            if (! is_array($block)) {
                throw new InvalidArgumentException("Document template block at index {$index} must be an object.");
            }

            $topLevelKeys = array_keys($block);
            $unsupportedTopLevelKeys = array_diff($topLevelKeys, ['type', 'data']);

            if ($unsupportedTopLevelKeys !== []) {
                throw new InvalidArgumentException("Document template block at index {$index} contains unsupported keys.");
            }

            $type = $block['type'] ?? null;

            if (! is_string($type) || ! in_array($type, $allowedTypes, true)) {
                throw new InvalidArgumentException("Unsupported document template block at index {$index}.");
            }

            if (array_key_exists('data', $block) && ! is_array($block['data'])) {
                throw new InvalidArgumentException("Document template block [{$type}] must use declarative data.");
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $unsupportedDataKeys = array_diff(array_keys($data), self::allowedDataKeys($type));

            if ($unsupportedDataKeys !== []) {
                throw new InvalidArgumentException("Document template block [{$type}] contains unsupported data keys.");
            }

            self::assertDeclarativePayload($data, "Document template block [{$type}]");
        }
    }

    /**
     * @return array<string>
     */
    private static function allowedDataKeys(string $type): array
    {
        return match ($type) {
            DocTemplateBlockType::DocumentHeader->value => ['visible', 'label'],
            DocTemplateBlockType::Parties->value => ['visible', 'company_label', 'customer_label'],
            DocTemplateBlockType::DocumentMetadata->value => ['visible', 'label'],
            DocTemplateBlockType::RichBody->value => ['visible'],
            DocTemplateBlockType::StaticRichText->value => ['visible', 'content'],
            DocTemplateBlockType::LineItems->value => ['visible', 'label'],
            DocTemplateBlockType::Totals->value => ['visible', 'label'],
            DocTemplateBlockType::NotesTerms->value => ['visible', 'notes_label', 'terms_label'],
            DocTemplateBlockType::SignaturePayment->value => ['visible', 'label', 'body'],
            DocTemplateBlockType::PageBreak->value => ['visible'],
            DocTemplateBlockType::Footer->value => ['visible', 'text'],
            default => [],
        };
    }

    /**
     * @param  array<mixed>  $payload
     */
    private static function assertDeclarativePayload(array $payload, string $context): void
    {
        foreach ($payload as $value) {
            if (is_array($value)) {
                self::assertDeclarativePayload($value, $context);

                continue;
            }

            if (is_scalar($value) || $value === null) {
                continue;
            }

            throw new InvalidArgumentException("{$context} must contain JSON-serializable declarative values only.");
        }
    }
}
