<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    public static function getModelLabel(): string
    {
        return __('Taller');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Talleres');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Información del taller'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('Nombre'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) =>
                                $set('slug', Str::slug($state ?? ''))
                            ),
                        Forms\Components\TextInput::make('slug')
                            ->label(__('Slug (URL)'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('email')
                            ->label(__('Correo electrónico'))
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label(__('Teléfono'))
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('whatsapp')
                            ->label(__('WhatsApp'))
                            ->tel()
                            ->maxLength(50),
                    ])->columns(2),

                Forms\Components\Section::make(__('Marca y colores'))
                    ->schema([
                        Forms\Components\FileUpload::make('logo_path')
                            ->label(__('Logo del taller'))
                            ->image()
                            ->directory('logos')
                            ->maxSize(5120)
                            ->helperText(__('PNG, JPG o WebP. Max 5 MB. Recomendado: 300x300 px.')),
                        Forms\Components\ColorPicker::make('primary_color')
                            ->label(__('Color principal'))
                            ->default('#0f766e')
                            ->helperText(__('Usado en botones, links y acentos.')),
                        Forms\Components\ColorPicker::make('secondary_color')
                            ->label(__('Color secundario'))
                            ->default('#f5f5f5')
                            ->helperText(__('Usado en fondos y superficies.')),
                    ])->columns(3),

                Forms\Components\Section::make(__('Dirección'))
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->label(__('Dirección'))
                            ->maxLength(255),
                        Forms\Components\TextInput::make('city')
                            ->label(__('Ciudad'))
                            ->maxLength(100),
                        Forms\Components\TextInput::make('state')
                            ->label(__('Provincia/Estado'))
                            ->maxLength(100),
                        Forms\Components\TextInput::make('zip')
                            ->label(__('Código postal'))
                            ->maxLength(20),
                        Forms\Components\TextInput::make('country')
                            ->label(__('País'))
                            ->default('AR')
                            ->maxLength(10),
                    ])->columns(2),

                Forms\Components\Section::make(__('Configuración'))
                    ->schema([
                        Forms\Components\Select::make('timezone')
                            ->label(__('Zona horaria'))
                            ->options([
                                'America/Argentina/Buenos_Aires' => __('Buenos Aires (UTC-3)'),
                                'America/Mexico_City'            => __('Ciudad de México (UTC-6)'),
                                'America/Bogota'                 => __('Bogotá (UTC-5)'),
                                'America/Santiago'               => __('Santiago (UTC-4)'),
                                'America/Lima'                   => __('Lima (UTC-5)'),
                                'Europe/Madrid'                  => __('Madrid (UTC+1/+2)'),
                            ])
                            ->default('America/Argentina/Buenos_Aires')
                            ->required(),
                        Forms\Components\Select::make('currency')
                            ->label(__('Moneda'))
                            ->options([
                                'ARS' => __('ARS — Peso argentino'),
                                'MXN' => __('MXN — Peso mexicano'),
                                'COP' => __('COP — Peso colombiano'),
                                'CLP' => __('CLP — Peso chileno'),
                                'PEN' => __('PEN — Sol peruano'),
                                'EUR' => __('EUR — Euro'),
                                'USD' => __('USD — Dólar'),
                            ])
                            ->default('ARS')
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Activo'))
                            ->default(true),
                        Forms\Components\DateTimePicker::make('trial_ends_at')
                            ->label(__('Fin del período de prueba'))
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('subscribed_at')
                            ->label(__('Fecha de suscripción'))
                            ->nullable(),
                    ])->columns(2),

                Forms\Components\Section::make(__('Reservas online (turnero)'))
                    ->description(__('Para que esta sucursal acepte turnos desde la landing de su marca.'))
                    ->schema([
                        Forms\Components\Toggle::make('accepts_online_booking')
                            ->label(__('Acepta reservas online'))
                            ->helperText(__('Si está activo, esta sucursal aparece en el turnero de la landing.'))
                            ->default(false),
                        Forms\Components\TextInput::make('brand_slug')
                            ->label(__('Marca (brand)'))
                            ->helperText(__('Agrupa las sucursales de un mismo cliente. Ej: 341boxes'))
                            ->maxLength(60),
                        Forms\Components\TextInput::make('public_api_key')
                            ->label(__('API key pública de la marca'))
                            ->helperText(__('Se usa en la landing para pedir turnos. Generala en UNA sucursal de la marca.'))
                            ->disabled()
                            ->dehydrated()
                            ->default(fn (): string => Str::random(48))
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('regen_api_key')
                                    ->icon('heroicon-m-arrow-path')
                                    ->label(__('Generar'))
                                    ->action(fn (Forms\Set $set) => $set('public_api_key', Str::random(48)))
                            ),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Nombre'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('Correo'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('users_count')
                    ->label(__('Usuarios'))
                    ->counts('users')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Activo'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('trial_ends_at')
                    ->label(__('Prueba hasta'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Creado'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Estado'))
                    ->trueLabel(__('Activos'))
                    ->falseLabel(__('Inactivos')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(__('Editar')),
                Tables\Actions\DeleteAction::make()->label(__('Eliminar')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label(__('Eliminar seleccionados')),
                ]),
            ]);
    }

    public static function getRelationManagers(): array
    {
        return [
            TenantResource\RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit'   => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
