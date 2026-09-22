<?php

namespace App\Filament\Pages\Reports\Concerns;

use App\Support\ReportPeriod;
use Carbon\Carbon;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

/**
 * Composes Filament's own dashboard-filters machinery (a `filters` array
 * kept in the URL/session, `->live()` so it reacts instantly, and
 * `InteractsWithPageFilters` on the chart widgets picking it up via a
 * Livewire reactive prop) onto a plain report Page, without needing to
 * extend `Filament\Pages\Dashboard` itself.
 */
trait HasReportPeriodFilter
{
    use HasFiltersForm;

    public function filtersForm(Form $form): Form
    {
        return $form->schema(ReportPeriod::formSchema());
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    public function reportDateRange(): array
    {
        return ReportPeriod::range($this->filters);
    }
}
