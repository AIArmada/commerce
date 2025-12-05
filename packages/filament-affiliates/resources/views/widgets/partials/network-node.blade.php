<div class="pl-{{ $level * 6 }} py-2 border-l-2 border-gray-200 dark:border-gray-700 {{ $level > 0 ? 'ml-4' : '' }}">
    <div class="flex items-center gap-3">
        <div class="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
            <span class="text-xs font-medium text-primary-700 dark:text-primary-300">
                {{ substr($node['name'] ?? 'A', 0, 1) }}
            </span>
        </div>
        <div>
            <div class="font-medium text-sm">{{ $node['name'] ?? 'Unknown' }}</div>
            <div class="text-xs text-gray-500">
                Code: {{ $node['code'] ?? '-' }} 
                @if($node['rank'] ?? null)
                    | Rank: {{ $node['rank'] }}
                @endif
                | {{ $node['conversions'] ?? 0 }} conversions
                @if(($node['children_count'] ?? 0) > count($node['children'] ?? []))
                    | +{{ ($node['children_count'] ?? 0) - count($node['children'] ?? []) }} more
                @endif
            </div>
        </div>
        <div class="ml-auto">
            <x-filament::badge :color="$node['status'] === 'active' ? 'success' : 'gray'">
                {{ ucfirst($node['status'] ?? 'unknown') }}
            </x-filament::badge>
        </div>
    </div>
    
    @if(!empty($node['children']))
        <div class="mt-2">
            @foreach($node['children'] as $child)
                @include('filament-affiliates::widgets.partials.network-node', ['node' => $child, 'level' => $level + 1])
            @endforeach
        </div>
    @endif
</div>
