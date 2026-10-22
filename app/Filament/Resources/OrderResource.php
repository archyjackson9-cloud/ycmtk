<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\AdminSnapshot;
use Carbon\Carbon;
use Filament\Actions\Action as HeaderAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
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

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function getNavigationBadge(): ?string
    {
        $count = AdminSnapshot::counts()['needs_action'];

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Paid or on-hold orders waiting for you';
    }

    /** Search the whole shop from the top bar by order number, phone or customer. */
    public static function getGloballySearchableAttributes(): array
    {
        return ['order_number', 'delivery_phone', 'guest_name', 'guest_phone', 'user.name'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('user:id,name,phone');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Order $record */
        return [
            'Customer' => $record->customerName(),
            'Status' => $record->status->label(),
            'Total' => 'GHS '.number_format((float) $record->total, 2),
        ];
    }

    /** Avoid one query per row for the customer name (audit A-05). */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user:id,name,phone');
    }

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
                Tables\Columns\TextColumn::make('order_number')->label('Order')->searchable()->sortable()->weight('bold')->copyable()->copyMessage('Order number copied'),
                Tables\Columns\TextColumn::make('customer')
                    ->state(fn (Order $record) => $record->customerName())
                    ->description(fn (Order $record) => $record->customerPhone())
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                        ->where('guest_name', 'like', "%{$search}%")
                        ->orWhere('delivery_phone', 'like', "%{$search}%")
                        ->orWhere('guest_phone', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")))),
                Tables\Columns\TextColumn::make('status')->badge()->formatStateUsing(fn (OrderStatus $state) => $state->label())->color(fn (OrderStatus $state) => $state->color()),
                Tables\Columns\TextColumn::make('total')->money('GHS')->sortable(),
                Tables\Columns\TextColumn::make('scheduled_delivery_at')
                    ->label('Delivery')
                    ->dateTime('D d M, H:i')
                    ->sortable()
                    ->placeholder('Not scheduled')
                    ->icon(fn (Order $record) => static::isDeliveringToday($record) ? 'heroicon-m-truck' : null)
                    ->color(fn (Order $record) => match (true) {
                        static::isOverdue($record) => 'danger',
                        static::isDeliveringToday($record) => 'success',
                        default => null,
                    })
                    ->weight(fn (Order $record) => static::isDeliveringToday($record) ? 'bold' : null)
                    ->tooltip(fn (Order $record) => match (true) {
                        static::isOverdue($record) => 'Past its scheduled time and not delivered yet',
                        static::isDeliveringToday($record) => 'Due today',
                        default => null,
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('Placed')->since()->dateTimeTooltip('d M Y, H:i')->sortable(),
                Tables\Columns\TextColumn::make('delivery_phone')
                    ->label('Phone')
                    ->url(fn (Order $record) => 'tel:'.$record->delivery_phone)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deliveryZone.name')->label('Zone')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->multiple()->options(collect(OrderStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                Tables\Filters\Filter::make('scheduled_delivery_at')
                    ->label('Scheduled delivery')
                    ->form([
                        Forms\Components\DatePicker::make('delivery_from')->label('Delivery from'),
                        Forms\Components\DatePicker::make('delivery_until')->label('Delivery until'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['delivery_from'] ?? null, fn ($q, $date) => $q->where('scheduled_delivery_at', '>=', Carbon::parse($date)->startOfDay()))
                        ->when($data['delivery_until'] ?? null, fn ($q, $date) => $q->where('scheduled_delivery_at', '<=', Carbon::parse($date)->endOfDay())))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['delivery_from'] ?? null) ? 'Delivery from '.Carbon::parse($data['delivery_from'])->format('d M') : null,
                        ($data['delivery_until'] ?? null) ? 'Delivery until '.Carbon::parse($data['delivery_until'])->format('d M') : null,
                    ])),
                Tables\Filters\Filter::make('created_at')
                    ->label('Date placed')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Placed from'),
                        Forms\Components\DatePicker::make('until')->label('Placed until'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $date) => $q->where('created_at', '>=', Carbon::parse($date)->startOfDay()))
                        ->when($data['until'] ?? null, fn ($q, $date) => $q->where('created_at', '<=', Carbon::parse($date)->endOfDay())))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['from'] ?? null) ? 'Placed from '.Carbon::parse($data['from'])->format('d M') : null,
                        ($data['until'] ?? null) ? 'Placed until '.Carbon::parse($data['until'])->format('d M') : null,
                    ])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Open')->icon('heroicon-o-eye'),
                self::updateStatusAction(),
                Tables\Actions\ActionGroup::make([
                    self::cancelAction(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    static::bulkAdvanceAction('markProcessing', 'Start processing', OrderStatus::Processing, 'heroicon-o-cog-6-tooth'),
                    static::bulkAdvanceAction('markDispatched', 'Mark as dispatched', OrderStatus::Dispatched, 'heroicon-o-truck'),
                ])->label('Move selected orders'),
            ])
            ->emptyStateIcon('heroicon-o-shopping-cart')
            ->emptyStateHeading('No orders here')
            ->emptyStateDescription('New orders appear as soon as customers check out. Try another tab or clear the filters.')
            ->searchPlaceholder('Search order number, name or phone')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->defaultSort('created_at', 'desc');
    }

    /** Open order whose scheduled delivery is today. */
    protected static function isDeliveringToday(Order $record): bool
    {
        return $record->scheduled_delivery_at !== null
            && $record->scheduled_delivery_at->isToday()
            && in_array($record->status, AdminSnapshot::DELIVERY_OPEN_STATUSES, true);
    }

    /** Open order whose scheduled delivery day has passed. */
    protected static function isOverdue(Order $record): bool
    {
        return $record->scheduled_delivery_at !== null
            && $record->scheduled_delivery_at->isPast()
            && ! $record->scheduled_delivery_at->isToday()
            && in_array($record->status, AdminSnapshot::DELIVERY_OPEN_STATUSES, true);
    }

    /**
     * Bulk "move to next step" for the common happy-path moves. Every order
     * still goes through OrderService::updateStatus(), so the status machine,
     * audit trail, SMS and stock effects are exactly as for a single update;
     * orders that cannot make that move are skipped and reported.
     */
    protected static function bulkAdvanceAction(string $name, string $label, OrderStatus $target, string $icon): Tables\Actions\BulkAction
    {
        return Tables\Actions\BulkAction::make($name)
            ->label($label)
            ->icon($icon)
            ->visible(fn () => auth()->user()?->hasAnyRole([config('cymarket.roles.super_admin'), config('cymarket.roles.inventory_officer')]) ?? false)
            ->requiresConfirmation()
            ->modalHeading($label)
            ->modalDescription('Only orders that can move to "'.$target->label().'" from where they are will change. The rest are skipped and left as they are.')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) use ($target) {
                $moved = 0;
                $skipped = 0;

                foreach ($records as $record) {
                    if (! $record->status->canTransitionTo($target)) {
                        $skipped++;

                        continue;
                    }

                    try {
                        app(OrderService::class)->updateStatus($record, $target, auth()->user(), 'Bulk update');
                        $moved++;
                    } catch (RuntimeException) {
                        $skipped++;
                    }
                }

                AdminSnapshot::flush();

                Notification::make()
                    ->title($moved.' '.Str::plural('order', $moved).' moved to '.$target->label())
                    ->body($skipped > 0 ? $skipped.' skipped (not in a state that can move there).' : null)
                    ->color($moved > 0 ? 'success' : 'warning')
                    ->icon($moved > 0 ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                    ->send();
            });
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
                    AdminSnapshot::flush();
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
                    AdminSnapshot::flush();
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
