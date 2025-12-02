<x-filament-widgets::widget>
    @php
        $events = $this->getTimelineEvents();
        $stats = $this->getSummaryStats();
    @endphp

    <x-filament::section
        icon="heroicon-o-clock"
        heading="Usage History"
        description="Complete timeline of voucher redemptions and activity"
    >
        <div class="fi-ta-content">
            {{-- Summary Stats Bar --}}
            @if($stats['total_redemptions'] > 0)
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem; padding: 1rem; background: rgba(0,0,0,0.05); border-radius: 0.75rem;">
                    <div style="text-align: center;">
                        <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-600);">{{ $stats['total_redemptions'] }}</div>
                        <div style="font-size: 0.75rem; color: #6b7280;">Redemptions</div>
                    </div>
                    <div style="text-align: center; border-left: 1px solid rgba(128,128,128,0.3); border-right: 1px solid rgba(128,128,128,0.3);">
                        <div style="font-size: 1.5rem; font-weight: 700; color: #059669;">{{ $stats['total_savings'] }}</div>
                        <div style="font-size: 0.75rem; color: #6b7280;">Total Savings</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 1.5rem; font-weight: 700; color: #0ea5e9;">{{ $stats['unique_customers'] }}</div>
                        <div style="font-size: 0.75rem; color: #6b7280;">Customers</div>
                    </div>
                </div>
            @endif

            {{-- Timeline --}}
            @if($events->isNotEmpty())
                <div style="position: relative; padding-left: 2rem;">
                    {{-- Vertical Timeline Line --}}
                    <div style="position: absolute; left: 0.5rem; top: 0; bottom: 0; width: 2px; background: linear-gradient(to bottom, #f97316, #d1d5db);"></div>

                    @foreach($events as $index => $event)
                        <div style="position: relative; margin-bottom: 1.5rem;">
                            {{-- Timeline Node --}}
                            <div style="position: absolute; left: -1.75rem; top: 0; width: 1.25rem; height: 1.25rem; border-radius: 50%; background: {{ $event['color'] === 'success' ? '#10b981' : '#f97316' }}; border: 3px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.2);"></div>

                            {{-- Arrow pointing to node --}}
                            <div style="position: absolute; left: -0.25rem; top: 0.35rem; width: 0; height: 0; border-top: 6px solid transparent; border-bottom: 6px solid transparent; border-right: 8px solid rgba(0,0,0,0.1);"></div>

                            {{-- Content Card --}}
                            <div style="background: rgba(0,0,0,0.03); border: 1px solid rgba(128,128,128,0.2); border-radius: 0.75rem; overflow: hidden;">
                                {{-- Header --}}
                                <div style="padding: 1rem; background: linear-gradient(to right, rgba(249,115,22,0.1), transparent); border-bottom: 1px solid rgba(128,128,128,0.1);">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem;">
                                        <div>
                                            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                                                <span style="font-size: 1rem; font-weight: 700;">{{ $event['title'] }}</span>
                                                @if($event['details']['order_number'] ?? null)
                                                    <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.75rem; background: #dcfce7; color: #166534; font-size: 0.75rem; font-weight: 600; border-radius: 9999px;">
                                                        🛒 {{ $event['details']['order_number'] }}
                                                    </span>
                                                @endif
                                            </div>
                                            <p style="margin-top: 0.25rem; font-size: 0.875rem; color: #6b7280;">{{ $event['description'] }}</p>
                                        </div>
                                        <div style="text-align: right; flex-shrink: 0;">
                                            <div style="font-size: 0.875rem; font-weight: 600;">{{ $event['timestamp']->format('M d, Y') }}</div>
                                            <div style="font-size: 0.75rem; color: #6b7280;">{{ $event['timestamp']->format('g:i A') }}</div>
                                            <div style="font-size: 0.75rem; color: #f97316; font-weight: 500;">{{ $event['timestamp_human'] }}</div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Details Grid --}}
                                <div style="padding: 1rem;">
                                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                                        {{-- Discount --}}
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <div style="width: 2rem; height: 2rem; background: #dcfce7; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center;">💰</div>
                                            <div>
                                                <div style="font-size: 0.625rem; color: #6b7280; text-transform: uppercase;">Discount</div>
                                                <div style="font-size: 0.875rem; font-weight: 700; color: #059669;">{{ $event['details']['savings'] }}</div>
                                            </div>
                                        </div>

                                        {{-- Channel --}}
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <div style="width: 2rem; height: 2rem; background: #fef3c7; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center;">⚡</div>
                                            <div>
                                                <div style="font-size: 0.625rem; color: #6b7280; text-transform: uppercase;">Channel</div>
                                                <div style="font-size: 0.875rem; font-weight: 600; text-transform: capitalize;">{{ $event['details']['channel'] }}</div>
                                            </div>
                                        </div>

                                        {{-- Order Total --}}
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <div style="width: 2rem; height: 2rem; background: #e0e7ff; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center;">📦</div>
                                            <div>
                                                <div style="font-size: 0.625rem; color: #6b7280; text-transform: uppercase;">Order Total</div>
                                                <div style="font-size: 0.875rem; font-weight: 600;">
                                                    @if($event['details']['grand_total'] ?? null)
                                                        {{ \Akaunting\Money\Money::MYR($event['details']['grand_total'])->format() }}
                                                    @elseif($event['details']['cart_total'] ?? null)
                                                        {{ \Akaunting\Money\Money::MYR($event['details']['cart_total'])->format() }}
                                                    @else
                                                        —
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Subtotal --}}
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <div style="width: 2rem; height: 2rem; background: #f3e8ff; border-radius: 0.5rem; display: flex; align-items: center; justify-content: center;">🧮</div>
                                            <div>
                                                <div style="font-size: 0.625rem; color: #6b7280; text-transform: uppercase;">Subtotal</div>
                                                <div style="font-size: 0.875rem; font-weight: 600;">
                                                    @if($event['details']['subtotal'] ?? null)
                                                        {{ \Akaunting\Money\Money::MYR($event['details']['subtotal'])->format() }}
                                                    @else
                                                        —
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    {{-- End Marker --}}
                    <div style="position: relative;">
                        <div style="position: absolute; left: -1.5rem; top: 0; width: 0.75rem; height: 0.75rem; border-radius: 50%; background: #9ca3af;"></div>
                        <p style="font-size: 0.75rem; color: #9ca3af; font-style: italic;">End of history</p>
                    </div>
                </div>
            @else
                {{-- Empty State --}}
                <div style="text-align: center; padding: 3rem 1rem;">
                    <div style="width: 4rem; height: 4rem; margin: 0 auto 1rem; background: #f3f4f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">⏰</div>
                    <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem;">No Usage History Yet</h3>
                    <p style="font-size: 0.875rem; color: #6b7280; max-width: 20rem; margin: 0 auto;">
                        This voucher hasn't been redeemed yet. Usage activity will appear here once customers start using this voucher.
                    </p>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
