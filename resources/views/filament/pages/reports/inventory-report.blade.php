<x-filament-panels::page>
    @include('filament.pages.reports.partials.filter-bar')

    <x-filament::section>
        <x-slot name="heading">Stock Overview</x-slot>

        @php $inventory = $this->inventoryReport(); @endphp
        <div class="cy-kpis">
            @foreach ([
                'Total SKUs' => $inventory['total_skus'],
                'Out of Stock' => $inventory['out_of_stock'],
                'Low Stock' => $inventory['low_stock'],
                'Inventory Valuation' => 'GHS '.number_format($inventory['inventory_valuation'], 2),
            ] as $label => $value)
                <div class="cy-kpi">
                    <div class="cy-kpi-label">{{ $label }}</div>
                    <div class="cy-kpi-value">{{ $value }}</div>
                </div>
            @endforeach
        </div>
        <p class="cy-note">Valuation is stock on hand at current selling price - always live. The period filter applies to write-offs and movement history below.</p>
    </x-filament::section>

    @livewire(\App\Filament\Pages\Reports\Widgets\InventoryValueByCategoryChartWidget::class)

    <div class="cy-cols">
        {{-- Wide tables (more columns) share this cell so they get more room --}}
        <div class="cy-stack">
            <x-filament::section>
                <x-slot name="heading">Current Stock Levels <span class="cy-muted">(lowest first)</span></x-slot>
                <div class="cy-table-wrap">
                    <table class="cy-table">
                        <thead><tr><th>Product</th><th class="r">Sellable</th><th class="r">Reserved</th><th class="r">Value</th></tr></thead>
                        <tbody>
                            @foreach ($this->stockLevels() as $product)
                                <tr>
                                    <td>{{ $product->name }}</td>
                                    <td class="r">{{ $product->sellable_quantity }} {{ $product->unit_of_measurement }}</td>
                                    <td class="r">{{ $product->reserved_quantity }}</td>
                                    <td class="r">GHS {{ number_format($product->available_quantity * $product->selling_price, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Stock Movement History</x-slot>
                <div class="cy-table-wrap">
                    <table class="cy-table">
                        <thead><tr><th>When</th><th>Product</th><th>Type</th><th class="r">Qty</th></tr></thead>
                        <tbody>
                            @forelse ($this->stockMovementHistory() as $movement)
                                <tr>
                                    <td class="cy-muted">{{ $movement->created_at->format('d M H:i') }}</td>
                                    <td>{{ $movement->product?->name ?? 'Deleted product' }}</td>
                                    <td>{{ $movement->type->label() }}</td>
                                    <td class="r">{{ $movement->quantity }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="cy-empty">No stock movement in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>

        {{-- Narrower tables (fewer columns) pair together --}}
        <div class="cy-stack">
            <x-filament::section>
                <x-slot name="heading">Low Stock Alerts</x-slot>
                <div class="cy-table-wrap">
                    <table class="cy-table">
                        <thead><tr><th>Product</th><th class="r">Sellable</th></tr></thead>
                        <tbody>
                            @forelse ($this->lowStockProducts() as $product)
                                <tr>
                                    <td>{{ $product->name }}</td>
                                    <td class="r">{{ $product->sellable_quantity }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="cy-empty">Nothing low on stock.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Spoilage / Write-Offs</x-slot>
                <div class="cy-table-wrap">
                    <table class="cy-table">
                        <thead><tr><th>Product</th><th class="r">Qty</th><th>Note</th></tr></thead>
                        <tbody>
                            @forelse ($this->spoilageWriteOffs() as $movement)
                                <tr>
                                    <td>{{ $movement->product?->name ?? 'Deleted product' }}</td>
                                    <td class="r">{{ $movement->quantity }}</td>
                                    <td class="cy-muted">{{ $movement->note }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="cy-empty">No write-offs in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
