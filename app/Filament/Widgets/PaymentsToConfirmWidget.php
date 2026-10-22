<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\PaymentResource;
use App\Models\Payment;
use App\Support\AdminSnapshot;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Pay-by-transfer is the live payment method, so the oldest unconfirmed
 * transfers sit on the home page with proof and Confirm/Reject right
 * there. Same actions (and same ManualPaymentService call) as the Payments
 * screen; Super Admin only, like Payments itself.
 */
class PaymentsToConfirmWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Transfers to confirm';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return PaymentResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AdminSnapshot::pendingTransfersQuery()
                    ->with('order:id,order_number,total')
                    ->orderBy('created_at')
                    ->limit(8)
            )
            ->columns([
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order')
                    ->weight('bold')
                    ->color('primary')
                    ->url(fn (Payment $record) => $record->order ? OrderResource::getUrl('view', ['record' => $record->order]) : null),
                Tables\Columns\TextColumn::make('amount')->money('GHS'),
                Tables\Columns\IconColumn::make('has_proof')
                    ->label('Proof')
                    ->state(fn (Payment $record) => filled($record->proof_path))
                    ->boolean()
                    ->trueIcon('heroicon-o-photo')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('created_at')->label('Waiting')->since()->dateTimeTooltip('d M Y, H:i'),
            ])
            ->actions([
                PaymentResource::viewProofAction(),
                PaymentResource::confirmAction(),
                PaymentResource::rejectAction(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('all')
                    ->label('See all payments')
                    ->url(PaymentResource::getUrl('index'))
                    ->color('gray')
                    ->size('sm'),
            ])
            ->paginated(false)
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('No transfers waiting')
            ->emptyStateDescription('When a customer pays by transfer it shows up here for you to confirm.');
    }
}
