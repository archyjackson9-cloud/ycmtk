<?php

namespace App\Filament\Resources;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Filament\Resources\NotificationLogResource\Pages;
use App\Models\NotificationLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Read-only audit trail of every SMS/notification attempt (TOR §7 SMS
 * notifications, §11 "Failed SMS delivery"). Super Admin sees everything;
 * Inventory Officer can see it too since low-stock/order alerts land here.
 */
class NotificationLogResource extends Resource
{
    protected static ?string $model = NotificationLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Notification Logs';

    protected static ?string $modelLabel = 'Notification Log';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return ($user?->isSuperAdmin() ?? false) || ($user?->isInventoryOfficer() ?? false);
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Sent At')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('channel')
                    ->badge()
                    ->formatStateUsing(fn (NotificationChannel $state) => $state->label()),
                Tables\Columns\TextColumn::make('type')->label('Event')->searchable(),
                Tables\Columns\TextColumn::make('recipient')->searchable(),
                Tables\Columns\TextColumn::make('message')->limit(50)->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (NotificationStatus $state) => $state->color())
                    ->formatStateUsing(fn (NotificationStatus $state) => $state->label()),
                Tables\Columns\TextColumn::make('attempts')->label('Attempts'),
                Tables\Columns\TextColumn::make('notifiable_type')
                    ->label('Related To')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->options(['sms' => 'SMS', 'email' => 'Email', 'push' => 'Push']),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'sent' => 'Sent',
                        'delivered' => 'Delivered',
                        'failed' => 'Failed',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificationLogs::route('/'),
        ];
    }
}
