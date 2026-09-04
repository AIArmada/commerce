<?php

declare(strict_types=1);

namespace AIArmada\Chip\Enums;

/**
 * Event hooks supported by CHIP Send webhooks.
 *
 * @see https://docs.chip-in.asia/chip-send/api-reference/webhooks/create
 */
enum SendWebhookHook: string
{
    case BANK_ACCOUNT_STATUS = 'bank_account_status';
    case BUDGET_ALLOCATION_STATUS = 'budget_allocation_status';
    case SEND_INSTRUCTION_STATUS = 'send_instruction_status';
}
