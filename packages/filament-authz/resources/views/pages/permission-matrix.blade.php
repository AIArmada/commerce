<x-filament-panels::page>
    <style>
        .pm-toggle {
            width: 44px;
            height: 24px;
            background-color: #d1d5db;
            border-radius: 9999px;
            position: relative;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .pm-toggle.active {
            background-color: #7c3aed;
        }
        .pm-toggle::after {
            content: '';
            width: 18px;
            height: 18px;
            background: white;
            border-radius: 50%;
            position: absolute;
            top: 3px;
            left: 3px;
            transition: transform 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .pm-toggle.active::after {
            transform: translateX(20px);
        }
        .dark .pm-toggle {
            background-color: #374151;
        }
        .dark .pm-toggle.active {
            background-color: #7c3aed;
        }
        .tab-btn {
            padding: 12px 24px;
            font-weight: 500;
            font-size: 14px;
            border-radius: 12px 12px 0 0;
            border: 1px solid transparent;
            border-bottom: none;
            cursor: pointer;
            transition: all 0.2s;
            background: transparent;
            color: #6b7280;
        }
        .tab-btn:hover {
            color: #374151;
            background: #f9fafb;
        }
        .tab-btn.active {
            background: white;
            color: #7c3aed;
            border-color: #e5e7eb;
            font-weight: 600;
        }
        .dark .tab-btn {
            color: #9ca3af;
        }
        .dark .tab-btn:hover {
            color: #e5e7eb;
            background: #1f2937;
        }
        .dark .tab-btn.active {
            background: #111827;
            color: #a78bfa;
            border-color: #374151;
        }
        .tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 9999px;
            margin-left: 8px;
        }
        .tab-badge-gray {
            background: #f3f4f6;
            color: #6b7280;
        }
        .tab-badge-purple {
            background: #ede9fe;
            color: #7c3aed;
        }
        .dark .tab-badge-gray {
            background: #374151;
            color: #9ca3af;
        }
        .dark .tab-badge-purple {
            background: rgba(167, 139, 250, 0.2);
            color: #a78bfa;
        }
    </style>

    <div x-data="{ 
        activeTab: 'resources',
        searchQuery: '', 
        collapsed: {},
        toggle(g) { this.collapsed[g] = !this.collapsed[g]; },
        isOpen(g) { return !this.collapsed[g]; }
    }">
        @if(!$selectedRole)
            {{-- Empty State --}}
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 80px 16px; text-align: center;">
                <div style="width: 96px; height: 96px; margin-bottom: 24px; border-radius: 24px; background: linear-gradient(135deg, #ede9fe, #ddd6fe); display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 40px; height: 40px; color: #7c3aed;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                </div>
                <h2 style="font-size: 24px; font-weight: 700; color: #111827; margin-bottom: 12px;">Permission Matrix</h2>
                <p style="color: #6b7280; max-width: 400px; margin-bottom: 32px; font-size: 16px; line-height: 1.6;">
                    Manage role-based access control. Select a role to begin editing permissions.
                </p>
                <div style="display: inline-flex; align-items: center; font-size: 14px; font-weight: 500; color: #7c3aed; background: #ede9fe; padding: 8px 16px; border-radius: 9999px;">
                    <svg style="width: 16px; height: 16px; margin-right: 8px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18" />
                    </svg>
                    Click "Select Role" above
                </div>
            </div>
        @else
            @php
                $typedData = $this->getMatrixDataByType();
                $resourceCount = collect($typedData['resources'])->flatten(1)->count();
                $pageCount = count($typedData['pages']);
                $widgetCount = count($typedData['widgets']);
                $total = $resourceCount + $pageCount + $widgetCount;
                
                $resourceEnabled = collect($typedData['resources'])->flatten(1)->where('has', true)->count();
                $pageEnabled = collect($typedData['pages'])->where('has', true)->count();
                $widgetEnabled = collect($typedData['widgets'])->where('has', true)->count();
                $totalEnabled = $resourceEnabled + $pageEnabled + $widgetEnabled;
                
                $pct = $total > 0 ? round(($totalEnabled / $total) * 100) : 0;
            @endphp

            {{-- Header with Stats --}}
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                {{-- Stats Card --}}
                <div style="background: white; border-radius: 16px; padding: 24px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
                        <span style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Currently Editing</span>
                    </div>
                    <h1 style="font-size: 28px; font-weight: 700; color: #111827; margin-bottom: 20px; text-transform: capitalize;">
                        {{ $this->getSelectedRoleName() }} Role
                    </h1>
                    <div style="display: flex; align-items: flex-end; gap: 32px;">
                        <div>
                            <div style="font-size: 28px; font-weight: 700; color: #111827;">{{ $totalEnabled }}</div>
                            <div style="font-size: 13px; color: #6b7280;">Enabled</div>
                        </div>
                        <div>
                            <div style="font-size: 28px; font-weight: 700; color: #9ca3af;">{{ $total - $totalEnabled }}</div>
                            <div style="font-size: 13px; color: #6b7280;">Disabled</div>
                        </div>
                        <div style="flex: 1; max-width: 200px;">
                            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
                                <span style="color: #374151;">Coverage</span>
                                <span style="font-weight: 600; color: #7c3aed;">{{ $pct }}%</span>
                            </div>
                            <div style="height: 8px; background: #f3f4f6; border-radius: 9999px; overflow: hidden;">
                                <div style="height: 100%; background: #7c3aed; border-radius: 9999px; width: {{ $pct }}%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Quick Stats per Type --}}
                <div style="background: #f9fafb; border-radius: 16px; padding: 24px; border: 1px solid #e5e7eb; display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
                    <div style="text-align: center; padding: 16px; background: white; border-radius: 12px; border: 1px solid #e5e7eb;">
                        <div style="font-size: 24px; font-weight: 700; color: #111827;">{{ $resourceEnabled }}/{{ $resourceCount }}</div>
                        <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">Resources</div>
                    </div>
                    <div style="text-align: center; padding: 16px; background: white; border-radius: 12px; border: 1px solid #e5e7eb;">
                        <div style="font-size: 24px; font-weight: 700; color: #111827;">{{ $pageEnabled }}/{{ $pageCount }}</div>
                        <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">Pages</div>
                    </div>
                    <div style="text-align: center; padding: 16px; background: white; border-radius: 12px; border: 1px solid #e5e7eb;">
                        <div style="font-size: 24px; font-weight: 700; color: #111827;">{{ $widgetEnabled }}/{{ $widgetCount }}</div>
                        <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">Widgets</div>
                    </div>
                </div>
            </div>

            {{-- Tabs --}}
            <div style="display: flex; align-items: flex-end; gap: 4px; border-bottom: 1px solid #e5e7eb; margin-bottom: 0;">
                <button 
                    @click="activeTab = 'resources'" 
                    :class="activeTab === 'resources' ? 'active' : ''"
                    class="tab-btn"
                >
                    <span style="display: flex; align-items: center;">
                        <svg style="width: 16px; height: 16px; margin-right: 8px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>
                        Resources
                        <span class="tab-badge" :class="activeTab === 'resources' ? 'tab-badge-purple' : 'tab-badge-gray'">{{ $resourceCount }}</span>
                    </span>
                </button>
                <button 
                    @click="activeTab = 'pages'" 
                    :class="activeTab === 'pages' ? 'active' : ''"
                    class="tab-btn"
                >
                    <span style="display: flex; align-items: center;">
                        <svg style="width: 16px; height: 16px; margin-right: 8px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                        Pages
                        <span class="tab-badge" :class="activeTab === 'pages' ? 'tab-badge-purple' : 'tab-badge-gray'">{{ $pageCount }}</span>
                    </span>
                </button>
                <button 
                    @click="activeTab = 'widgets'" 
                    :class="activeTab === 'widgets' ? 'active' : ''"
                    class="tab-btn"
                >
                    <span style="display: flex; align-items: center;">
                        <svg style="width: 16px; height: 16px; margin-right: 8px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                        </svg>
                        Widgets
                        <span class="tab-badge" :class="activeTab === 'widgets' ? 'tab-badge-purple' : 'tab-badge-gray'">{{ $widgetCount }}</span>
                    </span>
                </button>
            </div>

            {{-- Tab Content Container --}}
            <div style="background: white; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 16px 16px; padding: 24px;">
                
                {{-- Search & Bulk Actions --}}
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #f3f4f6;">
                    <div style="position: relative; width: 300px;">
                        <svg style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: #9ca3af;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        <input 
                            type="text" 
                            x-model="searchQuery"
                            placeholder="Search permissions..." 
                            style="width: 100%; padding: 10px 12px 10px 40px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; font-size: 14px; outline: none;"
                        >
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button 
                            @click="$wire.selectTypePermissions(activeTab)"
                            style="display: flex; align-items: center; gap: 6px; padding: 10px 16px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; font-size: 13px; font-weight: 500; color: #15803d; cursor: pointer;"
                        >
                            <svg style="width: 14px; height: 14px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            Select All
                        </button>
                        <button 
                            @click="$wire.deselectTypePermissions(activeTab)"
                            style="display: flex; align-items: center; gap: 6px; padding: 10px 16px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; font-size: 13px; font-weight: 500; color: #dc2626; cursor: pointer;"
                        >
                            <svg style="width: 14px; height: 14px;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Clear All
                        </button>
                    </div>
                </div>

                {{-- Resources Tab --}}
                <div x-show="activeTab === 'resources'" style="display: flex; flex-direction: column; gap: 16px;">
                    @foreach($typedData['resources'] as $group => $permissions)
                        @php
                            $groupEnabled = collect($permissions)->where('has', true)->count();
                        @endphp
                        
                        <div 
                            x-show="searchQuery === '' || Object.values({{ json_encode($permissions) }}).some(p => p.name.toLowerCase().includes(searchQuery.toLowerCase()))"
                            style="background: #f9fafb; border-radius: 12px; border: 1px solid #e5e7eb; overflow: hidden;"
                        >
                            {{-- Group Header --}}
                            <div 
                                @click="toggle('{{ $group }}')"
                                style="display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; cursor: pointer; background: white; border-bottom: 1px solid #e5e7eb;"
                            >
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #ede9fe; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; color: #7c3aed; text-transform: uppercase;">
                                        {{ substr($group, 0, 2) }}
                                    </div>
                                    <div>
                                        <div style="font-size: 15px; font-weight: 600; color: #111827; text-transform: capitalize;">
                                            {{ str_replace('_', ' ', $group) }}
                                        </div>
                                        <div style="font-size: 12px; color: #6b7280;">
                                            <span style="{{ $groupEnabled > 0 ? 'color: #7c3aed; font-weight: 500;' : '' }}">{{ $groupEnabled }}</span> / {{ count($permissions) }} enabled
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div @click.stop style="display: flex; gap: 4px;">
                                        <button 
                                            wire:click="selectGroupPermissions('{{ $group }}')"
                                            style="padding: 6px 10px; border-radius: 6px; background: #f0fdf4; border: 1px solid #bbf7d0; cursor: pointer; font-size: 11px; color: #15803d;"
                                        >Select All</button>
                                        <button 
                                            wire:click="deselectGroupPermissions('{{ $group }}')"
                                            style="padding: 6px 10px; border-radius: 6px; background: #fef2f2; border: 1px solid #fecaca; cursor: pointer; font-size: 11px; color: #dc2626;"
                                        >Clear</button>
                                    </div>
                                    <svg 
                                        style="width: 18px; height: 18px; color: #9ca3af; transition: transform 0.2s;"
                                        :style="isOpen('{{ $group }}') ? 'transform: rotate(180deg)' : ''"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </div>
                            </div>

                            {{-- Permissions Grid --}}
                            <div x-show="isOpen('{{ $group }}')" x-collapse>
                                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1px; background: #e5e7eb; padding: 1px;">
                                    @foreach($permissions as $permissionName => $permissionData)
                                        @php
                                            $shortName = str_replace($group . '.', '', $permissionName);
                                            $humanName = ucwords(str_replace(['_', '-'], ' ', $shortName));
                                        @endphp
                                        <div 
                                            wire:click="togglePermission('{{ $permissionData['id'] }}')"
                                            x-show="searchQuery === '' || '{{ strtolower($permissionName) }}'.includes(searchQuery.toLowerCase())"
                                            style="background: white; padding: 12px 14px; display: flex; align-items: center; justify-content: space-between; cursor: pointer;"
                                        >
                                            <span style="font-size: 13px; font-weight: 500; color: {{ $permissionData['has'] ? '#7c3aed' : '#374151' }};">
                                                {{ $humanName }}
                                            </span>
                                            <div class="pm-toggle {{ $permissionData['has'] ? 'active' : '' }}"></div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pages Tab --}}
                <div x-show="activeTab === 'pages'">
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                        @foreach($typedData['pages'] as $permissionName => $permissionData)
                            @php
                                $shortName = str_replace('page.', '', $permissionName);
                                $humanName = ucwords(str_replace(['-', '_'], ' ', $shortName));
                            @endphp
                            <div 
                                wire:click="togglePermission('{{ $permissionData['id'] }}')"
                                x-show="searchQuery === '' || '{{ strtolower($humanName) }}'.includes(searchQuery.toLowerCase())"
                                style="background: {{ $permissionData['has'] ? '#faf5ff' : '#f9fafb' }}; border: 1px solid {{ $permissionData['has'] ? '#e9d5ff' : '#e5e7eb' }}; border-radius: 10px; padding: 16px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: all 0.15s;"
                            >
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 36px; height: 36px; border-radius: 8px; background: {{ $permissionData['has'] ? '#ede9fe' : '#f3f4f6' }}; display: flex; align-items: center; justify-content: center;">
                                        <svg style="width: 18px; height: 18px; color: {{ $permissionData['has'] ? '#7c3aed' : '#9ca3af' }};" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                        </svg>
                                    </div>
                                    <span style="font-size: 14px; font-weight: 500; color: {{ $permissionData['has'] ? '#7c3aed' : '#374151' }};">
                                        {{ $humanName }}
                                    </span>
                                </div>
                                <div class="pm-toggle {{ $permissionData['has'] ? 'active' : '' }}"></div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Widgets Tab --}}
                <div x-show="activeTab === 'widgets'">
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                        @foreach($typedData['widgets'] as $permissionName => $permissionData)
                            @php
                                $shortName = str_replace('widget.', '', $permissionName);
                                $humanName = ucwords(str_replace(['_', '-'], ' ', $shortName));
                            @endphp
                            <div 
                                wire:click="togglePermission('{{ $permissionData['id'] }}')"
                                x-show="searchQuery === '' || '{{ strtolower($humanName) }}'.includes(searchQuery.toLowerCase())"
                                style="background: {{ $permissionData['has'] ? '#ecfdf5' : '#f9fafb' }}; border: 1px solid {{ $permissionData['has'] ? '#a7f3d0' : '#e5e7eb' }}; border-radius: 10px; padding: 16px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; transition: all 0.15s;"
                            >
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 36px; height: 36px; border-radius: 8px; background: {{ $permissionData['has'] ? '#d1fae5' : '#f3f4f6' }}; display: flex; align-items: center; justify-content: center;">
                                        <svg style="width: 18px; height: 18px; color: {{ $permissionData['has'] ? '#059669' : '#9ca3af' }};" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                                        </svg>
                                    </div>
                                    <span style="font-size: 14px; font-weight: 500; color: {{ $permissionData['has'] ? '#059669' : '#374151' }};">
                                        {{ $humanName }}
                                    </span>
                                </div>
                                <div class="pm-toggle {{ $permissionData['has'] ? 'active' : '' }}"></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
