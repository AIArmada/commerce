<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;
use AIArmada\Vouchers\Conditions\VoucherCondition;
use AIArmada\Vouchers\Facades\Voucher;
use AIArmada\Vouchers\Stacking\StackingEngine;

// 1. Setup Cart with products
Cart::clear();
Cart::add([
    'id' => 'p1',
    'name' => 'Test Product',
    'price' => 1000000, // RM 10,000
    'quantity' => 1,
]);

echo 'Subtotal: ' . Cart::getRawSubtotal() . "\n";

// 2. Apply LOYAL100
echo "Applying LOYAL100...\n";
Cart::applyVoucher('LOYAL100');
echo 'Total after LOYAL100: ' . Cart::getRawTotal() . "\n";
echo 'Conditions: ' . Cart::getConditions()->keys()->implode(', ') . "\n";
echo 'Dynamic conditions: ' . Cart::getDynamicConditions()->keys()->implode(', ') . "\n";

// 3. Apply WELCOME2024
echo "\nApplying WELCOME2024...\n";

try {
    Cart::applyVoucher('WELCOME2024');
    echo "SUCCESS (no exception thrown)\n";
} catch (\Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}

echo 'Total after WELCOME2024: ' . Cart::getRawTotal() . "\n";
echo 'Conditions: ' . Cart::getConditions()->keys()->implode(', ') . "\n";
echo 'Dynamic conditions: ' . Cart::getDynamicConditions()->keys()->implode(', ') . "\n";

// 4. Debug Stacking Engine directly
$voucher1 = Cart::getVoucherCondition('LOYAL100');
$voucher2Data = Voucher::find('WELCOME2024');
$voucher2 = new VoucherCondition($voucher2Data);

$policy = Cart::getStackingPolicy();
$engine = new StackingEngine($policy);

echo "\nStacking Policy Rules:\n";
print_r($policy->getRules());

$decision = $engine->canAdd($voucher2, collect([$voucher1]), Cart::instance('default'));
echo "\nDecision for WELCOME2024 adding to LOYAL100:\n";
echo $decision->isAllowed() ? 'ALLOWED' : 'DENIED';
if ($decision->isDenied()) {
    echo ' Reason: ' . $decision->reason . "\n";
}
echo "\n";
