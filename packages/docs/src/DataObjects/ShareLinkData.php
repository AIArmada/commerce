<?php

declare(strict_types=1);

namespace AIArmada\Docs\DataObjects;

use AIArmada\Docs\Enums\ShareLinkAction;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class ShareLinkData
{
    /**
     * @param  array<ShareLinkAction|string>  $allowedActions
     */
    public function __construct(
        public array $allowedActions = [ShareLinkAction::View],
        public ?DateTimeInterface $expiresAt = null,
    ) {}

    /**
     * @return array<string>
     */
    public function allowedActionValues(): array
    {
        $values = array_values(array_unique(array_map(
            static function (ShareLinkAction | string $action): string {
                $value = $action instanceof ShareLinkAction ? $action->value : $action;

                if (ShareLinkAction::tryFrom($value) === null) {
                    throw new InvalidArgumentException("Unsupported document share action [{$value}].");
                }

                return $value;
            },
            $this->allowedActions,
        )));

        if ($values === []) {
            throw new InvalidArgumentException('Document share links require at least one allowed action.');
        }

        return $values;
    }
}
