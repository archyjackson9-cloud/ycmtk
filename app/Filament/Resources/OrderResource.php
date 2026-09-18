<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Actions\Action as HeaderAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Table;
use RuntimeException;

/**
 * Order Management & Lifecycle console (TOR §6.4, §6.10). Every status
 * change goes through OrderService::updateStatus() / ::cancel() so the
 * audit trail, SMS triggers and stock effects stay consistent with the
 * customer-facing flow - orders are never edited as plain fields.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 0;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole([
            config('cymarket.roles.super_admin'),
            config('cymarket.roles.inventory_officer'),
            config('cymarket.roles.support_agent'),
        ]) ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // Orders are only created through checkout.
    }

    public static function canEdit($record): bool
    {
        return false; // Changes go through the controlled status actions below.
    }

    public static function canDelete($record): bool
    {
        return false; // Financial records are never deleted.
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('customer')->state(fn (Order $record) => $record->customerName())->searchable(query: fn ($query, $search) => $query
                    ->where('guest_name', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn (OrderStatus $state) => $state->label())->color(fn (OrderStatus $state) => $state->color()),
                Tables\Columns\TextColumn::make('total')->money('GHS')->sortable(),
                Tables\Columns\TextColumn::make('delivery_phone')->label('Phone')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('Placed')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(collect(OrderStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                self::updateStatusAction(),
                self::cancelAction(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Advance an order to the next stage (TOR §6.4 status machine),
     * offering only the statuses OrderStatus::allowedNextStatuses() permits
     * from its current state.
     */
    public static function updateStatusAction(): TableAction
    {
        return static::configureUpdateStatusAction(TableAction::make('updateStatus'));
    }

    public static function updateStatusHeaderAction(): HeaderAction
    {
        return static::configureUpdateStatusAction(HeaderAction::make('updateStatus'));
    }

    /**
     * Shared config for the "advance status" action (TOR §6.4 status
     * machine). Used as both a table row action (OrderResource::table())
     * and a page header action (ViewOrder) - those need different Filament
     * action classes (`Tables\Actions\Action` vs `Actions\Action`), but
     * must share identical visibility/form/handler logic.
     *
     * @template TAction of TableAction|HeaderAction
     *
     * @param  TAction  $action
     * @return TAction
     */
    protected static function configureUpdateStatusAction(TableAction|HeaderAction $action): TableAction|HeaderAction
    {
        return $action
            ->label('Update Status')
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (Order $record) => auth()->user()?->hasAnyRole([config('cymarket.roles.super_admin'), config('cymarket.roles.inventory_officer')])
                && ! empty($record->status->allowedNextStatuses()))
            ->form(fn (Order $record) => [
                Forms\Components\Select::make('status')
                    ->label('New status')
                    ->options(collect($record->status->allowedNextStatuses())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                    ->required(),
                Forms\Components\Textarea::make('note')->label('Note (optional)'),
            ])
            ->action(function (Order $record, array $data) {
                try {
                    app(OrderService::class)->updateStatus($record, OrderStatus::from($data['status']), auth()->user(), $data['note'] ?? null);
                    Notification::make()->title('Order status updated')->success()->send();
                } catch (RuntimeException $e) {
                    Notification::make()->title('Could not update status')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function cancelAction(): TableAction
    {
        return static::configureCancelAction(TableAction::make('cancel'));
    }

    public static function cancelHeaderAction(): HeaderAction
    {
        return static::configureCancelAction(HeaderAction::make('cancel'));
    }

    /**
     * Shared config for the "cancel order" action - see
     * configureUpdateStatusAction() for why this is split by class.
     *
     * @template TAction of TableAction|HeaderAction
     *
     * @param  TAction  $action
     * @return TAction
     */
    protected static function configureCancelAction(TableAction|HeaderAction $action): TableAction|HeaderAction
    {
        return $action
            ->label('Cancel')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Order $record) => auth()->user()?->hasAnyRole([config('cymarket.roles.super_admin'), config('cymarket.roles.inventory_officer')])
                && $record->status->canTransitionTo(OrderStatus::Cancelled))
            ->form([
                Forms\Components\Textarea::make('reason')->required()->label('Cancellation reason'),
            ])
            ->action(function (Order $record, array $data) {
                try {
                    app(OrderService::class)->cancel($record, $data['reason'], auth()->user());
                    Notification::make()->title('Order cancelled')->success()->send();
                } catch (RuntimeException $e) {
                    Notification::make()->title('Could not cancel order')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
