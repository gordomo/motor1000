<?php

namespace App\Filament\Admin\Resources\TenantResource\RelationManagers;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Usuarios del taller');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Nombre'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('Correo'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label(__('Teléfono')),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Activo'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Creado'))
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label(__('Nuevo usuario')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(__('Editar')),
                Tables\Actions\DeleteAction::make()->label(__('Eliminar')),
            ]);
    }
}
