{{-- Shared by every detail report page: back link, period filter (shared
     `HasReportPeriodFilter` state, so the charts below react to it live)
     and CSV export for the currently selected range. --}}
<a href="{{ \App\Filament\Pages\Reports::getUrl() }}" class="cy-back-link">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
    Back to Reports
</a>

<x-filament::section>
    <div class="cy-filter-bar">
        <div class="cy-filter-form">
            {{ $this->filtersForm }}
        </div>
        <x-filament::button tag="a" :href="$this->exportUrl()" icon="heroicon-o-arrow-down-tray" color="gray" size="sm">
            Export CSV
        </x-filament::button>
    </div>
    @php [$rangeFrom, $rangeTo, $rangeLabel] = $this->reportDateRange(); @endphp
    <p class="cy-note">Showing <strong>{{ $rangeLabel }}</strong> ({{ $rangeFrom->format('d M Y') }} &ndash; {{ $rangeTo->format('d M Y') }})</p>
</x-filament::section>
