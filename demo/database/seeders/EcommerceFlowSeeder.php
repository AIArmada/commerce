<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\Cart\Models\CartModel as Cart;
use AIArmada\Customers\Models\Customer;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\States\Completed;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use AIArmada\Products\Models\Product;
use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 🎭 SHOWCASE: E-Commerce Flow with Tax Effects
 *
 * Demonstrates how tax works in the complete e-commerce journey:
 * - Customers from different zones (Malaysia, Singapore, Thailand, Indonesia)
 * - Carts with mixed products (standard, digital, luxury tax classes)
 * - Orders showing tax calculation effects
 * - Tax-exempt customers
 */
final class EcommerceFlowSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎭 Creating E-Commerce Flow with Tax Showcase...');

        // Ensure tax zones and classes exist
        $mys = TaxZone::where('code', 'MY-ALL')->first();
        $sgp = TaxZone::where('code', 'SG')->first();
        $tha = TaxZone::where('code', 'TH')->first();
        $idn = TaxZone::where('code', 'ID')->first();

        $standardClass = TaxClass::where('slug', 'standard')->first();
        $digitalClass = TaxClass::where('slug', 'digital')->first();
        $luxuryClass = TaxClass::where('slug', 'luxury')->first();
        if (! $mys || ! $sgp || ! $tha || ! $idn) {
            $this->command->error('Tax zones not found. Please run TaxShowcaseSeeder first.');

            return;
        }

        if (! $standardClass || ! $digitalClass || ! $luxuryClass) {
            $this->command->error('Tax classes not found. Please run TaxShowcaseSeeder first.');

            return;
        }

        // Get products
        $iphone = Product::where('sku', 'IP16-PROMAX')->first();
        $software = Product::where('sku', 'MS365-FAM')->first();
        $luxuryWatch = Product::where('sku', 'WATCH-LUX-001')->first();
        $tshirt = Product::where('sku', 'TSHIRT-PREM')->first();

        if (! $iphone || ! $software || ! $luxuryWatch || ! $tshirt) {
            $this->command->error('Products not found. Please run ProductShowcaseSeeder first.');

            return;
        }

        // =====================================================================
        // SCENARIO 1: Malaysian Customer - Standard SST
        // =====================================================================
        $this->command->info('  → Scenario 1: Malaysian Customer (SST 8%)...');

        $malaysianCustomer = Customer::firstOrCreate(
            ['email' => 'ahmad@example.my'],
            [
            'first_name' => 'Ahmad',
            'last_name' => 'bin Abdullah',
            'phone' => '+60123456789',
            'metadata' => [
                'date_of_birth' => '1985-05-15',
                'country' => 'MY',
                'tax_zone' => 'MY-ALL',
                'address' => [
                    'line1' => 'No 123, Jalan Bukit Bintang',
                    'city' => 'Kuala Lumpur',
                    'state' => 'Wilayah Persekutuan',
                    'postcode' => '50200',
                    'country' => 'MY',
                ],
            ],
        ]);

        // Cart with iPhone and T-Shirt (both standard tax 8%)
        $cart1Subtotal = 629900 + 8900; // RM6299 + RM89
        $cart1Tax = (int) round($cart1Subtotal * 0.08); // 8% SST
        $cart1Total = $cart1Subtotal + $cart1Tax;

        $cart1 = Cart::create([
            'identifier' => (string) Str::uuid(),
            'owner_type' => Customer::class,
            'owner_id' => $malaysianCustomer->id,
            'metadata' => [
                'currency' => 'MYR',
                'tax_zone' => 'MY-ALL',
                'subtotal' => $cart1Subtotal,
                'tax_total' => $cart1Tax,
                'grand_total' => $cart1Total,
                'tax_breakdown' => [
                    ['class' => 'standard', 'rate' => 800, 'amount' => $cart1Tax],
                ],
            ],
            'items' => [
                [
                    'id' => $iphone->id,
                    'name' => $iphone->name,
                    'price' => 629900,
                    'quantity' => 1,
                    'attributes' => [
                        'sku' => $iphone->sku,
                        'tax_class' => 'standard',
                        'tax_rate' => 800,
                        'tax_amount' => (int) round(629900 * 0.08),
                        'total' => (int) round(629900 * 1.08),
                    ],
                    'associated_model' => $iphone->getMorphClass(),
                ],
                [
                    'id' => $tshirt->id,
                    'name' => $tshirt->name,
                    'price' => 8900,
                    'quantity' => 1,
                    'attributes' => [
                        'sku' => $tshirt->sku,
                        'tax_class' => 'standard',
                        'tax_rate' => 800,
                        'tax_amount' => (int) round(8900 * 0.08),
                        'total' => (int) round(8900 * 1.08),
                    ],
                    'associated_model' => $tshirt->getMorphClass(),
                ],
            ],
        ]);

        // Create completed order
        $order1 = Order::create([
            'customer_id' => $malaysianCustomer->id,
            'customer_type' => $malaysianCustomer->getMorphClass(),
            'order_number' => 'ORD-MY-001',
            'status' => Completed::class,
            'currency' => 'MYR',
            'subtotal' => $cart1Subtotal,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => $cart1Tax,
            'grand_total' => $cart1Total,
            'paid_at' => now(),
            'metadata' => [
                'tax_zone' => 'MY-ALL',
                'tax_breakdown' => $cart1->metadata['tax_breakdown'],
                'shipping_address' => $malaysianCustomer->metadata['address'],
                'billing_address' => $malaysianCustomer->metadata['address'],
            ],
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'purchasable_id' => $iphone->id,
            'purchasable_type' => $iphone->getMorphClass(),
            'name' => $iphone->name,
            'sku' => $iphone->sku,
            'quantity' => 1,
            'unit_price' => 629900,
            'discount_amount' => 0,
            'tax_amount' => (int) round(629900 * 0.08),
            'total' => (int) round(629900 * 1.08),
            'currency' => 'MYR',
            'metadata' => ['tax_class' => 'standard', 'tax_rate' => 800],
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'purchasable_id' => $tshirt->id,
            'purchasable_type' => $tshirt->getMorphClass(),
            'name' => $tshirt->name,
            'sku' => $tshirt->sku,
            'quantity' => 1,
            'unit_price' => 8900,
            'discount_amount' => 0,
            'tax_amount' => (int) round(8900 * 0.08),
            'total' => (int) round(8900 * 1.08),
            'currency' => 'MYR',
            'metadata' => ['tax_class' => 'standard', 'tax_rate' => 800],
        ]);

        // =====================================================================
        // SCENARIO 2: Singaporean Customer - GST 9%
        // =====================================================================
        $this->command->info('  → Scenario 2: Singaporean Customer (GST 9%)...');

        $singaporeCustomer = Customer::firstOrCreate(
            ['email' => 'weiming@example.sg'],
            [
            'first_name' => 'Tan',
            'last_name' => 'Wei Ming',
            'phone' => '+6598765432',
            'metadata' => [
                'date_of_birth' => '1990-08-20',
                'country' => 'SG',
                'tax_zone' => 'SG',
                'address' => [
                    'line1' => '123 Orchard Road',
                    'city' => 'Singapore',
                    'postcode' => '238858',
                    'country' => 'SG',
                ],
            ],
        ]);

        // Cart with Luxury Watch (10% luxury tax + 9% GST = effective higher tax)
        $cart2Subtotal = 899900; // RM8,999
        $cart2Tax = (int) round($cart2Subtotal * 0.10); // 10% luxury tax (simplified)
        $cart2Total = $cart2Subtotal + $cart2Tax;
        $cart2Shipping = 2500; // RM25 international

        $cart2 = Cart::create([
            'identifier' => (string) Str::uuid(),
            'owner_type' => Customer::class,
            'owner_id' => $singaporeCustomer->id,
            'metadata' => [
                'currency' => 'MYR',
                'tax_zone' => 'SG',
                'subtotal' => $cart2Subtotal,
                'tax_total' => $cart2Tax,
                'grand_total' => $cart2Total,
                'tax_breakdown' => [
                    ['class' => 'luxury', 'rate' => 1000, 'amount' => $cart2Tax],
                ],
            ],
            'items' => [
                [
                    'id' => $luxuryWatch->id,
                    'name' => $luxuryWatch->name,
                    'price' => $cart2Subtotal,
                    'quantity' => 1,
                    'attributes' => [
                        'sku' => $luxuryWatch->sku,
                        'tax_class' => 'luxury',
                        'tax_rate' => 1000,
                        'tax_amount' => $cart2Tax,
                        'total' => $cart2Total,
                    ],
                    'associated_model' => $luxuryWatch->getMorphClass(),
                ],
            ],
        ]);

        $order2 = Order::create([
            'customer_id' => $singaporeCustomer->id,
            'customer_type' => $singaporeCustomer->getMorphClass(),
            'order_number' => 'ORD-SG-001',
            'status' => Processing::class,
            'currency' => 'MYR',
            'subtotal' => $cart2Subtotal,
            'discount_total' => 0,
            'shipping_total' => $cart2Shipping,
            'tax_total' => $cart2Tax,
            'grand_total' => $cart2Total + $cart2Shipping,
            'metadata' => [
                'tax_zone' => 'SG',
                'tax_breakdown' => $cart2->metadata['tax_breakdown'],
                'shipping_address' => $singaporeCustomer->metadata['address'],
            ],
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'purchasable_id' => $luxuryWatch->id,
            'purchasable_type' => $luxuryWatch->getMorphClass(),
            'name' => $luxuryWatch->name,
            'sku' => $luxuryWatch->sku,
            'quantity' => 1,
            'unit_price' => $cart2Subtotal,
            'discount_amount' => 0,
            'tax_amount' => $cart2Tax,
            'total' => $cart2Total,
            'currency' => 'MYR',
            'metadata' => ['tax_class' => 'luxury', 'tax_rate' => 1000],
        ]);

        // =====================================================================
        // SCENARIO 3: Thai Customer - VAT 7%
        // =====================================================================
        $this->command->info('  → Scenario 3: Thai Customer (VAT 7%)...');

        $thaiCustomer = Customer::firstOrCreate(
            ['email' => 'somchai@example.th'],
            [
            'first_name' => 'Somchai',
            'last_name' => 'Phongsuwan',
            'phone' => '+66812345678',
            'metadata' => [
                'date_of_birth' => '1988-03-12',
                'country' => 'TH',
                'tax_zone' => 'TH',
                'address' => [
                    'line1' => '456 Sukhumvit Road',
                    'city' => 'Bangkok',
                    'postcode' => '10110',
                    'country' => 'TH',
                ],
            ],
        ]);

        // Cart with Digital Product (6% digital service tax)
        $cart3Subtotal = 43900; // RM439
        $cart3Tax = (int) round($cart3Subtotal * 0.06); // 6% digital tax
        $cart3Total = $cart3Subtotal + $cart3Tax;

        $cart3 = Cart::create([
            'identifier' => (string) Str::uuid(),
            'owner_type' => Customer::class,
            'owner_id' => $thaiCustomer->id,
            'metadata' => [
                'currency' => 'MYR',
                'tax_zone' => 'TH',
                'subtotal' => $cart3Subtotal,
                'tax_total' => $cart3Tax,
                'grand_total' => $cart3Total,
                'tax_breakdown' => [
                    ['class' => 'digital', 'rate' => 600, 'amount' => $cart3Tax],
                ],
            ],
            'items' => [
                [
                    'id' => $software->id,
                    'name' => $software->name,
                    'price' => $cart3Subtotal,
                    'quantity' => 1,
                    'attributes' => [
                        'sku' => $software->sku,
                        'tax_class' => 'digital',
                        'tax_rate' => 600,
                        'tax_amount' => $cart3Tax,
                        'total' => $cart3Total,
                    ],
                    'associated_model' => $software->getMorphClass(),
                ],
            ],
        ]);

        $order3 = Order::create([
            'customer_id' => $thaiCustomer->id,
            'customer_type' => $thaiCustomer->getMorphClass(),
            'order_number' => 'ORD-TH-001',
            'status' => Processing::class,
            'currency' => 'MYR',
            'subtotal' => $cart3Subtotal,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => $cart3Tax,
            'grand_total' => $cart3Total,
            'metadata' => [
                'tax_zone' => 'TH',
                'tax_breakdown' => $cart3->metadata['tax_breakdown'],
                'shipping_address' => $thaiCustomer->metadata['address'],
            ],
        ]);

        OrderItem::create([
            'order_id' => $order3->id,
            'purchasable_id' => $software->id,
            'purchasable_type' => $software->getMorphClass(),
            'name' => $software->name,
            'sku' => $software->sku,
            'quantity' => 1,
            'unit_price' => $cart3Subtotal,
            'discount_amount' => 0,
            'tax_amount' => $cart3Tax,
            'total' => $cart3Total,
            'currency' => 'MYR',
            'metadata' => ['tax_class' => 'digital', 'tax_rate' => 600],
        ]);

        // =====================================================================
        // SCENARIO 4: Tax-Exempt Educational Institution (Malaysia)
        // =====================================================================
        $this->command->info('  → Scenario 4: Tax-Exempt Customer (Educational)...');

        $educationCustomer = Customer::firstOrCreate(
            ['email' => 'procurement@um.edu.my'],
            [
            'first_name' => 'University of',
            'last_name' => 'Malaya',
            'phone' => '+60379676011',
            'metadata' => [
                'country' => 'MY',
                'tax_zone' => 'MY-ALL',
                'type' => 'institution',
                'tax_exempt' => true,
                'exemption_certificate' => 'EDU-EXEMPT-2025-001',
                'address' => [
                    'line1' => 'Lembah Pantai',
                    'city' => 'Kuala Lumpur',
                    'state' => 'Wilayah Persekutuan',
                    'postcode' => '50603',
                    'country' => 'MY',
                ],
            ],
        ]);

        // Cart with multiple items - NO TAX applied due to exemption
        $cart4Subtotal = (629900 * 2) + (43900 * 5); // 2 iPhones + 5 software licenses
        $cart4Tax = 0;
        $cart4Total = $cart4Subtotal;

        $cart4 = Cart::create([
            'identifier' => (string) Str::uuid(),
            'owner_type' => Customer::class,
            'owner_id' => $educationCustomer->id,
            'metadata' => [
                'currency' => 'MYR',
                'tax_zone' => 'MY-ALL',
                'subtotal' => $cart4Subtotal,
                'tax_total' => $cart4Tax,
                'grand_total' => $cart4Total,
                'tax_exempt' => true,
                'exemption_reason' => 'Educational Institution',
                'exemption_certificate' => 'EDU-EXEMPT-2025-001',
                'tax_breakdown' => [],
            ],
            'items' => [
                [
                    'id' => $iphone->id,
                    'name' => $iphone->name,
                    'price' => 629900,
                    'quantity' => 2,
                    'attributes' => [
                        'sku' => $iphone->sku,
                        'tax_class' => 'standard',
                        'tax_rate' => 0,
                        'tax_amount' => 0,
                        'total' => 629900 * 2,
                        'tax_exempt' => true,
                    ],
                    'associated_model' => $iphone->getMorphClass(),
                ],
                [
                    'id' => $software->id,
                    'name' => $software->name,
                    'price' => 43900,
                    'quantity' => 5,
                    'attributes' => [
                        'sku' => $software->sku,
                        'tax_class' => 'digital',
                        'tax_rate' => 0,
                        'tax_amount' => 0,
                        'total' => 43900 * 5,
                        'tax_exempt' => true,
                    ],
                    'associated_model' => $software->getMorphClass(),
                ],
            ],
        ]);

        $order4 = Order::create([
            'customer_id' => $educationCustomer->id,
            'customer_type' => $educationCustomer->getMorphClass(),
            'order_number' => 'ORD-MY-EDU-001',
            'status' => PendingPayment::class,
            'currency' => 'MYR',
            'subtotal' => $cart4Subtotal,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => $cart4Total,
            'metadata' => [
                'tax_zone' => 'MY-ALL',
                'tax_exempt' => true,
                'exemption_reason' => 'Educational Institution',
                'exemption_certificate' => 'EDU-EXEMPT-2025-001',
                'tax_savings' => (int) round($cart4Subtotal * 0.08), // Would have paid 8%
                'shipping_address' => $educationCustomer->metadata['address'],
            ],
        ]);

        OrderItem::create([
            'order_id' => $order4->id,
            'purchasable_id' => $iphone->id,
            'purchasable_type' => $iphone->getMorphClass(),
            'name' => $iphone->name,
            'sku' => $iphone->sku,
            'quantity' => 2,
            'unit_price' => 629900,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 629900 * 2,
            'currency' => 'MYR',
            'metadata' => ['tax_class' => 'standard', 'tax_rate' => 0, 'tax_exempt' => true],
        ]);

        OrderItem::create([
            'order_id' => $order4->id,
            'purchasable_id' => $software->id,
            'purchasable_type' => $software->getMorphClass(),
            'name' => $software->name,
            'sku' => $software->sku,
            'quantity' => 5,
            'unit_price' => 43900,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total' => 43900 * 5,
            'currency' => 'MYR',
            'metadata' => ['tax_class' => 'digital', 'tax_rate' => 0, 'tax_exempt' => true],
        ]);

        // =====================================================================
        // SCENARIO 5: Indonesian Customer - Mixed Cart (Standard + Digital)
        // =====================================================================
        $this->command->info('  → Scenario 5: Indonesian Customer (VAT 11%)...');

        $indonesianCustomer = Customer::firstOrCreate(
            ['email' => 'budi@example.id'],
            [
            'first_name' => 'Budi',
            'last_name' => 'Santoso',
            'phone' => '+628123456789',
            'metadata' => [
                'date_of_birth' => '1992-11-05',
                'country' => 'ID',
                'tax_zone' => 'ID',
                'address' => [
                    'line1' => 'Jl. Sudirman No. 789',
                    'city' => 'Jakarta',
                    'postcode' => '12190',
                    'country' => 'ID',
                ],
            ],
        ]);

        // Mixed cart demonstrating different tax classes
        $standardItemTotal = 8900 * 3; // 3 t-shirts
        $digitalItemTotal = 43900; // 1 software
        $standardTax = (int) round($standardItemTotal * 0.11); // 11% VAT
        $digitalTax = (int) round($digitalItemTotal * 0.11); // 11% VAT

        $cart5Subtotal = $standardItemTotal + $digitalItemTotal;
        $cart5Tax = $standardTax + $digitalTax;
        $cart5Total = $cart5Subtotal + $cart5Tax;

        $cart5 = Cart::create([
            'identifier' => (string) Str::uuid(),
            'owner_type' => Customer::class,
            'owner_id' => $indonesianCustomer->id,
            'metadata' => [
                'currency' => 'MYR',
                'tax_zone' => 'ID',
                'subtotal' => $cart5Subtotal,
                'tax_total' => $cart5Tax,
                'grand_total' => $cart5Total,
                'tax_breakdown' => [
                    ['class' => 'standard', 'rate' => 1100, 'amount' => $standardTax],
                    ['class' => 'digital', 'rate' => 1100, 'amount' => $digitalTax],
                ],
            ],
            'items' => [
                [
                    'id' => $tshirt->id,
                    'name' => $tshirt->name,
                    'price' => 8900,
                    'quantity' => 3,
                    'attributes' => [
                        'sku' => $tshirt->sku,
                        'tax_class' => 'standard',
                        'tax_rate' => 1100,
                        'tax_amount' => $standardTax,
                        'total' => $standardItemTotal + $standardTax,
                    ],
                    'associated_model' => $tshirt->getMorphClass(),
                ],
                [
                    'id' => $software->id,
                    'name' => $software->name,
                    'price' => 43900,
                    'quantity' => 1,
                    'attributes' => [
                        'sku' => $software->sku,
                        'tax_class' => 'digital',
                        'tax_rate' => 1100,
                        'tax_amount' => $digitalTax,
                        'total' => $digitalItemTotal + $digitalTax,
                    ],
                    'associated_model' => $software->getMorphClass(),
                ],
            ],
        ]);

        $order5 = Order::create([
            'customer_id' => $indonesianCustomer->id,
            'customer_type' => $indonesianCustomer->getMorphClass(),
            'order_number' => 'ORD-ID-001',
            'status' => Processing::class,
            'currency' => 'MYR',
            'subtotal' => $cart5Subtotal,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => $cart5Tax,
            'grand_total' => $cart5Total,
            'metadata' => [
                'tax_zone' => 'ID',
                'tax_breakdown' => $cart5->metadata['tax_breakdown'],
                'shipping_address' => $indonesianCustomer->metadata['address'],
            ],
        ]);

        OrderItem::create([
            'order_id' => $order5->id,
            'purchasable_id' => $tshirt->id,
            'purchasable_type' => $tshirt->getMorphClass(),
            'name' => $tshirt->name,
            'sku' => $tshirt->sku,
            'quantity' => 3,
            'unit_price' => 8900,
            'discount_amount' => 0,
            'tax_amount' => $standardTax,
            'total' => $standardItemTotal + $standardTax,
            'currency' => 'MYR',
            'metadata' => ['tax_class' => 'standard', 'tax_rate' => 1100],
        ]);

        OrderItem::create([
            'order_id' => $order5->id,
            'purchasable_id' => $software->id,
            'purchasable_type' => $software->getMorphClass(),
            'name' => $software->name,
            'sku' => $software->sku,
            'quantity' => 1,
            'unit_price' => 43900,
            'discount_amount' => 0,
            'tax_amount' => $digitalTax,
            'total' => $digitalItemTotal + $digitalTax,
            'currency' => 'MYR',
            'metadata' => ['tax_class' => 'digital', 'tax_rate' => 1100],
        ]);

        $this->command->info('✅ E-Commerce Flow Showcase Complete!');
        $this->command->info('   - ' . Customer::count() . ' Customers (MY, SG, TH, ID zones)');
        $this->command->info('   - ' . Cart::count() . ' Carts');
        $this->command->info('   - ' . Order::count() . ' Orders');
        $this->command->info('   - ' . OrderItem::count() . ' Order Items');
        $this->command->info('');
        $this->command->info('📊 Tax Scenarios:');
        $this->command->info('   1️⃣  Malaysian: RM' . number_format($cart1Subtotal / 100, 2) . ' → Tax: RM' . number_format($cart1Tax / 100, 2) . ' (8%)');
        $this->command->info('   2️⃣  Singapore: RM' . number_format($cart2Subtotal / 100, 2) . ' → Tax: RM' . number_format($cart2Tax / 100, 2) . ' (10% luxury)');
        $this->command->info('   3️⃣  Thailand: RM' . number_format($cart3Subtotal / 100, 2) . ' → Tax: RM' . number_format($cart3Tax / 100, 2) . ' (6% digital)');
        $this->command->info('   4️⃣  Tax-Exempt: RM' . number_format($cart4Subtotal / 100, 2) . ' → Tax: RM0.00 (Educational)');
        $this->command->info('   5️⃣  Indonesia: RM' . number_format($cart5Subtotal / 100, 2) . ' → Tax: RM' . number_format($cart5Tax / 100, 2) . ' (11% mixed)');
    }
}
