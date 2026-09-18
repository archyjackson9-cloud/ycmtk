<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Category;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Product Catalogue management (TOR §6.1). Full CRUD is Super-Admin only -
 * the Inventory Officer gets read access here plus stock-only control via
 * StockMovementResource (TOR §6.10 "No access to ... pricing changes").
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole([
            config('cymarket.roles.super_admin'),
            config('cymarket.roles.inventory_officer'),
        ]) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Product Details')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state).'-'.Str::lower(Str::random(5)))),
                    Forms\Components\TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),
                    Forms\Components\Select::make('category_id')
                        ->label('Category')
                        ->options(fn () => Category::active()->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Forms\Components\TextInput::make('sku')->required()->maxLength(100)->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('unit_of_measurement')->required()->maxLength(50)->default('unit')
                        ->helperText('e.g. kg, bag, crate, bunch, tuber'),
                    Forms\Components\TextInput::make('weight')->maxLength(50)->helperText('Free text, e.g. "5kg"'),
                    Forms\Components\Textarea::make('short_description')->maxLength(500)->columnSpanFull(),
                    Forms\Components\RichEditor::make('description')->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Pricing & Stock')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('selling_price')->numeric()->prefix('GHS')->required(),
                    Forms\Components\TextInput::make('wholesale_price')->numeric()->prefix('GHS'),
                    Forms\Components\TextInput::make('min_order_quantity')->numeric()->integer()->minValue(1)->default(1)->required(),
                    Forms\Components\TextInput::make('available_quantity')->numeric()->integer()->minValue(0)->default(0)->required()
                        ->helperText('Use Stock Movements to adjust this after go-live so every change is audited.'),
                    Forms\Components\TextInput::make('low_stock_threshold')->numeric()->integer()->minValue(0)
                        ->helperText('Defaults to '.config('cymarket.default_low_stock_threshold').' if left blank.'),
                    Forms\Components\Placeholder::make('reserved_quantity')
                        ->label('Currently Reserved')
                        ->content(fn (?Product $record) => $record?->reserved_quantity ?? 0),
                ]),

            Forms\Components\Section::make('Traceability & Seasonality')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('production_location')->maxLength(255),
                    Forms\Components\TextInput::make('packaging_type')->maxLength(255),
                    Forms\Components\Toggle::make('is_seasonal')->live(),
                    Forms\Components\Select::make('season_start_month')
                        ->options(self::monthOptions())
                        ->visible(fn (Forms\Get $get) => $get('is_seasonal')),
                    Forms\Components\Select::make('season_end_month')
                        ->options(self::monthOptions())
                        ->visible(fn (Forms\Get $get) => $get('is_seasonal')),
                ]),

            Forms\Components\Section::make('Images')
                ->schema([
                    Forms\Components\Repeater::make('images')
                        ->relationship()
                        ->schema([
                            Forms\Components\FileUpload::make('path')
                                ->image()
                                ->directory('products')
                                ->required(),
                            Forms\Components\Toggle::make('is_primary'),
                        ])
                        ->columns(2)
                        ->collapsible()
                        ->defaultItems(1)
                        ->addActionLabel('Add image'),
                ]),

            Forms\Components\Section::make('Visibility')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\Toggle::make('is_featured'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('images.0.path')->label('')->square(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category.name')->sortable(),
                Tables\Columns\TextColumn::make('sku')->label('SKU')->toggleable(),
                Tables\Columns\TextColumn::make('selling_price')->money('GHS')->sortable(),
                Tables\Columns\TextColumn::make('sellable_quantity')->label('In Stock')->badge()
                    ->color(fn (Product $record) => $record->is_out_of_stock ? 'danger' : ($record->is_low_stock ? 'warning' : 'success')),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\IconColumn::make('is_featured')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('sold_count')->label('Sold')->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')->label('Category')->options(fn () => Category::pluck('name', 'id')),
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\Filter::make('low_stock')
                    ->query(fn (Builder $query) => $query->whereRaw('(available_quantity - reserved_quantity) <= COALESCE(low_stock_threshold, ?)', [config('cymarket.default_low_stock_threshold')])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    protected static function monthOptions(): array
    {
        return collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => date('F', mktime(0, 0, 0, $m, 1))])->toArray();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
