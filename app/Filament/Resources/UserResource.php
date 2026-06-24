<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\Roles;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

/**
 * Gestión del equipo del taller (panel /painel). Cada taller administra sus
 * propios usuarios, scopeados a su tenant. No permite crear super-admins.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return __('Usuario');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Equipo');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Configuraciones');
    }

    // ─── Autorización: solo admin/manager del taller gestionan el equipo ────────
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'manager']) ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        // Solo un admin puede borrar, y nunca a sí mismo.
        return auth()->user()?->hasRole('admin')
            && $record->getKey() !== auth()->id();
    }

    public static function getEloquentQuery(): Builder
    {
        // El global scope de tenant ya filtra por taller; excluimos super-admins
        // por las dudas (no deberían tener tenant_id, pero reforzamos).
        return parent::getEloquentQuery()->where('is_super_admin', false);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Datos del usuario'))
                ->columns(2)
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
                    Forms\Components\Select::make('roles')
                        ->label(__('Rol'))
                        ->relationship('roles', 'name')
                        ->getOptionLabelFromRecordUsing(fn ($record): string => Roles::label($record->name))
                        ->multiple()
                        ->preload()
                        ->required()
                        ->helperText(__('Administrador y Gerente gestionan todo; Recepcionista carga sin borrar; Mecánico solo órdenes.')),
                    Forms\Components\TextInput::make('password')
                        ->label(__('Contraseña'))
                        ->password()
                        ->revealable()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->maxLength(255)
                        ->helperText(__('Dejar vacío para mantener la contraseña actual al editar.')),
                    Forms\Components\Toggle::make('is_active')
                        ->label(__('Activo'))
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Nombre'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('Correo'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label(__('Rol'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Roles::label($state)),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Activo'))
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Alta'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label(__('Activo')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(__('Editar')),
                Tables\Actions\DeleteAction::make()->label(__('Eliminar')),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
