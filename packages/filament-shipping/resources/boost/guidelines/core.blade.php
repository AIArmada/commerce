# Filament Shipping

Register `AIArmada\FilamentShipping\FilamentShippingPlugin::make()` on the panel. The package is a presentation adapter for Shipping, not an independent owner of shipping tables.

- Keep resource actions and page callbacks thin. Resolve Shipping feature APIs for shipment operations, manual shipment writes, rate configuration and return workflows; pass record identifiers instead of writing domain models directly.
- Use domain reporting APIs for aggregate widgets and retain resource policy checks and owner scoping.
- Generate label URLs through `AIArmada\Shipping\Features\Shipments\Api\PrintableLabel`; keep user and owner authorization intact. Do not advertise manifest PDF export until it is implemented.
