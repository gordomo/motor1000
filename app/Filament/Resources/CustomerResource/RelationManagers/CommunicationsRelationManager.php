<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CommunicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'communications';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Comunicaciones');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('channel')->label(__('Canal')),
                Tables\Columns\TextColumn::make('body')->label(__('Mensaje'))->limit(60),
                Tables\Columns\BadgeColumn::make('status')->label(__('Estado')),
                Tables\Columns\TextColumn::make('sent_at')->label(__('Enviado el'))->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
