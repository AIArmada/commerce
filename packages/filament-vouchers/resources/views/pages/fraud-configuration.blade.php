<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Fraud Detection Configuration
        </x-slot>
        <x-slot name="description">
            Configure fraud detection thresholds and enable/disable specific detectors.
        </x-slot>

        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex justify-end gap-3">
                @foreach ($this->getFormActions() as $action)
                    {{ $action }}
                @endforeach
            </div>
        </form>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">
            Fraud Detection Overview
        </x-slot>

        <div class="prose dark:prose-invert max-w-none text-sm">
            <p>The fraud detection system uses multiple detectors to identify potentially fraudulent voucher usage:</p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div class="border dark:border-gray-700 rounded p-4">
                    <h4 class="font-semibold">Velocity Detector</h4>
                    <p class="text-gray-500">Detects abnormally fast or frequent voucher usage patterns, such as multiple redemptions in a short time period.</p>
                </div>
                
                <div class="border dark:border-gray-700 rounded p-4">
                    <h4 class="font-semibold">Pattern Detector</h4>
                    <p class="text-gray-500">Identifies unusual patterns like redemptions at odd hours, from suspicious IP addresses, or geographic anomalies.</p>
                </div>
                
                <div class="border dark:border-gray-700 rounded p-4">
                    <h4 class="font-semibold">Behavioral Detector</h4>
                    <p class="text-gray-500">Analyzes user behavior such as cart manipulation, high refund rates, or suspicious checkout patterns.</p>
                </div>
                
                <div class="border dark:border-gray-700 rounded p-4">
                    <h4 class="font-semibold">Code Abuse Detector</h4>
                    <p class="text-gray-500">Detects code sharing, leaked codes, bruteforce attempts, and sequential code guessing.</p>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
