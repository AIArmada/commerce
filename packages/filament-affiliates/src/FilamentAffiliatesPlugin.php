<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates;

use AIArmada\FilamentAffiliates\Contracts\AdminPanelContribution;
use AIArmada\FilamentAffiliates\Platform\ExtensionRegistry;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentAffiliatesPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-affiliates';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources($this->getResources())
            ->pages($this->getPages())
            ->widgets($this->getWidgets());
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /** @return list<class-string> */
    private function getResources(): array
    {
        $features = $this->getAdminFeatures();
        $result = [];

        foreach ($this->contributions() as $contribution) {
            array_push($result, ...$contribution->resources($features));
        }

        return $result;
    }

    /** @return list<class-string> */
    private function getPages(): array
    {
        $features = $this->getAdminFeatures();
        $result = [];

        foreach ($this->contributions() as $contribution) {
            array_push($result, ...$contribution->pages($features));
        }

        return $result;
    }

    /** @return list<class-string> */
    private function getWidgets(): array
    {
        $features = $this->getAdminFeatures();
        $result = [];

        foreach ($this->contributions() as $contribution) {
            array_push($result, ...$contribution->widgets($features));
        }

        return $result;
    }
    /** @return list<AdminPanelContribution> */
    private function contributions(): array
    {
        $contributions = array_values(array_filter(
            app(ExtensionRegistry::class)->resolve('admin.panel.contributions', AdminPanelContribution::class),
            static fn (object $value): bool => $value instanceof AdminPanelContribution,
        ));

        usort($contributions, static fn (AdminPanelContribution $left, AdminPanelContribution $right): int => $left->priority() <=> $right->priority());

        return $contributions;
    }

    /**
     * @return array{conversions: bool, payouts: bool, programs: bool, commission_management: bool, volume_tiers: bool, links: bool, attribution: bool, ranks: bool, support_compliance: bool, fraud_monitoring: bool, reports: bool, upline_visualization: bool, creatives: bool}
     */
    private function getAdminFeatures(): array
    {
        $configured = config('filament-affiliates.features.admin', []);
        $commissionTrackingEnabled = (bool) config('affiliates.features.commission_tracking.enabled', true);

        return [
            'conversions' => (bool) ($configured['conversions'] ?? true),
            'payouts' => $commissionTrackingEnabled && (bool) ($configured['payouts'] ?? true),
            'programs' => $commissionTrackingEnabled && (bool) ($configured['programs'] ?? true),
            'commission_management' => $commissionTrackingEnabled && (bool) ($configured['commission_management'] ?? true),
            'volume_tiers' => $commissionTrackingEnabled && (bool) ($configured['volume_tiers'] ?? true),
            'links' => (bool) ($configured['links'] ?? true),
            'attribution' => (bool) ($configured['attribution'] ?? true),
            'ranks' => (bool) ($configured['ranks'] ?? true),
            'support_compliance' => (bool) ($configured['support_compliance'] ?? true),
            'fraud_monitoring' => (bool) ($configured['fraud_monitoring'] ?? true),
            'reports' => (bool) ($configured['reports'] ?? true),
            'upline_visualization' => (bool) ($configured['upline_visualization'] ?? true),
            'creatives' => (bool) ($configured['creatives'] ?? true),
        ];
    }
}
