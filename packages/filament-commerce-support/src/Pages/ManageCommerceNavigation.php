<?php

declare(strict_types=1);

namespace AIArmada\FilamentCommerceSupport\Pages;

use AIArmada\CommerceSupport\Support\Filament\CommerceNavigation;
use AIArmada\FilamentCommerceSupport\Settings\CommerceNavigationSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Spatie\LaravelSettings\Exceptions\MissingSettings;
use UnitEnum;

class ManageCommerceNavigation extends Page
{
    public ?array $data = [];

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bars-3';

    protected static string | UnitEnum | null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 100;

    public static function getNavigationLabel(): string
    {
        return __('Navigation');
    }

    public function getTitle(): string
    {
        return __('Navigation Manager');
    }

    public function mount(): void
    {
        $settings = $this->resolveSettings();

        $defaultGroups = $this->getDefaultGroups();
        $mergedGroups = array_replace_recursive($defaultGroups, $settings->groups ?? []);

        $defaultOverrides = $this->getDefaultOverrides($mergedGroups);
        $mergedOverrides = array_replace_recursive($defaultOverrides, $settings->overrides ?? []);

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
        $unassigned = array_keys($mergedOverrides);

        foreach ($mergedGroups as $key => $config) {
            $groupItems = [];
            $groupOverrides = [];

            // Collect items that belong to this group (by effective group)
            foreach ($mergedOverrides as $class => $override) {
                $itemGroup = $override['group'] ?? '';
                if ($itemGroup === $key) {
                    $groupOverrides[$class] = $override;
                }
            }

            $sortIndex = 0;
            foreach ($groupOverrides as $class => $override) {
                $groupItems[] = $this->normalizeOverrideForSidebar($class, $override, $sortIndex++);
                unset($unassigned[$class]);
            }

            // If there's no items group override matching this group key, check
            // if any items have the SAME group key as their default group.
            if ($groupItems === []) {
                foreach ($mergedOverrides as $class => $override) {
                    $itemGroup = $override['group'] ?? '';
                    if ($itemGroup === '' || ! isset($mergedGroups[$itemGroup]) || $itemGroup === $key) {
                        continue;
                    }
                }
            }

            $sections[] = [
                'group_key' => $key,
                'label' => $config['label'] ?? $key,
                'icon' => $config['icon'] ?? '',
                'sort' => $config['sort'] ?? 0,
                'collapsible' => $config['collapsible'] ?? true,
                'collapsed' => $config['collapsed'] ?? false,
                'items' => $groupItems,
            ];
        }

        // Add ungrouped items at the front — they appear first in the sidebar.
        $ungroupedItems = [];
        $sortIndex = 0;
        foreach ($mergedOverrides as $class => $override) {
            $itemGroup = $override['group'] ?? '';
            if ($itemGroup === '' || ! isset($mergedGroups[$itemGroup])) {
                $ungroupedItems[] = $this->normalizeOverrideForSidebar($class, $override, $sortIndex++);
            }
        }

        if ($ungroupedItems !== []) {
            array_unshift($sections, [
                'group_key' => '__ungrouped__',
                'label' => __('Ungrouped'),
                'icon' => '',
                'sort' => -1,
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
        if ($label === '') {
            $label = class_exists($class) && method_exists($class, 'getNavigationLabel')
                ? $class::getNavigationLabel()
                : class_basename($class);
        }

        return [
            'component' => $class,
            'hidden' => $override['hidden'] ?? false,
            'label' => $label,
            'display_label' => $label,
            'sort' => $override['sort'] ?? $sortIndex,
            'parent_item' => $override['parent_item'] ?? '',
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Repeater::make('sidebar')
                    ->label(__('Sidebar Menu'))
                    ->schema([
                        TextInput::make('group_key')
                            ->label(__('Group Key'))
                            ->placeholder(__('Select or type a group key, or leave empty for ungrouped items'))
                            ->datalist(fn (): array => array_keys($this->getGroupKeyOptions()))
                            ->hidden(fn (?string $state): bool => ($state ?? '') === '__ungrouped__'),

                        TextInput::make('label')
                            ->label(__('Group Label'))
                            ->maxLength(255)
                            ->hidden(fn (?string $state): bool => ($state ?? '') === '__ungrouped__'),

                        TextInput::make('icon')
                            ->label(__('Icon'))
                            ->helperText(__('Heroicon name, e.g. heroicon-o-shopping-bag'))
                            ->maxLength(255)
                            ->hidden(fn (?string $state): bool => ($state ?? '') === '__ungrouped__'),

                        TextInput::make('sort')
                            ->label(__('Sort Order'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(9999)
                            ->hidden(fn (?string $state): bool => ($state ?? '') === '__ungrouped__'),

                        Toggle::make('collapsible')
                            ->label(__('Collapsible'))
                            ->hidden(fn (?string $state): bool => ($state ?? '') === '__ungrouped__'),

                        Toggle::make('collapsed')
                            ->label(__('Collapsed by Default'))
                            ->hidden(fn (?string $state): bool => ($state ?? '') === '__ungrouped__'),

                        Toggle::make('hidden')
                            ->label(__('Hide Entire Group'))
                            ->helperText(__('Hide this group and all its items from the sidebar'))
                            ->hidden(fn (?string $state): bool => ($state ?? '') === '__ungrouped__'),

                        Repeater::make('items')
                            ->label(__('Menu Items in this Group'))
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
                                if ($class === '') {
                                    return null;
                                }
                                $label = $state['label'] ?? '';
                                if ($label === '') {
                                    $label = class_exists($class) && method_exists($class, 'getNavigationLabel')
                                        ? $class::getNavigationLabel()
                                        : class_basename($class);
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
                    ->itemLabel(fn (array $state): ?string => (($state['group_key'] ?? '') === '__ungrouped__')
                        ? '— ' . __('Ungrouped Items') . ' —'
                        : (($state['group_key'] ?? '') === ''
                            ? '— ' . __('Ungrouped Items') . ' —'
                            : ($state['label'] ?? $state['group_key'] ?? $state['_index'] ?? '')))
                    ->addActionLabel(__('Add Group'))
                    ->reorderable()
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $settings = $this->resolveSettings();

        // Denormalize the nested sidebar structure back into flat groups + overrides.
        $sidebar = $this->data['sidebar'] ?? [];

        $submittedGroups = [];
        $submittedOverrides = [];

        $groupSortIndex = 0;
        foreach ($sidebar as $section) {
            $groupKey = $section['group_key'] ?? '';

            if ($groupKey === '' || $groupKey === '__ungrouped__') {
                // Items in the ungrouped section: save with empty group.
                $itemIndex = 0;
                foreach ($section['items'] ?? [] as $item) {
                    $class = $item['component'] ?? '';
                    if ($class === '') {
                        continue;
                    }
                    $config = $this->overrideFromSidebarItem($item, $itemIndex);
                    $config['group'] = '';
                    $submittedOverrides[$class] = $config;
                    $itemIndex++;
                }
                continue;
            }

            // Save group config
            $groupConfig = [];
            $label = $section['label'] ?? $groupKey;
            if ($label !== '' && $label !== $groupKey) {
                $groupConfig['label'] = $label;
            }
            if (isset($section['icon']) && $section['icon'] !== '') {
                $groupConfig['icon'] = $section['icon'];
            }
            $groupConfig['sort'] = $groupSortIndex;
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
                $class = $item['component'] ?? '';
                if ($class === '') {
                    continue;
                }
                $itemConfig = $this->overrideFromSidebarItem($item, $itemIndex);
                $itemConfig['group'] = $groupKey;
                $submittedOverrides[$class] = $itemConfig;
                $itemIndex++;
            }
        }

        // Build group rename map
        $groupRenames = [];
        foreach ($submittedGroups as $key => $config) {
            $newLabel = $config['label'] ?? $key;
            if ($newLabel !== $key && $newLabel !== '') {
                $groupRenames[$key] = $newLabel;
            }
        }

        // Apply group renames to item overrides
        if ($groupRenames !== []) {
            foreach ($submittedOverrides as $class => &$config) {
                $currentGroup = $config['group'] ?? '';
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
            foreach ($trueDefaultOverrides as $class => &$config) {
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

        $settings->save();

        \AIArmada\FilamentCommerceSupport\Support\NavigationConfigurator::apply();

        Notification::make()
            ->title(__('Navigation configuration saved.'))
            ->success()
            ->send();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function overrideFromSidebarItem(array $item, int $index): array
    {
        $config = [];

        if (isset($item['hidden'])) {
            $config['hidden'] = (bool) $item['hidden'];
        }

        if (isset($item['label']) && $item['label'] !== '') {
            $config['label'] = $item['label'];
        }

        $config['sort'] = $index + 1;

        if (isset($item['parent_item']) && $item['parent_item'] !== '') {
            $config['parent_item'] = $item['parent_item'];
        }

        // Always include group when it differs from the item's component default.
        // When not set here, it's added by the caller for grouped items.
        return $config;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getTrueDefaultOverrides(): array
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        $defaults = [];
        $originalItems = \AIArmada\FilamentCommerceSupport\Support\NavigationConfigurator::$originalItemsConfig;

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

        foreach ($panel->getResources() as $resource) {
            $extract(is_string($resource) ? $resource : $resource::class);
        }

        foreach ($panel->getPages() as $page) {
            $extract(is_string($page) ? $page : $page::class);
        }

        foreach ($panel->getPageConfigurations() as $configuration) {
            $page = $configuration->getPage();
            $extract(is_string($page) ? $page : get_class($page));
        }

        return $defaults;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getTrueDefaultGroups(): array
    {
        $groups = [];
        $configGroups = \AIArmada\FilamentCommerceSupport\Support\NavigationConfigurator::$originalGroupConfig;

        foreach ($configGroups as $key => $config) {
            $groups[$key] = [
                'label' => is_array($config) ? ($config['label'] ?? $key) : (is_string($config) ? $config : $key),
                'icon' => is_array($config) ? ($config['icon'] ?? '') : '',
                'sort' => is_array($config) ? ($config['sort'] ?? 0) : 0,
                'collapsible' => is_array($config) ? ($config['collapsible'] ?? true) : true,
                'collapsed' => is_array($config) ? ($config['collapsed'] ?? false) : false,
            ];
        }

        // Auto-populate from panel resources/pages so they show up in the form.
        $existingLabels = array_map(
            static fn (array $g): string => mb_strtolower($g['label'] ?? ''),
            $groups,
        );
        $panel = Filament::getCurrentOrDefaultPanel();
        $groupNames = [];
        foreach ($panel->getResources() as $resource) {
            $class = is_string($resource) ? $resource : $resource::class;
            if (method_exists($class, 'getNavigationGroup')) {
                $g = $class::getNavigationGroup();
                if (is_string($g) && $g !== '') {
                    $groupNames[] = $g;
                }
            }
        }
        foreach ($panel->getPages() as $page) {
            $class = is_string($page) ? $page : $page::class;
            if (method_exists($class, 'getNavigationGroup')) {
                $g = $class::getNavigationGroup();
                if (is_string($g) && $g !== '') {
                    $groupNames[] = $g;
                }
            }
        }
        foreach ($panel->getPageConfigurations() as $configuration) {
            $page = $configuration->getPage();
            $class = is_string($page) ? $page : get_class($page);
            if (method_exists($class, 'getNavigationGroup')) {
                $g = $class::getNavigationGroup();
                if (is_string($g) && $g !== '') {
                    $groupNames[] = $g;
                }
            }
        }
        foreach (array_unique($groupNames) as $name) {
            if (in_array(mb_strtolower($name), $existingLabels, true)) {
                continue;
            }
            $groups[$name] = [
                'label' => $name,
                'icon' => '',
                'sort' => 0,
                'collapsible' => true,
                'collapsed' => false,
            ];
        }

        return $groups;
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
        try {
            return app(CommerceNavigationSettings::class);
        } catch (MissingSettings) {
            // Seed the missing rows so spatie/laravel-settings can load/save.
            $group = 'commerce-navigation';
            foreach (['groups', 'overrides'] as $name) {
                \Illuminate\Support\Facades\DB::table('settings')->insertOrIgnore([
                    'group' => $group,
                    'name' => $name,
                    'locked' => false,
                    'payload' => '[]',
                ]);
            }

            app()->forgetInstance(CommerceNavigationSettings::class);

            return app(CommerceNavigationSettings::class);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    private function normalizeGroupsForForm(array $groups): array
    {
        $items = [];

        foreach ($groups as $key => $config) {
            $items[] = [
                'group_key' => $key,
                'label' => $config['label'] ?? $key,
                'icon' => $config['icon'] ?? '',
                'sort' => $config['sort'] ?? 0,
                'collapsible' => $config['collapsible'] ?? true,
                'collapsed' => $config['collapsed'] ?? false,
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, array<string, mixed>>  $overrides
     * @return list<array<string, mixed>>
     */
    private function normalizeOverridesForForm(array $overrides): array
    {
        $items = [];

        foreach ($overrides as $component => $config) {
            $items[] = [
                'component' => $component,
                'hidden' => $config['hidden'] ?? false,
                'label' => $config['label'] ?? '',
                'group' => $config['group'] ?? '',
                'sort' => $config['sort'] ?? 0,
                'parent_item' => $config['parent_item'] ?? '',
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $formGroups
     * @return array<string, array<string, mixed>>
     */
    private function denormalizeGroupsFromForm(array $formGroups): array
    {
        $groups = [];

        foreach ($formGroups as $item) {
            $key = $item['group_key'] ?? '';

            if ($key === '') {
                continue;
            }

            $config = [];

            if (isset($item['label']) && $item['label'] !== '') {
                $config['label'] = $item['label'];
            }

            if (isset($item['icon']) && $item['icon'] !== '') {
                $config['icon'] = $item['icon'];
            }

            if (isset($item['sort']) && $item['sort'] !== '') {
                $config['sort'] = (int) $item['sort'];
            }

            if (isset($item['collapsible'])) {
                $config['collapsible'] = (bool) $item['collapsible'];
            }

            if (isset($item['collapsed'])) {
                $config['collapsed'] = (bool) $item['collapsed'];
            }

            $groups[$key] = $config;
        }

        return $groups;
    }

    /**
     * @param  list<array<string, mixed>>  $formOverrides
     * @return array<string, array<string, mixed>>
     */
    private function denormalizeOverridesFromForm(array $formOverrides): array
    {
        $overrides = [];

        foreach ($formOverrides as $item) {
            $component = $item['component'] ?? '';

            if ($component === '') {
                continue;
            }

            $config = [];

            if (isset($item['hidden'])) {
                $config['hidden'] = (bool) $item['hidden'];
            }

            if (isset($item['label']) && $item['label'] !== '') {
                $config['label'] = $item['label'];
            }

            if (array_key_exists('group', $item)) {
                $config['group'] = (string) $item['group'];
            }

            if (isset($item['sort']) && $item['sort'] !== '') {
                $config['sort'] = (int) $item['sort'];
            }

            if (isset($item['parent_item']) && $item['parent_item'] !== '') {
                $config['parent_item'] = $item['parent_item'];
            }

            $overrides[$component] = $config;
        }

        return $overrides;
    }

    /**
     * @return array<string, string>
     */
    private function getComponentOptions(): array
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        $options = [];

        foreach ($panel->getResources() as $resource) {
            $class = is_string($resource) ? $resource : $resource::class;
            $label = $class::getNavigationLabel();
            $group = $class::getNavigationGroup();
            $groupPrefix = $group ? "[{$group}] " : '';
            $options[$class] = "{$groupPrefix}[Resource] {$label} — {$class}";
        }

        foreach ($panel->getPages() as $page) {
            $class = is_string($page) ? $page : $page::class;
            $label = $class::getNavigationLabel();
            $group = $class::getNavigationGroup();
            $groupPrefix = $group ? "[{$group}] " : '';
            $options[$class] = "{$groupPrefix}[Page] {$label} — {$class}";
        }

        foreach ($panel->getPageConfigurations() as $configuration) {
            $class = $configuration->getPage();

            if (! is_string($class)) {
                $class = get_class($class);
            }

            $label = $class::getNavigationLabel();
            $group = $class::getNavigationGroup();
            $groupPrefix = $group ? "[{$group}] " : '';
            $options[$class] = "{$groupPrefix}[Page] {$label} — {$class}";
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
            $label = $config['label'] ?? $key;
            $keys[$key] = $label;
        }

        $keys = array_merge($defaultKeys, $keys);

        ksort($keys);

        return $keys;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getDefaultGroups(): array
    {
        $groups = [];
        $configGroups = config('commerce-support.filament.navigation.groups', []);

        foreach ($configGroups as $key => $config) {
            $groups[$key] = [
                'label' => is_array($config) ? ($config['label'] ?? $key) : (is_string($config) ? $config : $key),
                'icon' => is_array($config) ? ($config['icon'] ?? '') : '',
                'sort' => is_array($config) ? ($config['sort'] ?? 0) : 0,
                'collapsible' => is_array($config) ? ($config['collapsible'] ?? true) : true,
                'collapsed' => is_array($config) ? ($config['collapsed'] ?? false) : false,
            ];
        }

        // Also extract group names from all registered resources/pages so they
        // auto-populate. The sidebar groups are built from item group declarations,
        // not from explicit NavigationGroup panel registrations.
        $existingLabels = array_map(
            static fn (array $g): string => mb_strtolower($g['label'] ?? ''),
            $groups,
        );
        $panel = Filament::getCurrentOrDefaultPanel();
        $groupNames = [];
        foreach ($panel->getResources() as $resource) {
            $class = is_string($resource) ? $resource : $resource::class;
            if (method_exists($class, 'getNavigationGroup')) {
                $g = $class::getNavigationGroup();
                if (is_string($g) && $g !== '') {
                    $groupNames[] = $g;
                }
            }
        }
        foreach ($panel->getPages() as $page) {
            $class = is_string($page) ? $page : $page::class;
            if (method_exists($class, 'getNavigationGroup')) {
                $g = $class::getNavigationGroup();
                if (is_string($g) && $g !== '') {
                    $groupNames[] = $g;
                }
            }
        }
        foreach ($panel->getPageConfigurations() as $configuration) {
            $page = $configuration->getPage();
            $class = is_string($page) ? $page : get_class($page);
            if (method_exists($class, 'getNavigationGroup')) {
                $g = $class::getNavigationGroup();
                if (is_string($g) && $g !== '') {
                    $groupNames[] = $g;
                }
            }
        }
        foreach (array_unique($groupNames) as $name) {
            if (in_array(mb_strtolower($name), $existingLabels, true)) {
                continue;
            }
            $groups[$name] = [
                'label' => $name,
                'icon' => '',
                'sort' => 0,
                'collapsible' => true,
                'collapsed' => false,
            ];
        }

        // Sort by sort value, then by label — matching CommerceNavigation::groups().
        uasort($groups, static function (array $a, array $b): int {
            $sortA = (int) ($a['sort'] ?? 0);
            $sortB = (int) ($b['sort'] ?? 0);
            if ($sortA !== $sortB) {
                return $sortA <=> $sortB;
            }

            return strcasecmp($a['label'] ?? '', $b['label'] ?? '');
        });

        return $groups;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getDefaultOverrides(array $mergedGroups = []): array
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        $defaults = [];

        $extract = function (string $class) use (&$defaults) {
            $defaultGroup = method_exists($class, 'getNavigationGroup') ? $class::getNavigationGroup() : null;
            $defaultSort = method_exists($class, 'getNavigationSort') ? $class::getNavigationSort() : null;
            $defaultParent = method_exists($class, 'getNavigationParentItem') ? $class::getNavigationParentItem() : null;

            $group = CommerceNavigation::group($class, $defaultGroup);
            if ($group instanceof \UnitEnum) {
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

        foreach ($panel->getResources() as $resource) {
            $extract(is_string($resource) ? $resource : $resource::class);
        }

        foreach ($panel->getPages() as $page) {
            $extract(is_string($page) ? $page : $page::class);
        }

        foreach ($panel->getPageConfigurations() as $configuration) {
            $page = $configuration->getPage();
            $extract(is_string($page) ? $page : get_class($page));
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
        $userGroups = $settings->groups ?? [];

        $itemIndex = 0;
        foreach ($defaults as $class => &$config) {
            $config['__item_index'] = $itemIndex++;
            $config['__label'] = method_exists($class, 'getNavigationLabel') ? $class::getNavigationLabel() : class_basename($class);
            
            $groupKey = $config['group'] ?? '';
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

        uasort($defaults, function ($a, $b) {
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
            unset($config['__label'], $config['__group_sort'], $config['__item_index']);
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
            if (! array_key_exists($key, $default) || $default[$key] != $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    private function getGroupOptions(): array
    {
        return $this->getGroupKeyOptions();
    }
}
