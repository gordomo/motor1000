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
    protected static ?string $title = 'Recordatorios';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('type')
                ->label('Tipo')
                ->options(\App\Enums\ReminderType::class)
                ->required(),
            Forms\Components\TextInput::make('title')->label('Título')->required(),
            Forms\Components\DateTimePicker::make('due_at')->label('Vencimiento'),
            Forms\Components\Textarea::make('description')->label('Descripción'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Título'),
                Tables\Columns\BadgeColumn::make('type')->label('Tipo'),
                Tables\Columns\TextColumn::make('due_at')->label('Vencimiento')->dateTime('d/m/Y'),
                Tables\Columns\BadgeColumn::make('status')->label('Estado'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = app('current.tenant')->id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
