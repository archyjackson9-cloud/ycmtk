<x-filament-panels::page>
    @include('filament.pages.reports.partials.filter-bar')

    <div class="cy-cols">
        @livewire(\App\Filament\Pages\Reports\Widgets\SalesTrendChartWidget::class, ['filters' => $this->filters])
        @livewire(\App\Filament\Pages\Reports\Widgets\SalesByCategoryChartWidget::class, ['filters' => $this->filters])
    </div>

    <x-filament::section>
        <x-slot name="heading">Revenue Summary</x-slot>

        @php $sales = $this->salesSummary(); @endphp
        <div class="cy-kpis">
            @foreach ([
                'Paid Orders' => $sales['order_count'],
                'Gross Revenue' => 'GHS '.number_format($sales['gross_revenue'], 2),
                'Refunded' => 'GHS '.number_format($sales['refunded_amount'], 2),
                'Net Revenue' => 'GHS '.number_format($sales['net_revenue'], 2),
                'Delivery Fees' => 'GHS '.number_format($sales['delivery_fees'], 2),
                'Avg Order Value' => 'GHS '.number_format($sales['average_order_value'] ?? 0, 2),
            ] as $label => $value)
                <div class="cy-kpi">
                    <div class="cy-kpi-label">{{ $label }}</div>
                    <div class="cy-kpi-value">{{ $value }}</div>
                </div>
            @endforeach
        </div>
        <p class="cy-note">Net revenue counts orders from Paid through Completed. Gross adds back orders cancelled after payment, which are shown as Refunded.</p>
    </x-filament::section>

    <div class="cy-cols">
        <x-filament::section>
            <x-slot name="heading">By Product</x-slot>
            <div class="cy-table-wrap">
                <table class="cy-table">
                    <thead><tr><th>Product</th><th class="r">Units</th><th class="r">Revenue</th></tr></thead>
                    <tbody>
                        @forelse ($this->salesByProduct() as $row)
                            <tr>
                                <td>{{ $row->product }}</td>
                                <td class="r">{{ $row->units_sold }}</td>
                                <td class="r">GHS {{ number_format($row->revenue, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="cy-empty">No sales in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">By Location</x-slot>
            <div class="cy-table-wrap">
                <table class="cy-table">
                    <thead><tr><th>Zone</th><th class="r">Orders</th><th class="r">Revenue</th></tr></thead>
                    <tbody>
                        @forelse ($this->salesByLocation() as $row)
                            <tr>
                                <td>{{ $row->location }}</td>
                                <td class="r">{{ $row->orders }}</td>
                                <td class="r">GHS {{ number_format($row->revenue, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="cy-empty">No sales in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
