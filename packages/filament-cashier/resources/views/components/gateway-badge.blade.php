<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset bg-{$color}-50 text-{$color}-700 ring-{$color}-600/20 dark:bg-{$color}-400/10 dark:text-{$color}-400 dark:ring-{$color}-400/30"]) }}>
    <x-filament::icon
        :icon="$icon"
        class="h-3.5 w-3.5"
    />
    {{ $label }}
</span>
