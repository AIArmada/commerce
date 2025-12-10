<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * 🎭 COMMERCE DEMO DATABASE SEEDER
 *
 * Seeds all demo data in the correct order:
 * 1. Users & Permissions (authz showcase)
 * 2. Products & Categories (catalog)
 * 3. Inventory (multi-location warehouse demo)
 * 4. Orders (commerce history)
 * 5. Showcase (vouchers, affiliates, etc.)
 * 6. Billing (subscription demos)
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('🎭 ═══════════════════════════════════════════════════════════════');
        $this->command->info('   AIARMADA COMMERCE DEMO - Building Your Empire...');
        $this->command->info('═══════════════════════════════════════════════════════════════ 🎭');
        $this->command->info('');

        $this->call([
            // 1. Permissions & Roles (must be first!)
            PermissionSeeder::class,

            // 2. Users & Authentication
            UserSeeder::class,

            // 3. Product Catalog
            CategorySeeder::class,
            ProductSeeder::class,

            // 4. Inventory System (multi-location warehouses)
            InventorySeeder::class,

            // 5. Orders & Commerce
            OrderSeeder::class,

            // 6. Showcases (Vouchers, Affiliates)
            ShowcaseSeeder::class,

            // 7. Gift Cards (store credit system)
            GiftCardSeeder::class,

            // 8. J&T Express Shipping (tracking demo)
            JntShippingSeeder::class,

            // 9. Billing & Subscriptions
            BillingShowcaseSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('✨ ═══════════════════════════════════════════════════════════════');
        $this->command->info('   DEMO SEEDING COMPLETE!');
        $this->command->info('');
        $this->command->info('   🔐 Login Credentials (password: "password"):');
        $this->command->info('   • admin@commerce.demo     - Super Admin');
        $this->command->info('   • manager@commerce.demo   - Operations Manager');
        $this->command->info('   • warehouse@commerce.demo - Inventory Manager');
        $this->command->info('   • marketing@commerce.demo - Marketing Manager');
        $this->command->info('   • finance@commerce.demo   - Finance Manager');
        $this->command->info('   • support@commerce.demo   - Customer Support');
        $this->command->info('   • viewer@commerce.demo    - Analyst (Read-only)');
        $this->command->info('');
        $this->command->info('   🌐 URLs:');
        $this->command->info('   • Admin:  /admin');
        $this->command->info('   • Shop:   /');
        $this->command->info('═══════════════════════════════════════════════════════════════ ✨');
        $this->command->info('');
    }
}
