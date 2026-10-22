<?php

namespace App\Filament\Resources;

use App\Enums\PaymentStatus;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Services\Payments\ManualPaymentService;
use App\Support\AdminSnapshot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Payment ledger (TOR §6.10 "financial settings" access restricted to
 * Super Admin; §11 reconciliation). Read-only for MTN MoMo, which confirms
 * itself via webhook - but a pending pay-by-transfer row needs a human to
 * confirm or reject it here once the money actually lands (see
 * ManualPaymentService), since nothing calls this system automatically.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Payments';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = AdminSnapshot::counts()['pending_transfers'];

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Transfers waiting for you to confirm';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['order:id,order_number,total,user_id', 'confirmedBy:id,name']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->weight('bold')
                    ->color('primary')
                    ->url(fn (Payment $record) => $record->order ? OrderResource::getUrl('view', ['record' => $record->order]) : null),
                Tables\Columns\TextColumn::make('amount')->money('GHS')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'manual' ? 'Bank/MoMo transfer' : 'MTN MoMo')
                    ->color(fn (string $state) => $state === 'manual' ? 'warning' : 'success'),
                Tables\Columns\IconColumn::make('has_proof')
                    ->label('Proof')
                    ->state(fn (Payment $record) => filled($record->proof_path))
                    ->boolean()
                    ->trueIcon('heroicon-o-photo')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (Payment $record) => filled($record->proof_path) ? 'Customer uploaded proof - use "View proof"' : 'No proof uploaded yet'),
                Tables\Columns\TextColumn::make('created_at')->label('Started')->since()->dateTimeTooltip('d M Y, H:i')->sortable(),
                Tables\Columns\TextColumn::make('reference')->searchable()->copyable()->toggleable(),
                Tables\Columns\TextColumn::make('provider_transaction_id')->label('Transaction ref')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('channel')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('confirmedBy.name')->label('Confirmed by')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('paid_at')->label('Paid')->dateTime('d M Y, H:i')->placeholder('-')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(collect(PaymentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Tables\Filters\SelectFilter::make('provider')->label('Method')->options(['manual' => 'Bank/MoMo transfer', 'mtn_momo' => 'MTN MoMo']),
            ])
            ->actions([
                static::viewProofAction(),
                static::confirmAction(),
                static::rejectAction(),
            ])
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateHeading('No payments here')
            ->emptyStateDescription('Payments appear as soon as a customer places an order. Try another tab.')
            ->searchPlaceholder('Search order or reference')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->defaultSort('created_at', 'desc');
    }

    public static function isPendingManual(Payment $record): bool
    {
        return $record->provider === 'manual' && $record->status === PaymentStatus::Pending;
    }

    public static function viewProofAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('viewProof')
            ->label('View proof')
            ->icon('heroicon-o-photo')
            ->color('gray')
            ->visible(fn (Payment $record) => filled($record->proof_path))
            ->modalHeading(fn (Payment $record) => 'Proof of payment - '.($record->order?->order_number ?? $record->reference))
            ->modalContent(fn (Payment $record) => view('filament.payments.proof', [
                'payment' => $record,
                'url' => Storage::disk('public')->url($record->proof_path),
                'isImage' => in_array(strtolower(pathinfo((string) $record->proof_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    /** Form shown when confirming; shared by the table action and the order page. */
    public static function confirmForm(Payment $payment): array
    {
        return [
            Forms\Components\TextInput::make('amount_received')
                ->label('Amount received (GHS)')
                ->numeric()->required()->minValue(0)
                ->default($payment->amount)
                ->helperText('Checked against the order total - record what actually landed.'),
            Forms\Components\Textarea::make('note')->label('Reference / note (optional)'),
        ];
    }

    public static function performConfirm(Payment $payment, array $data): void
    {
        app(ManualPaymentService::class)->confirm($payment, auth()->user(), true, (float) $data['amount_received'], $data['note'] ?? null);
        AdminSnapshot::flush();

        Notification::make()->title('Payment confirmed')->body('The order is now paid and the customer has been notified.')->success()->send();
    }

    public static function performReject(Payment $payment, array $data): void
    {
        app(ManualPaymentService::class)->confirm($payment, auth()->user(), false, null, $data['note']);
        AdminSnapshot::flush();

        Notification::make()->title('Payment rejected')->body('The reserved stock was released.')->danger()->send();
    }

    public static function confirmAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('confirm')
            ->label('Confirm')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Payment $record) => static::isPendingManual($record))
            ->modalHeading(fn (Payment $record) => 'Confirm payment for '.($record->order?->order_number ?? 'this order'))
            ->modalIcon('heroicon-o-check-circle')
            ->modalIconColor('success')
            ->modalDescription('This marks the order as paid, deducts stock and notifies the customer - only do this once you can see the money in the account.')
            ->modalSubmitActionLabel('Yes, money received')
            ->form(fn (Payment $record) => static::confirmForm($record))
            ->action(fn (Payment $record, array $data) => static::performConfirm($record, $data));
    }

    public static function rejectAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Payment $record) => static::isPendingManual($record))
            ->modalHeading(fn (Payment $record) => 'Reject payment for '.($record->order?->order_number ?? 'this order'))
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('danger')
            ->modalDescription('This releases the reserved stock and lets the customer know the payment was not received.')
            ->modalSubmitActionLabel('Reject payment')
            ->form([
                Forms\Components\Textarea::make('note')->label('Reason')->required(),
            ])
            ->action(fn (Payment $record, array $data) => static::performReject($record, $data));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}
