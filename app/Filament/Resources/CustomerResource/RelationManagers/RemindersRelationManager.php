<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RemindersRelationManager extends RelationManager
{
    protected static string $relationship = 'reminders';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Recordatorios');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type')
                ->label(__('Tipo'))
                ->options(\App\Enums\ReminderType::class)
                ->required(),
            Forms\Components\TextInput::make('title')->label(__('Título'))->required(),
            Forms\Components\DateTimePicker::make('due_at')->label(__('Vencimiento')),
            Forms\Components\Textarea::make('description')->label(__('Descripción')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label(__('Título')),
                Tables\Columns\BadgeColumn::make('type')->label(__('Tipo')),
                Tables\Columns\TextColumn::make('due_at')->label(__('Vencimiento'))->dateTime('d/m/Y'),
                Tables\Columns\BadgeColumn::make('status')->label(__('Estado')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = \App\Support\CurrentTenant::id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
