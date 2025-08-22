<?php

namespace App\Admin\Resources;

use App\Admin\Resources\CouponResource\Pages\CreateCoupon;
use App\Admin\Resources\CouponResource\Pages\EditCoupon;
use App\Admin\Resources\CouponResource\Pages\ListCoupons;
use App\Admin\Resources\CouponResource\RelationManagers\ServicesRelationManager;
use App\Models\Coupon;
use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Support\RawJs;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'ri-coupon-line';

    protected static ?string $activeNavigationIcon = 'ri-coupon-fill';

    // Use method override to avoid property type conflicts
    public static function getNavigationGroup(): ?string
    {
        return 'Configuration';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->maxLength(255)
                    ->unique(static::getModel(), 'code', ignoreRecord: true)
                    ->placeholder('Enter the code of the coupon'),

                TextInput::make('value')
                    ->label('Value')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(fn (Get $get) => $get('type') === 'percentage' ? 100 : null)
                    ->mask(RawJs::make(
                        <<<'JS'
                            $money($input, '.', '', 2)
                        JS
                    ))
                    ->hidden(fn (Get $get) => $get('type') === 'free_setup')
                    ->suffix(fn (Get $get) => $get('type') === 'percentage' ? '%' : config('settings.default_currency'))
                    ->placeholder('Enter the value of the coupon'),

                Select::make('type')
                    ->label('Type')
                    ->required()
                    ->default('percentage')
                    ->live()
                    ->options([
                        'percentage' => 'Percentage',
                        'fixed' => 'Fixed amount',
                        'free_setup' => 'Free setup',
                    ])
                    ->placeholder('Select the type of the coupon'),

                TextInput::make('recurring')
                    ->label('Recurring')
                    ->numeric()
                    ->nullable()
                    ->minValue(0)
                    ->hidden(fn (Get $get) => $get('type') === 'free_setup')
                    ->placeholder('How many billing cycles the discount will be applied')
                    ->helperText('Enter 0 to apply it to all billing cycles, 1 to apply it only to the first billing cycle, etc.'),

                TextInput::make('max_uses')
                    ->label('Max Uses')
                    ->numeric()
                    ->minValue(0)
                    ->placeholder('Enter the maximum number of total uses of the coupon'),

                TextInput::make('max_uses_per_user')
                    ->label('Max Uses Per User')
                    ->numeric()
                    ->minValue(0)
                    ->placeholder('Enter the maximum number of uses per user'),

                DatePicker::make('starts_at')
                    ->label('Starts At'),

                DatePicker::make('expires_at')
                    ->label('Expires At'),

                Repeater::make('couponProducts')
                    ->label('Product/Plan restrictions')
                    ->relationship()
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, $set) {
                                $set('plan_id', null);
                            })
                            ->preload(),
                        Select::make('plan_id')
                            ->label('Plan (optional)')
                            ->options(function (Get $get) {
                                $productId = $get('product_id');
                                if (!$productId) {
                                    return [];
                                }
                                
                                $product = Product::find($productId);
                                if (!$product) {
                                    return [];
                                }
                                
                                return $product->plans->pluck('name', 'id')->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->hint('If empty, coupon applies to all plans of the product'),
                    ])
                    ->grid(2)
                    ->addActionLabel('Add restriction')
                    ->hint('Leave empty to apply to all products and plans'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable(),
                TextColumn::make('value')->searchable()->formatStateUsing(fn ($record) => $record->value . ($record->type === 'percentage' ? '%' : config('settings.default_currency'))),
            ])
            ->filters([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ServicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCoupons::route('/'),
            'create' => CreateCoupon::route('/create'),
            'edit' => EditCoupon::route('/{record}/edit'),
        ];
    }
}
