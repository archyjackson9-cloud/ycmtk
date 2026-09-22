<x-filament-panels::page>
    <p class="cy-note -mt-2">Pick a report to see its filters, charts and export options.</p>

    <div class="cy-report-grid">
        @foreach ($this->cards() as $card)
            <a href="{{ $card['url'] }}" class="cy-report-card">
                <span class="cy-report-card-icon {{ $card['color'] }}">
                    <x-filament::icon :icon="$card['icon']" class="h-6 w-6 text-white" />
                </span>

                <div>
                    <div class="cy-report-card-title">{{ $card['title'] }}</div>
                    <p class="cy-report-card-desc">{{ $card['description'] }}</p>
                </div>

                <div class="cy-report-card-stat">
                    <span class="cy-kpi-label">{{ $card['statLabel'] }}</span>
                    <span class="cy-report-card-stat-value">{{ $card['statValue'] }}</span>
                </div>

                <span class="cy-report-card-cta">
                    View report
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </span>
            </a>
        @endforeach
    </div>
</x-filament-panels::page>
