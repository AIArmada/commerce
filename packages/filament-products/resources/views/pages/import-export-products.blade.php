<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Import Products from CSV
            </x-slot>

            <x-slot name="description">
                Upload a CSV file to import products in bulk. You can download a template to see the required format.
            </x-slot>

            <form wire:submit="import">
                {{ $this->importForm }}

                <div class="mt-6">
                    <x-filament::button type="submit">
                        <x-heroicon-o-arrow-up-tray class="w-4 h-4 mr-1" />
                        Import Products
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Quick Guide
            </x-slot>

            <div class="prose dark:prose-invert max-w-none">
                <h4>CSV Format Requirements:</h4>
                <ul>
                    <li><strong>Required Fields:</strong> name</li>
                    <li><strong>Optional Fields:</strong> sku, slug, description, short_description, price, compare_price, cost, weight, status, type, visibility, is_featured, is_taxable, requires_shipping, tax_class</li>
                    <li><strong>Price Format:</strong> Enter prices in dollars (e.g., 99.99 not 9999)</li>
                    <li><strong>Status Values:</strong> active, draft, disabled, archived</li>
                    <li><strong>Type Values:</strong> simple, configurable, bundle, digital, subscription</li>
                    <li><strong>Visibility Values:</strong> catalog, search, catalog_search, individual, hidden</li>
                    <li><strong>Boolean Values:</strong> true/false for is_featured, is_taxable, requires_shipping</li>
                </ul>

                <h4>Import Tips:</h4>
                <ul>
                    <li>Download the template to see the correct format</li>
                    <li>If SKU already exists and "Update Existing" is enabled, the product will be updated</li>
                    <li>Enable "Skip Errors" to continue importing even if some rows fail</li>
                    <li>Large imports may take several minutes</li>
                </ul>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>