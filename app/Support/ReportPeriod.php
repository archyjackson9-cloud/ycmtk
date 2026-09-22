<?php

namespace App\Support;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Get;

/**
 * Shared period-filter logic for the admin Reports pages and their chart
 * widgets (TOR §6.11 "daily / weekly / monthly filters"). A report Page
 * (via Filament's HasFiltersForm) and its ChartWidgets (via
 * InteractsWithPageFilters) both end up holding the same `filters` array
 * shape - `['period' => ..., 'from' => ..., 'to' => ...]` - so the actual
 * from/to/label resolution lives in exactly one place instead of being
 * duplicated per page and per widget.
 */
class ReportPeriod
{
    /**
     * @param  array<string, mixed>|null  $filters
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    public static function range(?array $filters): array
    {
        $period = $filters['period'] ?? 'this_month';
        $now = now();

        [$from, $to, $label] = match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'Today'],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek(), 'This Week'],
            'custom' => [
                filled($filters['from'] ?? null) ? Carbon::parse($filters['from'])->startOfDay() : $now->copy()->startOfMonth(),
                filled($filters['to'] ?? null) ? Carbon::parse($filters['to'])->endOfDay() : $now->copy()->endOfDay(),
                'Custom Range',
            ],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'This Month'],
        };

        // A reversed custom range (From after To) would silently report
        // nothing - treat it as the same span the other way round.
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to, $label];
    }

    /**
     * @return array<\Filament\Forms\Components\Component>
     */
    public static function formSchema(): array
    {
        return [
            Select::make('period')
                ->label('Period')
                ->options([
                    'today' => 'Today',
                    'this_week' => 'This Week',
                    'this_month' => 'This Month',
                    'custom' => 'Custom Range',
                ])
                ->default('this_month')
                ->required()
                ->live(),
            DatePicker::make('from')
                ->label('From')
                ->visible(fn (Get $get) => $get('period') === 'custom')
                ->live(),
            DatePicker::make('to')
                ->label('To')
                ->visible(fn (Get $get) => $get('period') === 'custom')
                ->live(),
        ];
    }
}
