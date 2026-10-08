<?php

namespace App\Filament\Resources;

use App\Enums\PaymentStatus;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Services\Payments\ManualPaymentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
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

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
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

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.order_number')->label('Order')->searchable(),
                Tables\Columns\TextColumn::make('reference')->searchable(),
                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'manual' ? 'Bank/MoMo Transfer' : 'MTN MoMo')
                    ->color(fn (string $state) => $state === 'manual' ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('provider_transaction_id')->label('Transaction Ref')->toggleable(),
                Tables\Columns\TextColumn::make('channel')->toggleable(),
                Tables\Columns\TextColumn::make('amount')->money('GHS')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
                Tables\Columns\TextColumn::make('proof_path')
                    ->label('Proof')
                    ->formatStateUsing(fn (?string $state) => $state ? 'View' : '—')
                    ->url(fn (?string $state) => $state ? Storage::disk('public')->url($state) : null, shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('confirmedBy.name')->label('Confirmed By')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('paid_at')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(collect(PaymentStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Tables\Filters\SelectFilter::make('provider')->options(['manual' => 'Bank/MoMo Transfer', 'mtn_momo' => 'MTN MoMo']),
            ])
            ->actions([
                static::confirmAction(),
                static::rejectAction(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected static function isPendingManual(Payment $record): bool
    {
        return $record->provider === 'manual' && $record->status === PaymentStatus::Pending;
    }

    public static function confirmAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('confirm')
            ->label('Confirm Payment')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Payment $record) => static::isPendingManual($record))
            ->requiresConfirmation()
            ->modalDescription('This marks the order as paid, deducts stock and notifies the customer - only do this once you can see the money in the account.')
            ->form([
                Forms\Components\TextInput::make('amount_received')
                    ->label('Amount Received (GHS)')
                    ->numeric()->required()->minValue(0)
                    ->default(fn (Payment $record) => $record->amount)
                    ->helperText('Checked against the order total - record what actually landed.'),
                Forms\Components\Textarea::make('note')->label('Reference / note (optional)'),
            ])
            ->action(function (Payment $record, array $data) {
                app(ManualPaymentService::class)->confirm($record, auth()->user(), true, (float) $data['amount_received'], $data['note'] ?? null);

                Notification::make()->title('Payment confirmed')->success()->send();
            });
    }

    public static function rejectAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Payment $record) => static::isPendingManual($record))
            ->requiresConfirmation()
            ->modalDescription('This releases the reserved stock and lets the customer know the payment was not received.')
            ->form([
                Forms\Components\Textarea::make('note')->label('Reason')->required(),
            ])
            ->action(function (Payment $record, array $data) {
                app(ManualPaymentService::class)->confirm($record, auth()->user(), false, null, $data['note']);

                Notification::make()->title('Payment rejected')->danger()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }
}
