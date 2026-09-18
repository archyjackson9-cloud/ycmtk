<?php

namespace App\Filament\Resources;

use App\Enums\StockMovementType;
use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\Product;
use App\Models\StockMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Inventory audit trail + manual stock-in / write-off entry point (TOR
 * §6.10 "Inventory Officer - manage stock levels"; §6.11 "Inventory -
 * ... spoilage/write-offs, stock movement history"). Rows are never edited
 * or deleted - corrections are made with a new, explained movement so the
 * trail stays honest (Auditability NFR).
 */
class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Catalogue';

    protected static ?string $navigationLabel = 'Stock Movements';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole([
            config('cymarket.roles.super_admin'),
            config('cymarket.roles.inventory_officer'),
        ]) ?? false;
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
        return $form->schema([
            Forms\Components\Select::make('product_id')
                ->label('Product')
                ->options(fn () => Product::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('delta')
                ->label('Quantity change')
                ->numeric()
                ->integer()
                ->required()
                ->helperText('Positive to add stock (new harvest/delivery), negative to remove it (spoilage, write-off, correction).'),
            Forms\Components\Textarea::make('note')
                ->label('Reason')
                ->required()
                ->helperText('e.g. "New harvest delivered", "Spoiled produce written off", "Recount correction".'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Date')->dateTime('d M Y, H:i')->sortable(),
                Tables\Columns\TextColumn::make('product.name')->label('Product')->searchable(),
                Tables\Columns\TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => $state->label()),
                Tables\Columns\TextColumn::make('quantity')->sortable(),
                Tables\Columns\TextColumn::make('note')->limit(60),
                Tables\Columns\TextColumn::make('user.name')->label('By')->placeholder('System'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_id')->label('Product')->options(fn () => Product::pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('type')->options(collect(StockMovementType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
            'create' => Pages\CreateStockMovement::route('/create'),
        ];
    }
}
