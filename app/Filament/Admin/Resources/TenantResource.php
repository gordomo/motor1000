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

    protected static ?string $modelLabel = 'Taller';

    protected static ?string $pluralModelLabel = 'Talleres';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información del taller')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) =>
                                $set('slug', Str::slug($state ?? ''))
                            ),
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug (URL)')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('whatsapp')
                            ->label('WhatsApp')
                            ->tel()
                            ->maxLength(50),
                    ])->columns(2),

                Forms\Components\Section::make('Marca y colores')
                    ->schema([
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Logo del taller')
                            ->image()
                            ->directory('logos')
                            ->maxSize(5120)
                            ->helperText('PNG, JPG o WebP. Max 5 MB. Recomendado: 300x300 px.'),
                        Forms\Components\ColorPicker::make('primary_color')
                            ->label('Color principal')
                            ->default('#0f766e')
                            ->helperText('Usado en botones, links y acentos.'),
                        Forms\Components\ColorPicker::make('secondary_color')
                            ->label('Color secundario')
                            ->default('#f5f5f5')
                            ->helperText('Usado en fondos y superficies.'),
                    ])->columns(3),

                Forms\Components\Section::make('Dirección')
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->label('Dirección')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('city')
                            ->label('Ciudad')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('state')
                            ->label('Provincia/Estado')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('zip')
                            ->label('Código postal')
                            ->maxLength(20),
                        Forms\Components\TextInput::make('country')
                            ->label('País')
                            ->default('AR')
                            ->maxLength(10),
                    ])->columns(2),

                Forms\Components\Section::make('Configuración')
                    ->schema([
                        Forms\Components\Select::make('timezone')
                            ->label('Zona horaria')
                            ->options([
                                'America/Argentina/Buenos_Aires' => 'Buenos Aires (UTC-3)',
                                'America/Mexico_City'            => 'Ciudad de México (UTC-6)',
                                'America/Bogota'                 => 'Bogotá (UTC-5)',
                                'America/Santiago'               => 'Santiago (UTC-4)',
                                'America/Lima'                   => 'Lima (UTC-5)',
                                'Europe/Madrid'                  => 'Madrid (UTC+1/+2)',
                            ])
                            ->default('America/Argentina/Buenos_Aires')
                            ->required(),
                        Forms\Components\Select::make('currency')
                            ->label('Moneda')
                            ->options([
                                'ARS' => 'ARS — Peso argentino',
                                'MXN' => 'MXN — Peso mexicano',
                                'COP' => 'COP — Peso colombiano',
                                'CLP' => 'CLP — Peso chileno',
                                'PEN' => 'PEN — Sol peruano',
                                'EUR' => 'EUR — Euro',
                                'USD' => 'USD — Dólar',
                            ])
                            ->default('ARS')
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                        Forms\Components\DateTimePicker::make('trial_ends_at')
                            ->label('Fin del período de prueba')
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('subscribed_at')
                            ->label('Fecha de suscripción')
                            ->nullable(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->counts('users')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('trial_ends_at')
                    ->label('Prueba hasta')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Editar'),
                Tables\Actions\DeleteAction::make()->label('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Eliminar seleccionados'),
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
