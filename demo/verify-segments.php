<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use AIArmada\Customers\Models\Segment;
use AIArmada\Customers\Enums\SegmentType;
use AIArmada\Customers\Models\Customer;
use AIArmada\Customers\Enums\CustomerStatus;

echo "=== STARTING SEGMENT VERIFICATION ===\n";

// 1. Create Segment
echo "1. Creating Segment...\n";
try {
    $segment = Segment::create([
        'name' => 'Marketing VIPs',
        'slug' => 'marketing-vips-' . time(), // unique
        'type' => SegmentType::Custom,
        'is_automatic' => true,
        'conditions' => [
            ['field' => 'accepts_marketing', 'value_boolean' => true]
        ],
        'is_active' => true,
    ]);
    echo "Segment created: ID {$segment->id}\n";
} catch (\Exception $e) {
    echo "Error creating segment: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Create Matching Customer
echo "2. Creating Matching Customer...\n";
$c1 = Customer::create([
    'first_name' => 'Test',
    'last_name' => 'Marketing',
    'email' => 'marketing-' . time() . '@test.com',
    'accepts_marketing' => true,
    'status' => CustomerStatus::Active
]);
echo "Customer C1 created: {$c1->id} (Accepts Marketing: Yes)\n";

// 3. Create Non-Matching Customer
echo "3. Creating Non-Matching Customer...\n";
$c2 = Customer::create([
    'first_name' => 'Test',
    'last_name' => 'NoMarketing',
    'email' => 'nomarketing-' . time() . '@test.com',
    'accepts_marketing' => false,
    'status' => CustomerStatus::Active
]);
echo "Customer C2 created: {$c2->id} (Accepts Marketing: No)\n";

// 4. Run Logic
echo "4. Running Rebuild Logic...\n";
$count = $segment->rebuildCustomerList();
echo "Rebuilt list. Count: {$count}\n";

// 5. Verification
$segment->load('customers');
$hasC1 = $segment->customers->contains($c1->id);
$hasC2 = $segment->customers->contains($c2->id);

echo "=== RESULTS ===\n";
echo "Has Matching Customer (C1): " . ($hasC1 ? '✅ YES' : '❌ NO') . "\n";
echo "Has Non-Matching Customer (C2): " . ($hasC2 ? '❌ YES' : '✅ NO') . "\n";

if ($hasC1 && !$hasC2) {
    echo "\nSUCCESS: Segment logic matches expectations.\n";
} else {
    echo "\nFAILURE: Segment logic incorrect.\n";
    exit(1);
}
