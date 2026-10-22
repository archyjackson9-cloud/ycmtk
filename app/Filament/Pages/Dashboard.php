<?php

namespace App\Filament\Pages;

use App\Filament\Resources\BannerResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\StockMovementResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

/**
 * Staff home. Heading greets the person, the header buttons are the few
 * things each role does most (each one appears only if the role may use
 * it), and the widgets below are ordered by urgency: KPIs, "needs your
 * attention", then the live lists and the trend. Which widgets show is
 * decided by each widget's own canView().
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard';

    public function getHeading(): string|Htmlable
    {
        $hour = (int) now()->format('G');
        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };

        $first = Str::of((string) auth()->user()?->name)->before(' ')->toString();

        return trim($greeting.', '.$first, ', ');
    }

    public function getSubheading(): string|Htmlable|null
    {
        $role = auth()->user()?->roles->first()?->name;

        return now()->format('l, j F Y').($role ? ' · '.Str::headline($role) : '');
    }

    public function getColumns(): int|string|array
    {
        return ['default' => 1, 'lg' => 2];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reviewPayments')
                ->label('Confirm payments')
                ->icon('heroicon-o-banknotes')
                ->url(fn () => PaymentResource::getUrl('index', ['activeTab' => 'to_confirm']))
                ->visible(fn () => PaymentResource::canViewAny()),
            Action::make('orders')
                ->label('Orders to process')
                ->icon('heroicon-o-shopping-cart')
                ->color('gray')
                ->url(fn () => OrderResource::getUrl('index', ['activeTab' => 'needs_action']))
                ->visible(fn () => OrderResource::canViewAny()),
            Action::make('addStock')
                ->label('Record stock')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('gray')
                ->url(fn () => StockMovementResource::getUrl('create'))
                ->visible(fn () => StockMovementResource::canViewAny() && StockMovementResource::canCreate()),
            Action::make('newProduct')
                ->label('New product')
                ->icon('heroicon-o-plus')
                ->color('gray')
                ->url(fn () => ProductResource::getUrl('create'))
                ->visible(fn () => ProductResource::canCreate()),
            Action::make('newBanner')
                ->label('New banner')
                ->icon('heroicon-o-plus')
                ->color(fn () => auth()->user()?->isSuperAdmin() ? 'gray' : 'primary')
                ->url(fn () => BannerResource::getUrl('create'))
                ->visible(fn () => BannerResource::canViewAny() && BannerResource::canCreate()),
        ];
    }
}
