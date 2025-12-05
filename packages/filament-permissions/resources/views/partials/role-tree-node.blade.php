<div class="ml-{{ $node['level'] * 4 }}">
    <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 mb-2">
        {{-- Role indicator --}}
        <span class="w-3 h-3 rounded-full {{ $node['level'] === 0 ? 'bg-primary-500' : 'bg-gray-400' }}"></span>
        
        {{-- Role name --}}
        <span class="font-medium">{{ $node['name'] }}</span>
        
        {{-- Permission count badge --}}
        <span class="px-2 py-0.5 text-xs rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300">
            {{ $node['permission_count'] }} permissions
        </span>
        
        {{-- Level indicator --}}
        @if($node['level'] > 0)
            <span class="text-xs text-gray-500">Level {{ $node['level'] }}</span>
        @endif
    </div>
    
    {{-- Children --}}
    @if(!empty($node['children']))
        <div class="border-l-2 border-gray-200 dark:border-gray-600 ml-1.5">
            @foreach($node['children'] as $child)
                @include('filament-permissions::partials.role-tree-node', ['node' => $child])
            @endforeach
        </div>
    @endif
</div>
