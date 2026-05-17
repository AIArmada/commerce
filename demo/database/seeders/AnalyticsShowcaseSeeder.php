<?php

declare(strict_types=1);

namespace Database\Seeders;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Growth\Models\Assignment;
use AIArmada\Growth\Models\Experiment;
use AIArmada\Growth\Models\Variant;
use AIArmada\Signals\Models\SignalEvent;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalSession;
use AIArmada\Signals\Models\TrackedProperty;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

final class AnalyticsShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $owner = OwnerContext::resolve();

        if (! $owner instanceof User) {
            return;
        }

        $trackedProperty = TrackedProperty::query()->firstOrCreate(
            ['slug' => 'commerce-demo-storefront'],
            [
                'name' => 'Commerce Demo Storefront',
                'write_key' => 'demo-storefront-write-key-0000000000000',
                'domain' => 'cdemo.test',
                'type' => 'website',
                'timezone' => 'Asia/Kuala_Lumpur',
                'currency' => 'MYR',
                'is_active' => true,
                'settings' => [
                    'environment' => 'demo',
                    'notes' => 'Used for storefront analytics and growth experimentation.',
                ],
            ],
        );

        $controlIdentity = SignalIdentity::query()->firstOrCreate(
            ['tracked_property_id' => $trackedProperty->id, 'external_id' => 'demo-identity-control'],
            [
                'anonymous_id' => 'anon-demo-control',
                'email' => 'control@commerce.demo',
                'traits' => ['segment' => 'control'],
                'first_seen_at' => CarbonImmutable::now()->subDays(7),
                'last_seen_at' => CarbonImmutable::now()->subHours(3),
            ],
        );

        $variantIdentity = SignalIdentity::query()->firstOrCreate(
            ['tracked_property_id' => $trackedProperty->id, 'external_id' => 'demo-identity-variant'],
            [
                'anonymous_id' => 'anon-demo-variant',
                'email' => 'variant@commerce.demo',
                'traits' => ['segment' => 'variant'],
                'first_seen_at' => CarbonImmutable::now()->subDays(5),
                'last_seen_at' => CarbonImmutable::now()->subHour(),
            ],
        );

        $controlSession = SignalSession::query()->firstOrCreate(
            ['tracked_property_id' => $trackedProperty->id, 'session_identifier' => 'demo-session-control'],
            [
                'signal_identity_id' => $controlIdentity->id,
                'started_at' => CarbonImmutable::now()->subDays(1),
                'ended_at' => CarbonImmutable::now()->subDays(1)->addMinutes(18),
                'duration_milliseconds' => 1_080_000,
                'entry_path' => '/',
                'exit_path' => '/checkout',
                'country' => 'MY',
                'browser' => 'Safari',
                'browser_version' => '17.4',
                'os' => 'macOS',
                'os_version' => '15.0',
                'device_type' => 'desktop',
                'is_bot' => false,
                'is_bounce' => false,
                'ip_address' => '127.0.0.1',
                'referrer' => 'https://cdemo.test/',
                'utm_source' => 'demo',
                'utm_medium' => 'seed',
                'utm_campaign' => 'growth-control',
            ],
        );

        $variantSession = SignalSession::query()->firstOrCreate(
            ['tracked_property_id' => $trackedProperty->id, 'session_identifier' => 'demo-session-variant'],
            [
                'signal_identity_id' => $variantIdentity->id,
                'started_at' => CarbonImmutable::now()->subHours(12),
                'ended_at' => CarbonImmutable::now()->subHours(12)->addMinutes(24),
                'duration_milliseconds' => 1_440_000,
                'entry_path' => '/products',
                'exit_path' => '/order/complete',
                'country' => 'MY',
                'browser' => 'Chrome',
                'browser_version' => '136',
                'os' => 'macOS',
                'os_version' => '15.0',
                'device_type' => 'desktop',
                'is_bot' => false,
                'is_bounce' => false,
                'ip_address' => '127.0.0.1',
                'referrer' => 'https://cdemo.test/products',
                'utm_source' => 'demo',
                'utm_medium' => 'seed',
                'utm_campaign' => 'growth-variant',
            ],
        );

        $experiment = Experiment::query()->firstOrCreate(
            ['slug' => 'storefront-checkout-layout-test'],
            [
                'tracked_property_id' => $trackedProperty->id,
                'name' => 'Storefront Checkout Layout Test',
                'description' => 'Compares a compact checkout summary against a richer conversion-focused summary.',
                'module_type' => 'funnel_test',
                'status' => ExperimentStatus::Active,
                'goal_event_name' => 'order.paid',
                'goal_event_category' => 'conversion',
                'winner_metric' => 'revenue_per_visitor',
                'started_at' => CarbonImmutable::now()->subDays(3),
            ],
        );

        $controlVariant = Variant::query()->firstOrCreate(
            ['experiment_id' => $experiment->id, 'code' => 'A'],
            [
                'name' => 'Control Summary',
                'description' => 'Compact order summary with fewer persuasive elements.',
                'traffic_percentage' => 50,
                'position' => 1,
                'is_control' => true,
                'is_active' => true,
            ],
        );

        $winningVariant = Variant::query()->firstOrCreate(
            ['experiment_id' => $experiment->id, 'code' => 'B'],
            [
                'name' => 'Conversion Summary',
                'description' => 'Higher-context summary with social proof and shipping clarity.',
                'traffic_percentage' => 50,
                'position' => 2,
                'is_control' => false,
                'is_active' => true,
            ],
        );

        $controlAssignment = Assignment::query()->firstOrCreate(
            ['experiment_id' => $experiment->id, 'subject_key' => 'identity:'.$controlIdentity->id],
            [
                'variant_id' => $controlVariant->id,
                'signal_identity_id' => $controlIdentity->id,
                'signal_session_id' => $controlSession->id,
                'bucket' => 18,
                'metadata' => ['channel' => 'demo'],
                'assigned_at' => CarbonImmutable::now()->subDays(1),
                'first_exposed_at' => CarbonImmutable::now()->subDays(1),
                'last_seen_at' => CarbonImmutable::now()->subDays(1)->addMinutes(18),
            ],
        );

        $winningAssignment = Assignment::query()->firstOrCreate(
            ['experiment_id' => $experiment->id, 'subject_key' => 'identity:'.$variantIdentity->id],
            [
                'variant_id' => $winningVariant->id,
                'signal_identity_id' => $variantIdentity->id,
                'signal_session_id' => $variantSession->id,
                'bucket' => 81,
                'metadata' => ['channel' => 'demo'],
                'assigned_at' => CarbonImmutable::now()->subHours(12),
                'first_exposed_at' => CarbonImmutable::now()->subHours(12),
                'last_seen_at' => CarbonImmutable::now()->subHours(11),
            ],
        );

        $this->firstOrCreateEvent(
            trackedProperty: $trackedProperty,
            session: $controlSession,
            identity: $controlIdentity,
            eventName: 'page_view',
            eventCategory: 'page_view',
            occurredAt: CarbonImmutable::now()->subDays(1),
            properties: $this->experimentProperties($experiment, $controlVariant, $controlAssignment, '/checkout'),
        );

        $this->firstOrCreateEvent(
            trackedProperty: $trackedProperty,
            session: $controlSession,
            identity: $controlIdentity,
            eventName: 'checkout.started',
            eventCategory: 'checkout',
            occurredAt: CarbonImmutable::now()->subDays(1)->addMinutes(4),
            properties: $this->experimentProperties($experiment, $controlVariant, $controlAssignment, '/checkout'),
        );

        $this->firstOrCreateEvent(
            trackedProperty: $trackedProperty,
            session: $controlSession,
            identity: $controlIdentity,
            eventName: 'order.paid',
            eventCategory: 'conversion',
            occurredAt: CarbonImmutable::now()->subDays(1)->addMinutes(11),
            revenueMinor: 249_900,
            properties: $this->experimentProperties($experiment, $controlVariant, $controlAssignment, '/order/paid'),
        );

        $this->firstOrCreateEvent(
            trackedProperty: $trackedProperty,
            session: $variantSession,
            identity: $variantIdentity,
            eventName: 'page_view',
            eventCategory: 'page_view',
            occurredAt: CarbonImmutable::now()->subHours(12),
            properties: $this->experimentProperties($experiment, $winningVariant, $winningAssignment, '/checkout'),
        );

        $this->firstOrCreateEvent(
            trackedProperty: $trackedProperty,
            session: $variantSession,
            identity: $variantIdentity,
            eventName: 'checkout.started',
            eventCategory: 'checkout',
            occurredAt: CarbonImmutable::now()->subHours(12)->addMinutes(3),
            properties: $this->experimentProperties($experiment, $winningVariant, $winningAssignment, '/checkout'),
        );

        $this->firstOrCreateEvent(
            trackedProperty: $trackedProperty,
            session: $variantSession,
            identity: $variantIdentity,
            eventName: 'order.paid',
            eventCategory: 'conversion',
            occurredAt: CarbonImmutable::now()->subHours(12)->addMinutes(9),
            revenueMinor: 389_900,
            properties: $this->experimentProperties($experiment, $winningVariant, $winningAssignment, '/order/paid'),
        );
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function firstOrCreateEvent(
        TrackedProperty $trackedProperty,
        SignalSession $session,
        SignalIdentity $identity,
        string $eventName,
        string $eventCategory,
        CarbonImmutable $occurredAt,
        array $properties,
        int $revenueMinor = 0,
    ): void {
        SignalEvent::query()->firstOrCreate(
            [
                'tracked_property_id' => $trackedProperty->id,
                'signal_session_id' => $session->id,
                'signal_identity_id' => $identity->id,
                'event_name' => $eventName,
                'occurred_at' => $occurredAt,
            ],
            [
                'event_category' => $eventCategory,
                'path' => is_string($properties['path'] ?? null) ? $properties['path'] : null,
                'url' => 'https://cdemo.test'.(string) ($properties['path'] ?? '/'),
                'referrer' => 'https://cdemo.test/',
                'source' => 'demo',
                'medium' => 'seed',
                'campaign' => 'analytics-showcase',
                'revenue_minor' => $revenueMinor,
                'currency' => 'MYR',
                'properties' => $properties,
                'property_types' => [
                    'assignment_id' => 'string',
                    'experiment_id' => 'string',
                    'experiment_slug' => 'string',
                    'module_type' => 'string',
                    'variant_code' => 'string',
                    'variant_id' => 'string',
                ],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function experimentProperties(Experiment $experiment, Variant $variant, Assignment $assignment, string $path): array
    {
        return [
            'assignment_id' => $assignment->id,
            'experiment_contexts' => [[
                'assignment_id' => $assignment->id,
                'experiment_id' => $experiment->id,
                'experiment_slug' => $experiment->slug,
                'module_type' => $experiment->module_type,
                'variant_code' => $variant->code,
                'variant_id' => $variant->id,
            ]],
            'experiment_id' => $experiment->id,
            'experiment_slug' => $experiment->slug,
            'module_type' => $experiment->module_type,
            'path' => $path,
            'variant_code' => $variant->code,
            'variant_id' => $variant->id,
        ];
    }
}
