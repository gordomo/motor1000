<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\UserResource\Pages;
use App\Models\Tenant;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getModelLabel(): string
    {
        return __('Usuario');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Usuarios');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Datos del usuario'))
                    ->schema([
                        Forms\Components\Select::make('tenant_id')
                            ->label(__('Taller'))
                            ->relationship('tenant', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText(__('Dejar vacío si es super administrador.')),
                        Forms\Components\TextInput::make('name')
                            ->label(__('Nombre'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label(__('Correo electrónico'))
                            ->email()
                            ->required()
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label(__('Teléfono'))
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('password')
                            ->label(__('Contraseña'))
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->maxLength(255)
                            ->helperText(__('Dejar vacío para mantener la contraseña actual al editar.')),
                        Forms\Components\Select::make('roles')
                            ->label(__('Roles / permisos'))
                            ->relationship('roles', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => \App\Support\Roles::label($record->name))
                            ->multiple()
                            ->preload()
                            ->helperText(__('Define qué puede hacer el usuario dentro del panel del taller.')),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Activo'))
                            ->default(true),
                        Forms\Components\Toggle::make('is_super_admin')
                            ->label(__('Super administrador'))
                            ->helperText(__('Los super admins solo acceden al panel /admin, no al panel del taller.')),
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
                Tables\Columns\TextColumn::make('email')
                    ->label(__('Correo'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label(__('Taller'))
                    ->searchable()
                    ->sortable()
                    ->default('—'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Activo'))
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_super_admin')
                    ->label(__('Super admin'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Creado'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label(__('Taller'))
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Activo')),
                Tables\Filters\TernaryFilter::make('is_super_admin')
                    ->label(__('Super admin')),
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

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return User::withoutGlobalScopes()->orderBy('name');
    }
}
