<div>
    <x-filament::card>
        <div class="text-sm text-gray-500">
            Network visualization component.
        </div>
        
        <div class="mt-4">
            <h4 class="font-medium">Network Statistics</h4>
            <div class="grid grid-cols-4 gap-4 mt-2">
                @foreach($this->getNetworkStats() as $key => $value)
                    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="text-xs text-gray-500">{{ str_replace('_', ' ', ucwords($key)) }}</div>
                        <div class="text-lg font-semibold">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-4">
            <h4 class="font-medium mb-2">Network Tree</h4>
            <div class="overflow-x-auto">
                @foreach($this->getNetworkData() as $node)
                    @include('filament-affiliates::widgets.partials.network-node', ['node' => $node, 'level' => 0])
                @endforeach
            </div>
        </div>
    </x-filament::card>
</div>
