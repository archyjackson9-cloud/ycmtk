<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Category;
use App\Models\Product;
use App\Support\AdminSnapshot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
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

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        $counts = AdminSnapshot::counts();
        $n = $counts['low_stock'] + $counts['out_of_stock'];

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return AdminSnapshot::counts()['out_of_stock'] > 0 ? 'danger' : 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Products low or out of stock';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ['SKU' => $record->sku, 'Price' => 'GHS '.number_format((float) $record->selling_price, 2)];
    }

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
            Forms\Components\Tabs::make('Product')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Basics')
                        ->icon('heroicon-o-information-circle')
                        ->columns(2)
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                // Only build the web address while creating: changing it later
                                // would break links customers have already shared.
                                ->afterStateUpdated(function (Forms\Set $set, ?string $state, string $operation) {
                                    if ($operation === 'create') {
                                        $set('slug', Str::slug($state).'-'.Str::lower(Str::random(5)));
                                    }
                                }),
                            Forms\Components\TextInput::make('slug')
                                ->label('Web address (slug)')
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true)
                                ->disabled(fn (string $operation) => $operation === 'edit')
                                ->dehydrated()
                                ->helperText(fn (string $operation) => $operation === 'edit'
                                    ? 'Locked so links you have already shared keep working.'
                                    : 'Filled in for you from the name.'),
                            Forms\Components\Select::make('category_id')
                                ->label('Category')
                                ->options(fn () => Category::active()->pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                            Forms\Components\TextInput::make('sku')->label('SKU (stock code)')->required()->maxLength(100)->unique(ignoreRecord: true),
                            Forms\Components\TextInput::make('unit_of_measurement')->label('Sold by (unit)')->required()->maxLength(50)->default('unit')
                                ->helperText('e.g. kg, bag, crate, bunch, tuber'),
                            Forms\Components\TextInput::make('weight')->maxLength(50)->helperText('Free text, e.g. "5kg"'),
                            Forms\Components\Textarea::make('short_description')->maxLength(500)->rows(2)->columnSpanFull()
                                ->helperText('Shown on product cards - one or two lines.'),
                            Forms\Components\RichEditor::make('description')->columnSpanFull(),
                        ]),

                    Forms\Components\Tabs\Tab::make('Price & stock')
                        ->icon('heroicon-o-banknotes')
                        ->columns(['default' => 1, 'md' => 3])
                        ->schema([
                            Forms\Components\TextInput::make('selling_price')->numeric()->prefix('GHS')->required()->minValue(0)->step(0.01)->inputMode('decimal'),
                            Forms\Components\TextInput::make('wholesale_price')->numeric()->prefix('GHS')->minValue(0)->step(0.01)->inputMode('decimal'),
                            Forms\Components\TextInput::make('min_order_quantity')->numeric()->integer()->minValue(1)->default(1)->required(),
                            Forms\Components\TextInput::make('available_quantity')->label('Quantity in stock')->numeric()->integer()->minValue(0)->default(0)->required()
                                // After the product exists, every change goes through
                                // Stock movements so there is an audit trail.
                                ->disabled(fn (string $operation) => $operation === 'edit')
                                ->dehydrated(fn (string $operation) => $operation === 'create')
                                ->helperText(fn (string $operation) => $operation === 'edit'
                                    ? 'To add, remove or correct stock, record a stock movement - that keeps a history of who changed what.'
                                    : 'Opening stock. After this, use Stock movements so every change is recorded.'),
                            Forms\Components\TextInput::make('low_stock_threshold')->numeric()->integer()->minValue(0)
                                ->helperText('Warn when this many or fewer are left. Defaults to '.config('cymarket.default_low_stock_threshold').' if left blank.'),
                            Forms\Components\Placeholder::make('reserved_quantity')
                                ->label('Held for unpaid orders')
                                ->content(fn (?Product $record) => $record?->reserved_quantity ?? 0),
                        ]),

                    Forms\Components\Tabs\Tab::make('Photos')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            Forms\Components\Repeater::make('images')
                                ->relationship()
                                ->label('Product photos')
                                ->schema([
                                    Forms\Components\FileUpload::make('path')
                                        ->label('Photo')
                                        ->image()
                                        ->directory('products')
                                        ->maxSize(4096)
                                        // Resized in the browser before upload: saves staff data
                                        // and keeps shop pages fast for customers on mobile data.
                                        ->imageResizeMode('contain')
                                        ->imageResizeTargetWidth('1200')
                                        ->imageResizeTargetHeight('1200')
                                        ->imagePreviewHeight('160')
                                        ->required(),
                                    Forms\Components\Toggle::make('is_primary')->label('Main photo')
                                        ->helperText('The main photo is the one shoppers see first.'),
                                ])
                                ->columns(2)
                                ->grid(['default' => 1, 'lg' => 2])
                                ->collapsible()
                                ->defaultItems(1)
                                ->addActionLabel('Add photo'),
                        ]),

                    Forms\Components\Tabs\Tab::make('Origin & season')
                        ->icon('heroicon-o-sun')
                        ->columns(2)
                        ->schema([
                            Forms\Components\TextInput::make('production_location')->maxLength(255),
                            Forms\Components\TextInput::make('packaging_type')->maxLength(255),
                            Forms\Components\Toggle::make('is_seasonal')->live()->columnSpanFull(),
                            Forms\Components\Select::make('season_start_month')
                                ->options(self::monthOptions())
                                ->visible(fn (Forms\Get $get) => $get('is_seasonal')),
                            Forms\Components\Select::make('season_end_month')
                                ->options(self::monthOptions())
                                ->helperText('A season can run over the new year, e.g. November to February.')
                                ->visible(fn (Forms\Get $get) => $get('is_seasonal')),
                        ]),

                    Forms\Components\Tabs\Tab::make('Shop visibility')
                        ->icon('heroicon-o-eye')
                        ->columns(2)
                        ->schema([
                            Forms\Components\Toggle::make('is_active')->label('Show in the shop')->default(true)
                                ->helperText('Turn off to hide the product without deleting it.'),
                            Forms\Components\Toggle::make('is_featured')->label('Feature on the home page'),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category:id,name', 'images']))
            ->columns([
                Tables\Columns\ImageColumn::make('images.0.path')->label('')->square()->size(44)->extraImgAttributes(['loading' => 'lazy', 'alt' => '']),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('bold')
                    ->description(fn (Product $record) => $record->sku),
                Tables\Columns\TextColumn::make('category.name')->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('selling_price')->label('Price')->money('GHS')->sortable(),
                Tables\Columns\TextColumn::make('sellable_quantity')->label('In stock')->badge()
                    ->formatStateUsing(fn (Product $record, $state) => $state.' '.$record->unit_of_measurement)
                    ->color(fn (Product $record) => $record->is_out_of_stock ? 'danger' : ($record->is_low_stock ? 'warning' : 'success')),
                Tables\Columns\ToggleColumn::make('is_active')->label('In shop')
                    ->disabled(fn () => ! static::canCreate())
                    ->tooltip('Show or hide this product in the shop'),
                Tables\Columns\IconColumn::make('is_featured')->label('Featured')->boolean()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sku')->label('SKU')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sold_count')->label('Sold')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')->label('Category')->options(fn () => Category::pluck('name', 'id')),
                Tables\Filters\TernaryFilter::make('is_active')->label('In shop'),
                Tables\Filters\Filter::make('low_stock')
                    ->label('Low or out of stock')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereRaw('(available_quantity - reserved_quantity) <= COALESCE(low_stock_threshold, ?)', [config('cymarket.default_low_stock_threshold')])),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn (Product $record) => static::canEdit($record)),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('restock')
                        ->label('Record stock')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->url(fn (Product $record) => StockMovementResource::getUrl('create', ['product_id' => $record->id]))
                        ->visible(fn () => StockMovementResource::canCreate()),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('showInShop')
                        ->label('Show in shop')
                        ->icon('heroicon-o-eye')
                        ->requiresConfirmation()
                        ->visible(fn () => static::canCreate())
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true])),
                    Tables\Actions\BulkAction::make('hideFromShop')
                        ->label('Hide from shop')
                        ->icon('heroicon-o-eye-slash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Customers will no longer see these products. Nothing is deleted.')
                        ->visible(fn () => static::canCreate())
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false])),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->emptyStateHeading('No products found')
            ->emptyStateDescription('Add your first product, or clear the filters.')
            ->searchPlaceholder('Search name or SKU')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
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
