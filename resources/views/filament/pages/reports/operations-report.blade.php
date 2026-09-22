<x-filament-panels::page>
    @include('filament.pages.reports.partials.filter-bar')

    @php $ops = $this->operationsReport(); @endphp
    <x-filament::section>
        <x-slot name="heading">Revenue</x-slot>
        <div class="cy-kpis">
            @foreach ([
                'Gross Revenue' => 'GHS '.number_format($ops['gross_revenue'], 2),
                'Refunded' => 'GHS '.number_format($ops['refunded_amount'], 2),
                'Net of Refunds' => 'GHS '.number_format($ops['net_revenue'], 2),
            ] as $label => $value)
                <div class="cy-kpi">
                    <div class="cy-kpi-label">{{ $label }}</div>
                    <div class="cy-kpi-value">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    @livewire(\App\Filament\Pages\Reports\Widgets\OperationsByStatusChartWidget::class, ['filters' => $this->filters])

    @php $fulfilment = $this->fulfilmentPerformance(); @endphp
    <x-filament::section>
        <x-slot name="heading">Fulfilment Summary</x-slot>
        <div class="cy-kpis">
            <div class="cy-kpi">
                <div class="cy-kpi-label">Total Orders</div>
                <div class="cy-kpi-value">{{ $fulfilment['total_orders'] }}</div>
            </div>
            <div class="cy-kpi">
                <div class="cy-kpi-label">Cancelled</div>
                <div class="cy-kpi-value">{{ $fulfilment['cancelled_orders'] }}</div>
            </div>
            <div class="cy-kpi">
                <div class="cy-kpi-label">Cancellation Rate</div>
                <div class="cy-kpi-value">{{ $fulfilment['cancellation_rate'] }}%</div>
            </div>
        </div>
    </x-filament::section>

    @livewire(\App\Filament\Pages\Reports\Widgets\FulfilmentPerformanceChartWidget::class, ['filters' => $this->filters])
</x-filament-panels::page>
