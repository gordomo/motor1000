<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CommunicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'communications';
    protected static ?string $title = 'Comunicações';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('channel')->label('Canal'),
                Tables\Columns\TextColumn::make('body')->label('Mensagem')->limit(60),
                Tables\Columns\BadgeColumn::make('status')->label('Estado'),
                Tables\Columns\TextColumn::make('sent_at')->label('Enviado em')->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
