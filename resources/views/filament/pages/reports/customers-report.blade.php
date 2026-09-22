<x-filament-panels::page>
    @include('filament.pages.reports.partials.filter-bar')

    @php $customers = $this->customerReport(); @endphp
    <x-filament::section>
        <x-slot name="heading">New vs Returning</x-slot>
        <div class="cy-kpis">
            @foreach ([
                'New Accounts' => $customers['new_customers'],
                'First-Time Buyers' => $customers['first_time_buyers'],
                'Returning Buyers' => $customers['returning_customers'],
            ] as $label => $value)
                <div class="cy-kpi">
                    <div class="cy-kpi-label">{{ $label }}</div>
                    <div class="cy-kpi-value">{{ $value }}</div>
                </div>
            @endforeach
        </div>
        <p class="cy-note">New Accounts registered in the period. First-Time Buyers made their first paid order in it. Returning Buyers paid for an order in it and had already bought before it started.</p>
    </x-filament::section>

    @livewire(\App\Filament\Pages\Reports\Widgets\CustomerAcquisitionChartWidget::class, ['filters' => $this->filters])

    <div class="cy-cols">
        <x-filament::section>
            <x-slot name="heading">Most Active <span class="cy-muted">(this period)</span></x-slot>
            <div class="cy-table-wrap">
                <table class="cy-table">
                    <thead><tr><th>Customer</th><th class="r">Orders</th><th class="r">Spent</th></tr></thead>
                    <tbody>
                        @forelse ($this->mostActiveCustomers() as $row)
                            <tr>
                                <td>{{ $row->name }}</td>
                                <td class="r">{{ $row->order_count }}</td>
                                <td class="r">GHS {{ number_format($row->total_spent, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="cy-empty">No customer orders in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Lifetime Value <span class="cy-muted">(all-time)</span></x-slot>
            <div class="cy-table-wrap">
                <table class="cy-table">
                    <thead><tr><th>Customer</th><th class="r">Orders</th><th class="r">LTV</th></tr></thead>
                    <tbody>
                        @forelse ($this->customerLifetimeValue() as $row)
                            <tr>
                                <td>{{ $row->name }}</td>
                                <td class="r">{{ $row->lifetime_orders }}</td>
                                <td class="r">GHS {{ number_format($row->lifetime_value, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="cy-empty">No paid customers yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
