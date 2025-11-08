<div class="space-y-2">
    @if(empty($data))
        <p class="text-sm text-gray-500 dark:text-gray-400">No metadata available.</p>
    @else
        <pre class="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg text-xs overflow-x-auto">{{ json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
    @endif
</div>
