<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Notifications\EmailNotificationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\TextEntry\TextEntrySize;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * One order, readable at a glance: who/what/how much and where it is in its
 * journey up top, then customer & delivery, money, items and the audit
 * trail. Primary action is always the next step; payment confirmation sits
 * next to it for the Super Admin so staff never have to hunt for the
 * matching row in Payments.
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Order '.$this->record->order_number;
    }

    /** Load everything the page reads in one go (no per-row lazy loads). */
    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(['user:id,name,phone,email', 'deliveryZone', 'items', 'payments.confirmedBy', 'statusHistories.changedBy']);
    }

    protected function pendingTransfer(): ?Payment
    {
        return $this->record->payments->first(fn (Payment $payment) => PaymentResource::isPendingManual($payment));
    }

    protected function getHeaderActions(): array
    {
        $canConfirm = fn () => PaymentResource::canViewAny() && $this->pendingTransfer() !== null;

        return [
            OrderResource::updateStatusHeaderAction()->button(),
            Action::make('confirmPayment')
                ->label('Confirm payment')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible($canConfirm)
                ->modalHeading(fn () => 'Confirm payment for '.$this->record->order_number)
                ->modalIcon('heroicon-o-check-circle')
                ->modalIconColor('success')
                ->modalDescription('This marks the order as paid, deducts stock and notifies the customer - only do this once you can see the money in the account.')
                ->modalSubmitActionLabel('Yes, money received')
                ->form(fn () => PaymentResource::confirmForm($this->pendingTransfer()))
                ->action(function (array $data) {
                    PaymentResource::performConfirm($this->pendingTransfer(), $data);
                    $this->refreshRecord();
                }),
            Action::make('rejectPayment')
                ->label('Reject payment')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->outlined()
                ->visible($canConfirm)
                ->modalHeading(fn () => 'Reject payment for '.$this->record->order_number)
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->modalIconColor('danger')
                ->modalDescription('This releases the reserved stock and lets the customer know the payment was not received.')
                ->modalSubmitActionLabel('Reject payment')
                ->form([Forms\Components\Textarea::make('note')->label('Reason')->required()])
                ->action(function (array $data) {
                    PaymentResource::performReject($this->pendingTransfer(), $data);
                    $this->refreshRecord();
                }),
            ActionGroup::make([
                Action::make('resendEmail')
                    ->label('Resend status email')
                    ->icon('heroicon-o-envelope')
                    ->requiresConfirmation()
                    ->visible(fn () => auth()->user()?->hasAnyRole([config('cymarket.roles.super_admin'), config('cymarket.roles.inventory_officer')])
                        && filled(app(EmailNotificationService::class)->recipientFor($this->record)))
                    ->action(function () {
                        app(EmailNotificationService::class)->resend($this->record);
                        Notification::make()->title('Email queued')->success()->send();
                    }),
                OrderResource::cancelHeaderAction(),
            ])->label('More')->icon('heroicon-m-ellipsis-vertical')->button()->color('gray'),
        ];
    }

    /** After an action the status/timeline/payments must reflect the change. */
    protected function refreshRecord(): void
    {
        $this->record = $this->resolveRecord($this->record->getKey());
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make()
                ->schema([
                    Grid::make(['default' => 2, 'md' => 4])->schema([
                        TextEntry::make('order_number')->label('Order')->weight(FontWeight::Bold)->size(TextEntrySize::Large)->copyable()->copyMessage('Order number copied'),
                        TextEntry::make('status')->badge()->size(TextEntrySize::Large)->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
                        TextEntry::make('total')->money('GHS')->weight(FontWeight::Bold)->size(TextEntrySize::Large),
                        TextEntry::make('created_at')->label('Placed')->since()->dateTimeTooltip('d M Y, H:i'),
                    ]),
                    ViewEntry::make('timeline')->label('')->view('filament.infolists.order-timeline')->columnSpanFull(),
                ]),

            Grid::make(['default' => 1, 'md' => 2])->schema([
                Section::make('Customer & delivery')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('customerName')->label('Customer')->state(fn (Order $record) => $record->customerName()),
                        TextEntry::make('customerPhone')->label('Phone')->state(fn (Order $record) => $record->customerPhone())
                            ->icon('heroicon-m-phone')
                            ->url(fn (Order $record) => 'tel:'.$record->customerPhone())
                            ->copyable(),
                        TextEntry::make('scheduled_delivery_at')->label('Scheduled delivery')->dateTime('l d M Y, H:i')->placeholder('Not scheduled')->icon('heroicon-m-truck')->columnSpanFull(),
                        TextEntry::make('delivery_address_line')->label('Address')->columnSpanFull(),
                        TextEntry::make('delivery_landmark')->label('Landmark')->visible(fn ($record) => filled($record->delivery_landmark)),
                        TextEntry::make('deliveryZone.name')->label('Delivery zone')->placeholder('-'),
                        TextEntry::make('notes')->label('Customer notes')->visible(fn ($record) => filled($record->notes))->columnSpanFull(),
                    ]),

                Section::make('Money')
                    ->icon('heroicon-o-banknotes')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('subtotal')->money('GHS'),
                        TextEntry::make('delivery_fee')->label('Delivery fee')->money('GHS'),
                        TextEntry::make('discount_total')->label('Discount')->money('GHS')
                            ->helperText(fn ($record) => filled($record->coupon_code) ? 'Coupon '.$record->coupon_code : null),
                        TextEntry::make('total')->money('GHS')->weight(FontWeight::Bold),
                        RepeatableEntry::make('payments')
                            ->label('Payments')
                            ->columnSpanFull()
                            ->contained(false)
                            ->schema([
                                Grid::make(['default' => 2, 'md' => 4])->schema([
                                    TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
                                    TextEntry::make('amount')->money('GHS'),
                                    TextEntry::make('provider')->label('Method')->formatStateUsing(fn (string $state) => $state === 'manual' ? 'Bank/MoMo transfer' : 'MTN MoMo'),
                                    TextEntry::make('paid_at')->label('Paid')->dateTime('d M Y, H:i')->placeholder('Not yet'),
                                    TextEntry::make('reference')->copyable()->columnSpan(['default' => 2, 'md' => 4]),
                                    ImageEntry::make('proof_path')
                                        ->label('Proof of payment')
                                        ->disk('public')
                                        ->height(160)
                                        ->extraImgAttributes(['loading' => 'lazy', 'alt' => 'Payment proof uploaded by the customer'])
                                        ->visible(fn (Payment $record) => static::proofIsImage($record))
                                        ->columnSpan(['default' => 2, 'md' => 4]),
                                    TextEntry::make('proof_link')
                                        ->label('Proof of payment')
                                        ->state('Open uploaded file')
                                        ->url(fn (Payment $record) => Storage::disk('public')->url($record->proof_path), shouldOpenInNewTab: true)
                                        ->visible(fn (Payment $record) => filled($record->proof_path) && ! static::proofIsImage($record))
                                        ->columnSpan(['default' => 2, 'md' => 4]),
                                ]),
                            ])
                            ->visible(fn ($record) => $record->payments->isNotEmpty()),
                    ]),
            ]),

            Section::make('Items')
                ->icon('heroicon-o-shopping-bag')
                ->schema([
                    RepeatableEntry::make('items')
                        ->label('')
                        ->contained(false)
                        ->schema([
                            TextEntry::make('product_name')->label('Product')->weight(FontWeight::SemiBold),
                            TextEntry::make('quantity')->label('Qty'),
                            TextEntry::make('unit_price')->label('Unit price')->money('GHS'),
                            TextEntry::make('line_total')->label('Line total')->money('GHS'),
                        ])
                        ->columns(['default' => 2, 'md' => 4]),
                ]),

            Section::make('Delivery location')
                ->icon('heroicon-o-map-pin')
                ->description('Where the customer pinned their current location, so a rider can go straight to it.')
                ->collapsible()
                ->visible(fn ($record) => filled($record->delivery_latitude) && filled($record->delivery_longitude))
                ->schema([
                    ViewEntry::make('deliveryLocationMap')
                        ->label('')
                        ->view('filament.infolists.delivery-location-map')
                        ->columnSpanFull(),
                ]),

            Section::make('History')
                ->icon('heroicon-o-clock')
                ->description('Every status change, who made it and why.')
                ->collapsible()
                ->collapsed()
                ->schema([
                    RepeatableEntry::make('statusHistories')
                        ->label('')
                        ->contained(false)
                        ->schema([
                            TextEntry::make('to_status')->label('Status')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
                            TextEntry::make('note')->placeholder('No note'),
                            TextEntry::make('changedBy.name')->label('Changed by')->placeholder('System'),
                            TextEntry::make('created_at')->label('When')->dateTime('d M Y, H:i'),
                        ])
                        ->columns(['default' => 2, 'md' => 4]),
                ]),
        ]);
    }

    protected static function proofIsImage(Payment $payment): bool
    {
        return filled($payment->proof_path)
            && in_array(strtolower(pathinfo((string) $payment->proof_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }
}
