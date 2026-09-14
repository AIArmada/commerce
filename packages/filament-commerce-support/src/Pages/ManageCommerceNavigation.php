<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport\Pages;

use AIArmada\CommerceSupport\Support\Filament\CommerceNavigation;
use AIArmada\FilamentCommerceSupport\Settings\CommerceNavigationSettings;
use AIArmada\FilamentCommerceSupport\Support\NavigationConfigurator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelSettings\Exceptions\MissingSettings;
use Throwable;
use UnitEnum;

class ManageCommerceNavigation extends Page
{
    /**
     * Reserved group key meaning "ungrouped". Never persisted as a group;
     * sections carrying it (or an empty key) save their items with no group.
     */
    private const UNGROUPED_KEY = '__ungrouped__';

    private const ICON_PATTERN = '/^(?:heroicon-[a-z]-[a-z0-9-]+)?$/';

    public ?array $data = [];

    private bool $settingsFallbackWarned = false;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bars-3';

    public static function getNavigationLabel(): string
    {
        return __('Navigation');
    }

    public function getTitle(): string
    {
        return __('Navigation Manager');
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        return config('filament-commerce-support.navigation.settings_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-commerce-support.navigation.sort');
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();
        $permission = config('filament-commerce-support.navigation.permission');

        if ($user === null || ! is_string($permission) || $permission === '') {
            return false;
        }

        return Gate::forUser($user)->allows($permission);
    }

    public function mount(): void
    {
        $settings = $this->resolveSettings();

        $defaultGroups = $this->getDefaultGroups();
        $mergedGroups = array_replace_recursive($defaultGroups, $settings->groups);

        $defaultOverrides = $this->getDefaultOverrides($mergedGroups);
        $mergedOverrides = array_replace_recursive($defaultOverrides, $settings->overrides);

        $this->data = [
            'sidebar' => $this->buildSidebarForForm($mergedGroups, $mergedOverrides),
        ];

        $this->getSchema('form')?->fill($this->data);
    }

    /**
     * Build grouped form data: each group entry holds its items nested under it.
     * This matches the sidebar structure exactly and enables drag-and-drop
     * reordering of groups and items within groups.
     *
     * @param  array<string, array<string, mixed>>  $mergedGroups
     * @param  array<string, array<string, mixed>>  $mergedOverrides
     * @return list<array<string, mixed>>
     */
    private function buildSidebarForForm(array $mergedGroups, array $mergedOverrides): array
    {
        $sections = [];

        // Build a set of used component classes to track what's assigned
        $unassigned = array_fill_keys(array_keys($mergedOverrides), true);

        foreach ($mergedGroups as $key => $config) {
            $key = (string) $key;
            if (! is_array($config)) {
                $config = [];
            }

            $groupItems = [];
            $groupOverrides = [];

            // Collect items that belong to this group. Saved items carry the
            // group *label* after a rename, so match by key or label —
            // otherwise renamed groups mis-bucket into Ungrouped and a resave
            // silently detaches them.
            $groupLabel = $config['label'] ?? $key;
            foreach ($mergedOverrides as $class => $override) {
                if (! is_string($class)) {
                    continue;
                }
                if (! is_array($override)) {
                    $override = [];
                }
                $itemGroup = $override['group'] ?? '';
                if ($itemGroup === $key || ($groupLabel !== '' && $itemGroup === $groupLabel)) {
                    $groupOverrides[$class] = $override;
                }
            }

            $sortIndex = 0;
            foreach ($groupOverrides as $class => $override) {
                $groupItems[] = $this->normalizeOverrideForSidebar($class, $override, $sortIndex++);
                unset($unassigned[$class]);
            }

            $sections[] = [
                'group_key' => $key,
                'label' => isset($config['label']) && is_string($config['label']) ? $config['label'] : $key,
                'icon' => isset($config['icon']) && is_string($config['icon']) ? $config['icon'] : '',
                'sort' => $config['sort'] ?? 0,
                'collapsible' => $config['collapsible'] ?? true,
                'collapsed' => $config['collapsed'] ?? false,
                'hidden' => $config['hidden'] ?? false,
                'items' => $groupItems,
            ];
        }

        // Add ungrouped items at the front — they appear first in the sidebar.
        // Anything not bucketed above (no group, unknown group) lands here.
        $ungroupedItems = [];
        $sortIndex = 0;
        foreach ($mergedOverrides as $class => $override) {
            if (! is_string($class) || ! isset($unassigned[$class])) {
                continue;
            }
            if (! is_array($override)) {
                $override = [];
            }
            $ungroupedItems[] = $this->normalizeOverrideForSidebar($class, $override, $sortIndex++);
        }

        if ($ungroupedItems !== []) {
            array_unshift($sections, [
                'group_key' => '',
                'label' => __('Ungrouped'),
                'icon' => '',
                'sort' => 0,
                'collapsible' => true,
                'collapsed' => false,
                'items' => $ungroupedItems,
            ]);
        }

        return $sections;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeOverrideForSidebar(string $class, array $override, int $sortIndex): array
    {
        $label = $override['label'] ?? '';
        if (! is_string($label)) {
            $label = '';
        }
        if ($label === '') {
            $label = $this->safeNavigationLabel($class);
        }

        $parentItem = $override['parent_item'] ?? '';
        if (! is_string($parentItem)) {
            $parentItem = '';
        }

        return [
            'component' => $class,
            'hidden' => $override['hidden'] ?? false,
            'label' => $label,
            'display_label' => $label,
            'sort' => $override['sort'] ?? $sortIndex,
            'parent_item' => $parentItem,
        ];
    }

    /**
     * Resolve a component's navigation label without invoking static methods
     * on unregistered classes. Override keys come from persisted settings,
     * so only panel-registered components may have methods called on them.
     */
    private function safeNavigationLabel(string $class): string
    {
        if (
            in_array($class, CommerceNavigation::registeredNavigationComponents(), true)
            && class_exists($class)
            && method_exists($class, 'getNavigationLabel')
        ) {
            $label = $class::getNavigationLabel();

            if (is_string($label) && $label !== '') {
                return $label;
            }
        }

        return class_basename($class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Repeater::make('sidebar')
                    ->label(__('Sidebar Menu'))
                    ->maxItems(100)
                    ->schema([
                        TextInput::make('group_key')
                            ->label(__('Group Key'))
                            ->placeholder(__('Select or type a group key, or leave empty for ungrouped items'))
                            ->datalist(fn (): array => array_keys($this->getGroupKeyOptions()))
                            ->maxLength(255)
                            ->notIn([self::UNGROUPED_KEY])
                            ->live(),

                        TextInput::make('label')
                            ->label(__('Group Label'))
                            ->maxLength(255)
                            ->hidden(fn (Get $get): bool => $this->isUngroupedSection($get)),

                        TextInput::make('icon')
                            ->label(__('Icon'))
                            ->helperText(__('Heroicon name, e.g. heroicon-o-shopping-bag'))
                            ->maxLength(255)
                            ->regex(self::ICON_PATTERN)
                            ->hidden(fn (Get $get): bool => $this->isUngroupedSection($get)),

                        TextInput::make('sort')
                            ->label(__('Sort Order'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(9999)
                            ->hidden(fn (Get $get): bool => $this->isUngroupedSection($get)),

                        Toggle::make('collapsible')
                            ->label(__('Collapsible'))
                            ->hidden(fn (Get $get): bool => $this->isUngroupedSection($get)),

                        Toggle::make('collapsed')
                            ->label(__('Collapsed by Default'))
                            ->hidden(fn (Get $get): bool => $this->isUngroupedSection($get)),

                        Toggle::make('hidden')
                            ->label(__('Hide Entire Group'))
                            ->helperText(__('Hide this group and all its items from the sidebar'))
                            ->hidden(fn (Get $get): bool => $this->isUngroupedSection($get)),

                        Repeater::make('items')
                            ->label(__('Menu Items in this Group'))
                            ->maxItems(200)
                            ->schema([
                                Select::make('component')
                                    ->label(__('Component'))
                                    ->options(fn (): array => $this->getComponentOptions())
                                    ->searchable()
                                    ->required()
                                    ->columnSpanFull(),

                                Toggle::make('hidden')
                                    ->label(__('Hidden')),

                                TextInput::make('label')
                                    ->label(__('Label'))
                                    ->helperText(__('Override label (leave blank for default)'))
                                    ->maxLength(255),

                                TextInput::make('sort')
                                    ->label(__('Sort'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(9999),

                                TextInput::make('parent_item')
                                    ->label(__('Parent Item'))
                                    ->helperText(__('Nest under another item (e.g. "Attributes")'))
                                    ->maxLength(255),
                            ])
                            ->collapsible()
                            ->collapsed()
                            ->itemLabel(function (array $state): ?string {
                                $class = $state['component'] ?? '';
                                if ($class === '' || ! is_string($class)) {
                                    return null;
                                }
                                $label = $state['label'] ?? '';
                                if ($label === '' || ! is_string($label)) {
                                    $label = $this->safeNavigationLabel($class);
                                }
                                $hidden = ! empty($state['hidden']);

                                return $hidden ? "✕ {$label}" : $label;
                            })
                            ->addActionLabel(__('Add Item to this Group'))
                            ->reorderable()
                            ->columns(2),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->itemLabel(fn (array $state): ?string => in_array($state['group_key'] ?? '', ['', self::UNGROUPED_KEY], true)
                        ? '— ' . __('Ungrouped Items') . ' —'
                        : ($state['label'] ?? $state['group_key'] ?? $state['_index'] ?? ''))
                    ->addActionLabel(__('Add Group'))
                    ->reorderable()
                    ->columns(2),
            ])
            ->statePath('data');
    }

    private function isUngroupedSection(Get $get): bool
    {
        return in_array($get('group_key') ?? '', ['', self::UNGROUPED_KEY], true);
    }

    public function save(): void
    {
        $settings = $this->resolveSettings();

        // Denormalize the nested sidebar structure back into flat groups + overrides.
        // getState() runs the form validation rules; without it every rule
        // above (required, allowlists, lengths, ranges) would be skipped.
        $state = $this->getSchema('form')?->getState() ?? $this->data ?? [];
        $sidebar = $state['sidebar'] ?? [];

        $submittedGroups = [];
        $submittedOverrides = [];
        $duplicateComponents = [];

        // Mounted sort values, used to tell typed sorts apart from untouched
        // inputs (which follow drag position). Unavailable without a panel.
        [$mountedGroupSorts, $mountedItemSorts] = $this->mountedSortMaps($settings);

        $groupSortIndex = 0;
        foreach ($sidebar as $section) {
            if (! is_array($section)) {
                continue;
            }
            $groupKey = $section['group_key'] ?? '';
            if (! is_string($groupKey)) {
                $groupKey = '';
            }

            if ($groupKey === '' || $groupKey === self::UNGROUPED_KEY) {
                // Items in the ungrouped section: save with empty group.
                $itemIndex = 0;
                foreach ($section['items'] ?? [] as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $class = $item['component'] ?? '';
                    if ($class === '' || ! is_string($class) || isset($submittedOverrides[$class])) {
                        $this->trackDuplicate($class, $submittedOverrides, $duplicateComponents);

                        continue;
                    }
                    $config = $this->overrideFromSidebarItem($item, $itemIndex, $mountedItemSorts[$class] ?? null);
                    $config['group'] = '';
                    $submittedOverrides[$class] = $config;
                    $itemIndex++;
                }

                continue;
            }

            // Save group config
            $groupConfig = [];
            $label = $section['label'] ?? $groupKey;
            if (! is_string($label)) {
                $label = $groupKey;
            }
            if ($label !== '' && $label !== $groupKey) {
                $groupConfig['label'] = $label;
            }
            $icon = $section['icon'] ?? '';
            if (is_string($icon) && $icon !== '' && preg_match(self::ICON_PATTERN, $icon) === 1) {
                $groupConfig['icon'] = $icon;
            }
            $groupConfig['sort'] = $this->resolveSort($section['sort'] ?? null, $mountedGroupSorts[$groupKey] ?? null, $groupSortIndex);
            if (isset($section['collapsible'])) {
                $groupConfig['collapsible'] = (bool) $section['collapsible'];
            }
            if (isset($section['collapsed'])) {
                $groupConfig['collapsed'] = (bool) $section['collapsed'];
            }
            if (isset($section['hidden'])) {
                $groupConfig['hidden'] = (bool) $section['hidden'];
            }
            $submittedGroups[$groupKey] = $groupConfig;
            $groupSortIndex++;

            // Save items in this group
            $itemIndex = 0;
            foreach ($section['items'] ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $class = $item['component'] ?? '';
                if ($class === '' || ! is_string($class) || isset($submittedOverrides[$class])) {
                    $this->trackDuplicate($class, $submittedOverrides, $duplicateComponents);

                    continue;
                }
                $itemConfig = $this->overrideFromSidebarItem($item, $itemIndex, $mountedItemSorts[$class] ?? null);
                $itemConfig['group'] = $groupKey;
                $submittedOverrides[$class] = $itemConfig;
                $itemIndex++;
            }
        }

        if ($duplicateComponents !== []) {
            Notification::make()
                ->title(__('Duplicate menu items were skipped (first occurrence kept).'))
                ->body(implode(', ', array_map(class_basename(...), $duplicateComponents)))
                ->warning()
                ->send();
        }

        // Build group rename map
        $groupRenames = [];
        foreach ($submittedGroups as $key => $config) {
            // Labels are stored only when non-empty and differ from the key,
            // so a label present here always renames.
            $newLabel = $config['label'] ?? $key;
            if ($newLabel !== $key) {
                $groupRenames[$key] = $newLabel;
            }
        }

        // Apply group renames to item overrides
        if ($groupRenames !== []) {
            foreach ($submittedOverrides as &$config) {
                $currentGroup = $config['group'];
                if (isset($groupRenames[$currentGroup])) {
                    $config['group'] = $groupRenames[$currentGroup];
                }
            }
            unset($config);
        }

        // True (untainted) defaults for diff baseline
        $trueDefaultGroups = $this->getTrueDefaultGroups();
        $trueDefaultOverrides = $this->getTrueDefaultOverrides();

        if ($groupRenames !== []) {
            foreach ($trueDefaultOverrides as &$config) {
                $currentGroup = $config['group'] ?? '';
                if (isset($groupRenames[$currentGroup])) {
                    $config['group'] = $groupRenames[$currentGroup];
                }
            }
            unset($config);
        }

        // For the unified sidebar form, always save the complete submitted
        // state — the form represents the exact desired sidebar structure.
        $groupsToSave = [];
        foreach ($submittedGroups as $key => $config) {
            if ($this->hasDifferences($trueDefaultGroups[$key] ?? [], $config)) {
                $groupsToSave[$key] = $config;
            }
        }

        $overridesToSave = [];
        foreach ($submittedOverrides as $class => $config) {
            if ($this->hasDifferences($trueDefaultOverrides[$class] ?? [], $config)) {
                $overridesToSave[$class] = $config;
            }
        }

        $settings->groups = $groupsToSave;
        $settings->overrides = $overridesToSave;

        try {
            $settings->save();
        } catch (QueryException | MissingSettings) {
            Notification::make()
                ->title(__('Navigation settings could not be saved. The settings storage is unavailable.'))
                ->danger()
                ->send();

            return;
        }

        NavigationConfigurator::apply();

        Notification::make()
            ->title(__('Navigation configuration saved.'))
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function overrideFromSidebarItem(array $item, int $index, mixed $mountedSort = null): array
    {
        $config = [];

        if (isset($item['hidden'])) {
            $config['hidden'] = (bool) $item['hidden'];
        }

        if (isset($item['label']) && is_string($item['label']) && $item['label'] !== '') {
            $config['label'] = $item['label'];
        }

        $config['sort'] = $this->resolveSort($item['sort'] ?? null, $mountedSort, $index + 1);

        if (isset($item['parent_item']) && is_string($item['parent_item']) && $item['parent_item'] !== '') {
            $config['parent_item'] = $item['parent_item'];
        }

        // Always include group when it differs from the item's component default.
        // When not set here, it's added by the caller for grouped items.
        return $config;
    }

    /**
     * Resolve a submitted sort input: a typed value that differs from the
     * mounted value wins (explicit user edit); untouched inputs follow drag
     * position so reordering keeps working. Values are clamped to the form's
     * 0–9999 range.
     */
    private function resolveSort(mixed $submitted, mixed $mounted, int $positional): int
    {
        if (is_numeric($submitted) && ($mounted === null || (int) $submitted !== (int) $mounted)) {
            return max(0, min(9999, (int) $submitted));
        }

        return $positional;
    }

    /**
     * Rebuild the sort values the form was mounted with, keyed by group key
     * and component class. Falls back to empty maps when no panel is
     * available (then every numeric submitted sort is honored).
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function mountedSortMaps(CommerceNavigationSettings $settings): array
    {
        try {
            $mergedGroups = array_replace_recursive($this->getDefaultGroups(), $settings->groups);
            $mergedOverrides = array_replace_recursive($this->getDefaultOverrides($mergedGroups), $settings->overrides);
            $mounted = $this->buildSidebarForForm($mergedGroups, $mergedOverrides);
        } catch (Throwable) {
            return [[], []];
        }

        $groupSorts = [];
        $itemSorts = [];

        foreach ($mounted as $section) {
            $groupKey = $section['group_key'] ?? '';
            if (is_string($groupKey) && $groupKey !== '' && $groupKey !== self::UNGROUPED_KEY) {
                $groupSorts[$groupKey] = $section['sort'] ?? 0;
            }
            foreach ($section['items'] ?? [] as $item) {
                $class = $item['component'] ?? '';
                if (is_string($class) && $class !== '') {
                    $itemSorts[$class] = $item['sort'] ?? 0;
                }
            }
        }

        return [$groupSorts, $itemSorts];
    }

    /**
     * @param  array<string, mixed>  $submittedOverrides
     * @param  list<string>  $duplicateComponents
     */
    private function trackDuplicate(mixed $class, array $submittedOverrides, array &$duplicateComponents): void
    {
        if (! is_string($class) || $class === '' || ! isset($submittedOverrides[$class])) {
            return;
        }

        if (! in_array($class, $duplicateComponents, true)) {
            $duplicateComponents[] = $class;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getTrueDefaultOverrides(): array
    {
        $defaults = [];
        $originalItems = NavigationConfigurator::getOriginalItemsConfig();

        $extract = function (string $class) use (&$defaults, $originalItems): void {
            $group = null;
            if (method_exists($class, 'getNavigationGroup')) {
                $group = $class::getNavigationGroup();
            }
            if ($group instanceof UnitEnum) {
                $group = $group->name;
            }

            $defaults[$class] = [
                'hidden' => false,
                'label' => '',
                'group' => (string) $group,
                'sort' => (int) (method_exists($class, 'getNavigationSort') ? ($class::getNavigationSort() ?? 0) : 0),
                'parent_item' => (string) (method_exists($class, 'getNavigationParentItem') ? ($class::getNavigationParentItem() ?? '') : ''),
            ];

            // Layer file-based (pre-settings) config overrides on top so the
            // true default reflects package-level overrides, not just component
            // declarations. This prevents config-level overrides from being
            // duplicated into settings on the first save.
            $classConfig = $originalItems[$class] ?? $originalItems[mb_ltrim($class, '\\')] ?? [];
            if ($classConfig === []) {
                return;
            }
            if (isset($classConfig['hidden'])) {
                $defaults[$class]['hidden'] = (bool) $classConfig['hidden'];
            }
            if (isset($classConfig['label']) && $classConfig['label'] !== '') {
                $defaults[$class]['label'] = $classConfig['label'];
            }
            if (isset($classConfig['group']) && $classConfig['group'] !== '') {
                $defaults[$class]['group'] = $classConfig['group'];
            }
            if (isset($classConfig['sort'])) {
                $defaults[$class]['sort'] = (int) $classConfig['sort'];
            }
            if (isset($classConfig['parent_item']) && $classConfig['parent_item'] !== '') {
                $defaults[$class]['parent_item'] = $classConfig['parent_item'];
            }
        };

        foreach (CommerceNavigation::registeredNavigationComponents() as $class) {
            $extract($class);
        }

        return $defaults;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getTrueDefaultGroups(): array
    {
        return $this->getDefaultGroups(NavigationConfigurator::getOriginalGroupConfig());
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Save'))
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action('save'),
        ];
    }

    protected function resolveSettings(): CommerceNavigationSettings
    {
        $settings = NavigationConfigurator::resolveSettings();

        if ($settings !== null) {
            return $settings;
        }

        // Settings storage is unavailable (missing table or rows): degrade to
        // an empty in-memory instance instead of 500ing, and never write
        // during reads. A later save() upserts the rows when the table exists.
        if (! $this->settingsFallbackWarned) {
            $this->settingsFallbackWarned = true;

            Notification::make()
                ->title(__('Navigation settings storage is unavailable; showing defaults.'))
                ->warning()
                ->send();
        }

        return new CommerceNavigationSettings(['groups' => [], 'overrides' => []]);
    }

    /**
     * @return array<string, string>
     */
    private function getComponentOptions(): array
    {
        $options = [];

        foreach (CommerceNavigation::registeredNavigationComponents() as $class) {
            $label = method_exists($class, 'getNavigationLabel')
                ? $class::getNavigationLabel()
                : class_basename($class);
            $group = method_exists($class, 'getNavigationGroup')
                ? $class::getNavigationGroup()
                : null;
            if ($group instanceof UnitEnum) {
                $group = $group->name;
            }
            $groupPrefix = $group ? "[{$group}] " : '';
            $type = is_subclass_of($class, Resource::class) ? 'Resource' : 'Page';
            $options[$class] = "{$groupPrefix}[{$type}] {$label} — {$class}";
        }

        // Keep previously stored (now unregistered) components selectable so
        // their entries keep round-tripping instead of failing validation and
        // blocking every save. Their labels never invoke methods on them.
        foreach (array_keys($this->resolveSettings()->overrides) as $class) {
            if (! is_string($class) || $class === '' || isset($options[$class])) {
                continue;
            }
            $options[$class] = "[Unregistered] {$class}";
        }

        ksort($options);

        return $options;
    }

    private function getGroupKeyOptions(): array
    {
        $defaultKeys = [
            'Addressing' => 'Addressing',
            'Affiliate Network' => 'Affiliate Network',
            'Affiliate Portal' => 'Affiliate Portal',
            'Authz' => 'Authz',
            'Billing' => 'Billing',
            'Catalog' => 'Catalog',
            'CHIP Operations' => 'CHIP Operations',
            'Contacting' => 'Contacting',
            'CRM' => 'CRM',
            'Documents' => 'Documents',
            'E-commerce' => 'E-commerce',
            'Engagement' => 'Engagement',
            'Events' => 'Events',
            'Feedback' => 'Feedback',
            'Growth' => 'Growth',
            'Insights' => 'Insights',
            'Inventory' => 'Inventory',
            'Marketing' => 'Marketing',
            'Payments' => 'Payments',
            'Pricing' => 'Pricing',
            'Sales' => 'Sales',
            'Settings' => 'Settings',
            'Shipping' => 'Shipping',
            'Tax' => 'Tax',
            'Vouchers & Discounts' => 'Vouchers & Discounts',
        ];

        $keys = [];

        foreach (config('commerce-support.filament.navigation.groups', []) as $key => $config) {
            $label = is_array($config) ? ($config['label'] ?? $key) : $key;
            $keys[$key] = $label;
        }

        $settings = $this->resolveSettings();

        foreach ($settings->groups as $key => $config) {
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }
            $label = is_array($config) ? ($config['label'] ?? $key) : $key;
            $keys[$key] = is_string($label) ? $label : (string) $key;
        }

        $keys = array_merge($defaultKeys, $keys);

        ksort($keys);

        return $keys;
    }

    /**
     * Resolve the canonical engine's groups into the settings form shape.
     *
     * @param  array<string | int, mixed>|null  $configuredGroups
     * @return array<string, array<string, mixed>>
     */
    private function getDefaultGroups(?array $configuredGroups = null): array
    {
        $configGroups = $configuredGroups ?? config('commerce-support.filament.navigation.groups', []);

        if (! is_array($configGroups)) {
            return [];
        }

        $groups = [];

        foreach (CommerceNavigation::groups($configGroups) as $resolvedGroup) {
            $label = $resolvedGroup->getLabel();

            if (! is_string($label) || $label === '') {
                continue;
            }

            $key = $this->groupKeyForLabel($configGroups, $label);
            $config = $configGroups[$key] ?? [];

            $groups[$key] = [
                'label' => is_array($config) ? ($config['label'] ?? $label) : (is_string($config) ? $config : $label),
                'icon' => is_array($config) ? ($config['icon'] ?? '') : '',
                'sort' => is_array($config) ? ($config['sort'] ?? 0) : 0,
                'collapsible' => is_array($config) ? ($config['collapsible'] ?? true) : true,
                'collapsed' => is_array($config) ? ($config['collapsed'] ?? false) : false,
                'hidden' => is_array($config) ? ($config['hidden'] ?? false) : false,
            ];
        }

        return $groups;
    }

    /**
     * @param  array<string | int, mixed>  $configuredGroups
     */
    private function groupKeyForLabel(array $configuredGroups, string $label): string
    {
        foreach ($configuredGroups as $key => $definition) {
            $configuredLabel = is_array($definition)
                ? ($definition['label'] ?? $key)
                : (is_string($definition) ? $definition : $key);

            if ((string) $key === $label || (string) $configuredLabel === $label) {
                return (string) $key;
            }
        }

        return $label;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getDefaultOverrides(array $mergedGroups = []): array
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        $defaults = [];

        $extract = function (string $class) use (&$defaults): void {
            $defaultGroup = method_exists($class, 'getNavigationGroup') ? $class::getNavigationGroup() : null;
            $defaultSort = method_exists($class, 'getNavigationSort') ? $class::getNavigationSort() : null;
            $defaultParent = method_exists($class, 'getNavigationParentItem') ? $class::getNavigationParentItem() : null;

            $group = CommerceNavigation::group($class, $defaultGroup);
            if ($group instanceof UnitEnum) {
                $group = $group->name;
            }

            $defaults[$class] = [
                'hidden' => ! CommerceNavigation::visible($class, true),
                'label' => '',
                'group' => (string) $group,
                'sort' => (int) CommerceNavigation::sort($class, $defaultSort),
                'parent_item' => (string) CommerceNavigation::parentItem($class, $defaultParent),
            ];
        };

        foreach (CommerceNavigation::registeredNavigationComponents() as $class) {
            $extract($class);
        }

        // Extract Panel's explicit group registration order
        $panelGroups = $panel->getNavigationGroups();
        $panelGroupSorts = [];
        foreach ($panelGroups as $index => $groupObj) {
            $label = $groupObj->getLabel();
            if ($label !== null) {
                $panelGroupSorts[$label] = $index;
            }
        }

        $settings = $this->resolveSettings();
        $userGroups = $settings->groups;

        $itemIndex = 0;
        foreach ($defaults as &$config) {
            $config['__item_index'] = $itemIndex++;
            $groupKey = $config['group'];
            $config['__group_sort'] = 9999;
            if ($groupKey !== '') {
                // If user explicitly saved a sort order, use it. Otherwise rely on panel registration order.
                if (isset($userGroups[$groupKey]['sort'])) {
                    $config['__group_sort'] = (int) $userGroups[$groupKey]['sort'];
                } elseif (isset($panelGroupSorts[$groupKey])) {
                    $config['__group_sort'] = $panelGroupSorts[$groupKey];
                } elseif (isset($mergedGroups[$groupKey]['sort']) && $mergedGroups[$groupKey]['sort'] !== 0) {
                    $config['__group_sort'] = (int) $mergedGroups[$groupKey]['sort'];
                }
            }
        }
        unset($config);

        /** @var array<string, array<string, mixed>> $defaults */
        uasort($defaults, function (array $a, array $b): int {
            // 1. Group Sort
            if ($a['__group_sort'] !== $b['__group_sort']) {
                return $a['__group_sort'] <=> $b['__group_sort'];
            }

            // 2. Group Label (Alphabetical)
            $groupA = $a['group'] ?? '';
            $groupB = $b['group'] ?? '';
            $groupCmp = strcasecmp($groupA, $groupB);
            if ($groupCmp !== 0) {
                return $groupCmp;
            }

            // 3. Item Sort
            $sortA = (int) ($a['sort'] ?? 0);
            $sortB = (int) ($b['sort'] ?? 0);
            if ($sortA !== $sortB) {
                return $sortA <=> $sortB;
            }

            // 4. Panel Registration Order
            return $a['__item_index'] <=> $b['__item_index'];
        });

        foreach ($defaults as &$config) {
            unset($config['__group_sort'], $config['__item_index']);
        }
        unset($config);

        return $defaults;
    }

    private function hasDifferences(array $default, array $submitted): bool
    {
        if (empty($default)) {
            return true;
        }

        foreach ($submitted as $key => $value) {
            if (! array_key_exists($key, $default) || $default[$key] !== $value) {
                return true;
            }
        }

        return false;
    }
}
