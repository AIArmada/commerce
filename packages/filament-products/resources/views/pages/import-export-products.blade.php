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
                    <li><strong>Required Fields:</strong> name, sku</li>
                    <li><strong>Optional Fields:</strong> slug, description, price, compare_at_price, cost,
                        stock_quantity, low_stock_threshold, weight, status, type, visibility</li>
                    <li><strong>Price Format:</strong> Enter prices in dollars (e.g., 99.99 not 9999)</li>
                    <li><strong>Status Values:</strong> active, draft, archived</li>
                    <li><strong>Type Values:</strong> simple, variable, digital</li>
                    <li><strong>Visibility Values:</strong> visible, hidden, catalog, search</li>
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