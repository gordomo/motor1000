<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommunicationTemplateResource\Pages;
use App\Models\CommunicationTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CommunicationTemplateResource extends Resource
{
    protected static ?string $model = CommunicationTemplate::class;

    protected static ?string $navigationIcon   = 'heroicon-o-chat-bubble-left-ellipsis';
    protected static ?string $navigationGroup  = 'Configuraciones';
    protected static ?string $navigationLabel  = 'Plantillas de comunicación';
    protected static ?string $modelLabel       = 'Plantilla';
    protected static ?string $pluralModelLabel = 'Plantillas';
    protected static ?int    $navigationSort   = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificación')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->label('Nombre'),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->label('Slug'),

                    Forms\Components\Select::make('channel')
                        ->options([
                            'whatsapp' => 'WhatsApp',
                            'email'    => 'E-mail',
                            'sms'      => 'SMS',
                        ])
                        ->required()
                        ->label('Canal'),

                    Forms\Components\TextInput::make('event')
                        ->maxLength(100)
                        ->placeholder('ex: work_order.completed')
                        ->label('Evento'),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->label('Activo'),
                ]),

            Forms\Components\Section::make('Contenido')
                ->schema([
                    Forms\Components\TextInput::make('subject')
                        ->maxLength(255)
                        ->placeholder('Asunto (solo correo)')
                        ->label('Asunto'),

                    Forms\Components\Textarea::make('body')
                        ->required()
                        ->rows(8)
                        ->hint('Usa {variable} para insertar datos dinámicos')
                        ->label('Cuerpo del mensaje'),

                    Forms\Components\KeyValue::make('variables')
                        ->nullable()
                        ->label('Variables disponibles')
                        ->hint('Clave: nombre de variable | Valor: descripción'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Nombre'),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug'),

                Tables\Columns\BadgeColumn::make('channel')
                    ->colors([
                        'success' => 'whatsapp',
                        'primary' => 'email',
                        'warning' => 'sms',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'whatsapp' => 'WhatsApp',
                        'email'    => 'E-mail',
                        'sms'      => 'SMS',
                        default    => $state,
                    })
                    ->label('Canal'),

                Tables\Columns\TextColumn::make('event')
                    ->placeholder('—')
                    ->label('Evento'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Activo'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d/m/Y')
                    ->label('Actualizado'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->options([
                        'whatsapp' => 'WhatsApp',
                        'email'    => 'E-mail',
                        'sms'      => 'SMS',
                    ])
                    ->label('Canal'),
                Tables\Filters\TernaryFilter::make('is_active')->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCommunicationTemplates::route('/'),
            'create' => Pages\CreateCommunicationTemplate::route('/create'),
            'edit'   => Pages\EditCommunicationTemplate::route('/{record}/edit'),
        ];
    }
}
