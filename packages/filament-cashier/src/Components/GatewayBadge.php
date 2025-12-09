<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\Components;

use AIArmada\FilamentCashier\Support\GatewayDetector;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class GatewayBadge extends Component
{
    public string $label;

    public string $icon;

    public string $color;

    public function __construct(
        public string $gateway,
    ) {
        $detector = app(GatewayDetector::class);
        $config = $detector->getGatewayConfig($gateway);

        $this->label = $config['label'];
        $this->icon = $config['icon'];
        $this->color = $config['color'];
    }

    public function render(): View
    {
        return view('filament-cashier::components.gateway-badge');
    }
}
