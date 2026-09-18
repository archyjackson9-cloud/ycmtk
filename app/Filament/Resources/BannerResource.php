<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Homepage banner / hero content management (TOR §6.8 CMS-lite, Phase 2).
 * Super Admin and Marketing Admin only.
 */
class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Marketing';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole([
            config('cymarket.roles.super_admin'),
            config('cymarket.roles.marketing_admin'),
        ]) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Banner')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                    Forms\Components\FileUpload::make('image')
                        ->image()
                        ->directory('banners')
                        ->helperText('Used as the hero image, or as the video poster frame if a video is also set.'),
                    Forms\Components\FileUpload::make('video')
                        ->directory('banners')
                        ->acceptedFileTypes(['video/mp4', 'video/webm'])
                        ->helperText('Optional. Autoplays muted and looped in the hero - keep it short (a few seconds) and under a few MB.'),
                    Forms\Components\TextInput::make('link_url')->label('Link URL')->url()->maxLength(255),
                    Forms\Components\Select::make('position')
                        ->options([
                            'homepage_hero' => 'Homepage Hero',
                            'category_spotlight' => 'Category Spotlight',
                        ])
                        ->required()
                        ->default('homepage_hero'),
                    Forms\Components\TextInput::make('sort_order')->numeric()->integer()->default(0)->required()
                        ->helperText('Lower numbers show first when multiple banners are live.'),
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\DateTimePicker::make('starts_at')->helperText('Leave blank to go live immediately.'),
                    Forms\Components\DateTimePicker::make('expires_at')->helperText('Leave blank to never expire.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')->label('')->square(),
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('position')->badge(),
                Tables\Columns\IconColumn::make('video')->label('Video')->boolean()
                    ->getStateUsing(fn (Banner $record) => $record->hasVideo()),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('starts_at')->dateTime('d M Y, H:i')->placeholder('Immediately')->toggleable(),
                Tables\Columns\TextColumn::make('expires_at')->dateTime('d M Y, H:i')->placeholder('Never')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('position')->options([
                    'homepage_hero' => 'Homepage Hero',
                    'category_spotlight' => 'Category Spotlight',
                ]),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
