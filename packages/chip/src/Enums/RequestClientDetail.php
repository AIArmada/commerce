<?php

declare(strict_types=1);

namespace AIArmada\Chip\Enums;

/**
 * ClientDetails fields CHIP can request from the payer before payment.
 *
 * Passed under `purchase.request_client_details`. If a value is already
 * present for a field in ClientDetails, CHIP removes it from the list.
 */
enum RequestClientDetail: string
{
    case EMAIL = 'email';
    case PHONE = 'phone';
    case FULL_NAME = 'full_name';
    case PERSONAL_CODE = 'personal_code';
    case BRAND_NAME = 'brand_name';
    case LEGAL_NAME = 'legal_name';
    case REGISTRATION_NUMBER = 'registration_number';
    case TAX_NUMBER = 'tax_number';
    case BANK_ACCOUNT = 'bank_account';
    case BANK_CODE = 'bank_code';
    case BILLING_ADDRESS = 'billing_address';
    case SHIPPING_ADDRESS = 'shipping_address';
}
