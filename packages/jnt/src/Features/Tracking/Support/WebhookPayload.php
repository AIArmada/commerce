<?php

declare(strict_types=1);

namespace AIArmada\Jnt\Features\Tracking\Support;

use AIArmada\Jnt\Exceptions\JntValidationException;

final readonly class WebhookPayload
{
    /** @return array<string, mixed> */
    public static function decode(string $content): array
    {
        $maxBytes = max(1, (int) config('jnt.webhooks.max_biz_content_bytes', 1048576));

        if (mb_strlen($content, '8bit') > $maxBytes) {
            throw JntValidationException::fieldTooLong('bizContent', $maxBytes, mb_strlen($content, '8bit'));
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw JntValidationException::invalidFormat('bizContent', 'valid JSON');
        }

        if (! is_string($decoded['billCode'] ?? null) || mb_trim($decoded['billCode']) === '') {
            throw JntValidationException::requiredFieldMissing('billCode');
        }

        if (! is_array($decoded['details'] ?? null) || ! array_is_list($decoded['details'])) {
            throw JntValidationException::invalidFormat('details', 'list of tracking details');
        }

        if (isset($decoded['txlogisticId']) && ! is_string($decoded['txlogisticId'])) {
            throw JntValidationException::invalidFormat('txlogisticId', 'string');
        }

        $maxDetails = max(1, (int) config('jnt.webhooks.max_details', 500));

        if (count($decoded['details']) > $maxDetails) {
            throw JntValidationException::fieldTooLong('details', $maxDetails, count($decoded['details']));
        }

        foreach ($decoded['details'] as $detail) {
            if (! is_array($detail) || array_is_list($detail)) {
                throw JntValidationException::invalidFormat('details.*', 'tracking detail object');
            }
        }

        return $decoded;
    }
}
