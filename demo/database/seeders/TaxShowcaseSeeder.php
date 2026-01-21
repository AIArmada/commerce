<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\Tax\Enums\ExemptionStatus;
use AIArmada\Tax\Models\TaxClass;
use AIArmada\Tax\Models\TaxExemption;
use AIArmada\Tax\Models\TaxRate;
use AIArmada\Tax\Models\TaxZone;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 🎭 SHOWCASE: Complete Tax System
 *
 * Demonstrates ALL features of the tax package:
 * - Tax Zones (Country, State, Postcode-based)
 * - Tax Classes (Standard, Reduced, Zero-rate)
 * - Tax Rates (Simple, Compound, Shipping)
 * - Tax Exemptions (Pending, Approved, Rejected)
 */
final class TaxShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎭 Creating Tax Showcase Data...');

        // =====================================================================
        // TAX CLASSES
        // =====================================================================
        $this->command->info('  → Creating Tax Classes...');

        $standardClass = TaxClass::create([
            'name' => 'Standard Rate',
            'slug' => 'standard',
            'description' => 'Standard tax rate for most goods and services',
            'is_default' => true,
            'is_active' => true,
            'position' => 1,
        ]);

        $reducedClass = TaxClass::create([
            'name' => 'Reduced Rate',
            'slug' => 'reduced',
            'description' => 'Reduced rate for essential goods',
            'is_default' => false,
            'is_active' => true,
            'position' => 2,
        ]);

        $zeroClass = TaxClass::create([
            'name' => 'Zero Rate',
            'slug' => 'zero',
            'description' => 'Zero-rated goods (exports, basic food items)',
            'is_default' => false,
            'is_active' => true,
            'position' => 3,
        ]);

        $digitalClass = TaxClass::create([
            'name' => 'Digital Services',
            'slug' => 'digital',
            'description' => 'Tax class for digital goods and services',
            'is_default' => false,
            'is_active' => true,
            'position' => 4,
        ]);

        $luxuryClass = TaxClass::create([
            'name' => 'Luxury Goods',
            'slug' => 'luxury',
            'description' => 'Higher tax rate for luxury items',
            'is_default' => false,
            'is_active' => true,
            'position' => 5,
        ]);

        // =====================================================================
        // TAX ZONES
        // =====================================================================
        $this->command->info('  → Creating Tax Zones...');

        // Malaysia - Default Zone
        $malaysiaZone = TaxZone::create([
            'name' => 'Malaysia (All States)',
            'code' => 'MY-ALL',
            'description' => 'Default tax zone for Malaysia',
            'type' => 'country',
            'countries' => ['MY'],
            'states' => null,
            'postcodes' => null,
            'priority' => 10,
            'is_default' => true,
            'is_active' => true,
        ]);

        // Malaysia - Labuan (Special Economic Zone)
        $labuanZone = TaxZone::create([
            'name' => 'Labuan (Special Zone)',
            'code' => 'MY-LBN',
            'description' => 'Labuan International Business and Financial Centre - Special tax treatment',
            'type' => 'state',
            'countries' => ['MY'],
            'states' => ['Labuan'],
            'postcodes' => ['87*'],
            'priority' => 50,
            'is_default' => false,
            'is_active' => true,
        ]);

        // Malaysia - Sabah & Sarawak
        $eastMalaysiaZone = TaxZone::create([
            'name' => 'East Malaysia',
            'code' => 'MY-EAST',
            'description' => 'Sabah and Sarawak states',
            'type' => 'state',
            'countries' => ['MY'],
            'states' => ['Sabah', 'Sarawak'],
            'postcodes' => null,
            'priority' => 40,
            'is_default' => false,
            'is_active' => true,
        ]);

        // Singapore
        $singaporeZone = TaxZone::create([
            'name' => 'Singapore',
            'code' => 'SG',
            'description' => 'Singapore GST Zone',
            'type' => 'country',
            'countries' => ['SG'],
            'states' => null,
            'postcodes' => null,
            'priority' => 20,
            'is_default' => false,
            'is_active' => true,
        ]);

        // Thailand
        $thailandZone = TaxZone::create([
            'name' => 'Thailand',
            'code' => 'TH',
            'description' => 'Thailand VAT Zone',
            'type' => 'country',
            'countries' => ['TH'],
            'states' => null,
            'postcodes' => null,
            'priority' => 20,
            'is_default' => false,
            'is_active' => true,
        ]);

        // Indonesia
        $indonesiaZone = TaxZone::create([
            'name' => 'Indonesia',
            'code' => 'ID',
            'description' => 'Indonesia PPN Zone',
            'type' => 'country',
            'countries' => ['ID'],
            'states' => null,
            'postcodes' => null,
            'priority' => 20,
            'is_default' => false,
            'is_active' => true,
        ]);

        // European Union
        $euZone = TaxZone::create([
            'name' => 'European Union',
            'code' => 'EU',
            'description' => 'EU VAT Zone',
            'type' => 'country',
            'countries' => ['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'PT', 'PL'],
            'states' => null,
            'postcodes' => null,
            'priority' => 15,
            'is_default' => false,
            'is_active' => true,
        ]);

        // International (Rest of World)
        $rowZone = TaxZone::create([
            'name' => 'Rest of World',
            'code' => 'ROW',
            'description' => 'International sales - typically zero-rated for exports',
            'type' => 'country',
            'countries' => [],
            'states' => null,
            'postcodes' => null,
            'priority' => 1,
            'is_default' => false,
            'is_active' => true,
        ]);

        // =====================================================================
        // TAX RATES
        // =====================================================================
        $this->command->info('  → Creating Tax Rates...');

        // Malaysia Standard Rates (SST = 10% for services, 6% for goods)
        TaxRate::create([
            'zone_id' => $malaysiaZone->id,
            'tax_class' => 'standard',
            'name' => 'SST Standard (Goods)',
            'description' => 'Sales and Service Tax for standard goods',
            'rate' => 600, // 6.00%
            'is_compound' => false,
            'is_shipping' => true,
            'priority' => 10,
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $malaysiaZone->id,
            'tax_class' => 'digital',
            'name' => 'SST Digital Services',
            'description' => 'Digital service tax',
            'rate' => 800, // 8.00%
            'is_compound' => false,
            'is_shipping' => false,
            'priority' => 10,
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $malaysiaZone->id,
            'tax_class' => 'reduced',
            'name' => 'SST Reduced',
            'description' => 'Reduced rate for essential goods',
            'rate' => 0, // 0%
            'is_compound' => false,
            'is_shipping' => false,
            'priority' => 10,
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $malaysiaZone->id,
            'tax_class' => 'luxury',
            'name' => 'Luxury Tax',
            'description' => 'Additional tax for luxury goods',
            'rate' => 1000, // 10.00%
            'is_compound' => true, // Applied after standard tax
            'is_shipping' => false,
            'priority' => 5,
            'is_active' => true,
        ]);

        // Labuan Special Rates
        TaxRate::create([
            'zone_id' => $labuanZone->id,
            'tax_class' => 'standard',
            'name' => 'Labuan Tax Incentive',
            'description' => 'Special economic zone - reduced rate',
            'rate' => 300, // 3.00%
            'is_compound' => false,
            'is_shipping' => true,
            'priority' => 10,
            'is_active' => true,
        ]);

        // East Malaysia Rates
        TaxRate::create([
            'zone_id' => $eastMalaysiaZone->id,
            'tax_class' => 'standard',
            'name' => 'East Malaysia SST',
            'description' => 'Slightly lower rate for East Malaysia',
            'rate' => 500, // 5.00%
            'is_compound' => false,
            'is_shipping' => true,
            'priority' => 10,
            'is_active' => true,
        ]);

        // Singapore GST
        TaxRate::create([
            'zone_id' => $singaporeZone->id,
            'tax_class' => 'standard',
            'name' => 'Singapore GST',
            'description' => 'Goods and Services Tax',
            'rate' => 900, // 9.00%
            'is_compound' => false,
            'is_shipping' => true,
            'priority' => 10,
            'is_active' => true,
        ]);

        // Thailand VAT
        TaxRate::create([
            'zone_id' => $thailandZone->id,
            'tax_class' => 'standard',
            'name' => 'Thailand VAT',
            'description' => 'Value Added Tax',
            'rate' => 700, // 7.00%
            'is_compound' => false,
            'is_shipping' => true,
            'priority' => 10,
            'is_active' => true,
        ]);

        // Indonesia PPN
        TaxRate::create([
            'zone_id' => $indonesiaZone->id,
            'tax_class' => 'standard',
            'name' => 'Indonesia PPN',
            'description' => 'Pajak Pertambahan Nilai',
            'rate' => 1100, // 11.00%
            'is_compound' => false,
            'is_shipping' => true,
            'priority' => 10,
            'is_active' => true,
        ]);

        // EU VAT (Average)
        TaxRate::create([
            'zone_id' => $euZone->id,
            'tax_class' => 'standard',
            'name' => 'EU VAT Standard',
            'description' => 'Standard EU VAT Rate',
            'rate' => 2000, // 20.00%
            'is_compound' => false,
            'is_shipping' => true,
            'priority' => 10,
            'is_active' => true,
        ]);

        TaxRate::create([
            'zone_id' => $euZone->id,
            'tax_class' => 'reduced',
            'name' => 'EU VAT Reduced',
            'description' => 'Reduced VAT for essential goods',
            'rate' => 700, // 7.00%
            'is_compound' => false,
            'is_shipping' => false,
            'priority' => 10,
            'is_active' => true,
        ]);

        // Rest of World - Zero Rate (Export)
        TaxRate::create([
            'zone_id' => $rowZone->id,
            'tax_class' => 'standard',
            'name' => 'Export (Zero Rate)',
            'description' => 'Zero-rated for international exports',
            'rate' => 0, // 0%
            'is_compound' => false,
            'is_shipping' => false,
            'priority' => 10,
            'is_active' => true,
        ]);

        // =====================================================================
        // TAX EXEMPTIONS
        // =====================================================================
        $this->command->info('  → Creating Tax Exemptions...');

        // Get a user for exemptions
        $user = User::first();

        if ($user !== null) {
            // Approved exemption - Government organization
            TaxExemption::create([
                'exemptable_id' => $user->id,
                'exemptable_type' => User::class,
                'tax_zone_id' => $malaysiaZone->id,
                'reason' => 'Government Educational Institution',
                'certificate_number' => 'GOV-EDU-2024-001234',
                'document_path' => null,
                'status' => ExemptionStatus::Approved,
                'rejection_reason' => null,
                'verified_at' => now()->subDays(30),
                'verified_by' => null,
                'starts_at' => now()->subDays(30),
                'expires_at' => now()->addYear(),
            ]);

            // Pending exemption - Business
            TaxExemption::create([
                'exemptable_id' => $user->id,
                'exemptable_type' => User::class,
                'tax_zone_id' => null, // All zones
                'reason' => 'Registered Non-Profit Organization',
                'certificate_number' => 'NPO-2024-005678',
                'document_path' => null,
                'status' => ExemptionStatus::Pending,
                'rejection_reason' => null,
                'verified_at' => null,
                'verified_by' => null,
                'starts_at' => now(),
                'expires_at' => now()->addYears(2),
            ]);

            // Rejected exemption
            TaxExemption::create([
                'exemptable_id' => $user->id,
                'exemptable_type' => User::class,
                'tax_zone_id' => $singaporeZone->id,
                'reason' => 'Diplomatic Mission',
                'certificate_number' => 'DIP-2024-INVALID',
                'document_path' => null,
                'status' => ExemptionStatus::Rejected,
                'rejection_reason' => 'Certificate number could not be verified with issuing authority. Please provide a valid diplomatic ID.',
                'verified_at' => now()->subDays(7),
                'verified_by' => null,
                'starts_at' => now(),
                'expires_at' => now()->addYear(),
            ]);

            // Expired exemption (Approved but past expiry)
            TaxExemption::create([
                'exemptable_id' => $user->id,
                'exemptable_type' => User::class,
                'tax_zone_id' => $malaysiaZone->id,
                'reason' => 'Pioneer Status (Expired)',
                'certificate_number' => 'PIONEER-2022-001',
                'document_path' => null,
                'status' => ExemptionStatus::Approved,
                'rejection_reason' => null,
                'verified_at' => now()->subYears(2),
                'verified_by' => null,
                'starts_at' => now()->subYears(2),
                'expires_at' => now()->subMonths(6), // Already expired
            ]);
        }

        $this->command->info('✅ Tax Showcase Complete!');
        $this->command->info('   - ' . TaxClass::count() . ' Tax Classes');
        $this->command->info('   - ' . TaxZone::count() . ' Tax Zones');
        $this->command->info('   - ' . TaxRate::count() . ' Tax Rates');
        $this->command->info('   - ' . TaxExemption::count() . ' Tax Exemptions');
    }
}
