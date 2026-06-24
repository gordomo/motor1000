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
    protected static ?int    $navigationSort   = 3;

    public static function getModelLabel(): string
    {
        return __('Plantilla');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Plantillas');
    }

    public static function getNavigationLabel(): string
    {
        return __('Plantillas de comunicación');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Configuraciones');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Identificación'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->label(__('Nombre')),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->label(__('Slug')),

                    Forms\Components\Select::make('channel')
                        ->options([
                            'whatsapp' => 'WhatsApp',
                            'email'    => 'E-mail',
                            'sms'      => 'SMS',
                        ])
                        ->required()
                        ->label(__('Canal')),

                    Forms\Components\TextInput::make('event')
                        ->maxLength(100)
                        ->placeholder('ex: work_order.completed')
                        ->label(__('Evento')),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->label(__('Activo')),
                ]),

            Forms\Components\Section::make(__('Contenido'))
                ->schema([
                    Forms\Components\TextInput::make('subject')
                        ->maxLength(255)
                        ->placeholder(__('Asunto (solo correo)'))
                        ->label(__('Asunto')),

                    Forms\Components\Textarea::make('body')
                        ->required()
                        ->rows(8)
                        ->hint(__('Usa {variable} para insertar datos dinámicos'))
                        ->label(__('Cuerpo del mensaje')),

                    Forms\Components\KeyValue::make('variables')
                        ->nullable()
                        ->label(__('Variables disponibles'))
                        ->hint(__('Clave: nombre de variable | Valor: descripción')),
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
                    ->label(__('Nombre')),

                Tables\Columns\TextColumn::make('slug')
                    ->label(__('Slug')),

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
                    ->label(__('Canal')),

                Tables\Columns\TextColumn::make('event')
                    ->placeholder('—')
                    ->label(__('Evento')),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('Activo')),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d/m/Y')
                    ->label(__('Actualizado')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->options([
                        'whatsapp' => 'WhatsApp',
                        'email'    => 'E-mail',
                        'sms'      => 'SMS',
                    ])
                    ->label(__('Canal')),
                Tables\Filters\TernaryFilter::make('is_active')->label(__('Activo')),
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
