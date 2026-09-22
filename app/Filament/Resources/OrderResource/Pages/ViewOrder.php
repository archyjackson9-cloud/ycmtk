<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OrderResource::updateStatusHeaderAction(),
            OrderResource::cancelHeaderAction(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Order '.$this->record->order_number)
                ->columns(3)
                ->schema([
                    TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
                    TextEntry::make('created_at')->label('Placed')->dateTime('d M Y, H:i'),
                    TextEntry::make('total')->money('GHS'),
                ]),

            Section::make('Customer & Delivery')
                ->columns(2)
                ->schema([
                    TextEntry::make('customerName')->label('Customer')->state(fn ($record) => $record->customerName()),
                    TextEntry::make('customerPhone')->label('Phone')->state(fn ($record) => $record->customerPhone()),
                    TextEntry::make('delivery_address_line')->label('Address')->columnSpanFull(),
                    TextEntry::make('delivery_landmark')->label('Landmark')->visible(fn ($record) => filled($record->delivery_landmark)),
                    TextEntry::make('deliveryZone.name')->label('Delivery zone'),
                    TextEntry::make('notes')->label('Customer notes')->visible(fn ($record) => filled($record->notes)),
                ]),

            Section::make('Delivery Location')
                ->description('Where the customer pinned their current location, so a rider can go straight to it.')
                ->visible(fn ($record) => filled($record->delivery_latitude) && filled($record->delivery_longitude))
                ->schema([
                    ViewEntry::make('deliveryLocationMap')
                        ->label('')
                        ->view('filament.infolists.delivery-location-map')
                        ->columnSpanFull(),
                ]),

            Section::make('Items')
                ->schema([
                    RepeatableEntry::make('items')
                        ->schema([
                            TextEntry::make('product_name')->label('Product'),
                            TextEntry::make('quantity'),
                            TextEntry::make('unit_price')->money('GHS'),
                            TextEntry::make('line_total')->money('GHS'),
                        ])
                        ->columns(4),
                ]),

            Section::make('Payments')
                ->schema([
                    RepeatableEntry::make('payments')
                        ->schema([
                            TextEntry::make('reference'),
                            TextEntry::make('channel'),
                            TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
                            TextEntry::make('amount')->money('GHS'),
                            TextEntry::make('paid_at')->dateTime('d M Y, H:i'),
                        ])
                        ->columns(5),
                ])
                ->visible(fn ($record) => $record->payments->isNotEmpty()),

            Section::make('Status History')
                ->schema([
                    RepeatableEntry::make('statusHistories')
                        ->label('')
                        ->schema([
                            TextEntry::make('to_status')->label('Status')->formatStateUsing(fn ($state) => $state->label()),
                            TextEntry::make('note'),
                            TextEntry::make('changedBy.name')->label('Changed by')->placeholder('System'),
                            TextEntry::make('created_at')->dateTime('d M Y, H:i'),
                        ])
                        ->columns(4),
                ]),
        ]);
    }
}
