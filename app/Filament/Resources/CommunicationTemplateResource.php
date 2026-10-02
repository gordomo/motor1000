<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommunicationTemplateResource\Pages;
use App\Support\Mensajes;
use App\Models\CommunicationTemplate;
use App\Filament\Concerns\HiddenFromMechanics;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CommunicationTemplateResource extends Resource
{
    use HiddenFromMechanics;

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
            Forms\Components\Section::make(__('Mensaje'))
                ->description(__('Elegí qué mensaje querés cambiar. Si no hay una plantilla activa, el sistema usa el texto de siempre.'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('event')
                        ->label(__('Mensaje'))
                        ->options(fn (?CommunicationTemplate $record): array => self::opcionesDeEvento($record))
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state, string $operation): void {
                            $canales = array_keys(Mensajes::catalogo()[$state]['canales'] ?? []);
                            if (! in_array($get('channel'), $canales, true)) {
                                $set('channel', $canales[0] ?? null);
                            }
                            if ($operation === 'create') {
                                self::completarConElTextoDeSiempre($get, $set);
                            }
                        }),

                    Forms\Components\Select::make('channel')
                        ->label(__('Canal'))
                        ->options(fn (Get $get): array => collect(Mensajes::catalogo()[$get('event')]['canales'] ?? ['whatsapp' => [], 'email' => []])
                            ->keys()
                            ->mapWithKeys(fn (string $c): array => [$c => self::nombreDeCanal($c)])
                            ->all())
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set, string $operation): void {
                            if ($operation === 'create') {
                                self::completarConElTextoDeSiempre($get, $set);
                            }
                        }),

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->label(__('Nombre')),

                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->inline(false)
                        ->helperText(__('Si la desactivás, se vuelve a usar el texto de siempre.'))
                        ->label(__('Activa')),
                ]),

            Forms\Components\Section::make(__('Texto'))
                ->schema([
                    Forms\Components\TextInput::make('subject')
                        ->maxLength(255)
                        ->label(__('Asunto'))
                        ->visible(fn (Get $get): bool => $get('channel') === 'email'),

                    Forms\Components\Textarea::make('body')
                        ->required()
                        ->rows(6)
                        ->label(__('Texto del mensaje'))
                        ->helperText(fn (Get $get): string => self::ayudaDeVariables($get('event'))),
                ]),
        ]);
    }

    /** Los mensajes del sistema, más el evento guardado si es uno viejo que ya no está en la lista. */
    private static function opcionesDeEvento(?CommunicationTemplate $record): array
    {
        $opciones = Mensajes::opciones();

        if ($record?->event && ! isset($opciones[$record->event])) {
            $opciones[$record->event] = $record->event . ' ' . __('(el sistema no lo usa)');
        }

        return $opciones;
    }

    private static function completarConElTextoDeSiempre(Get $get, Set $set): void
    {
        $mensaje = Mensajes::catalogo()[$get('event')] ?? null;
        $canal = $mensaje['canales'][$get('channel')] ?? null;

        if (! $mensaje || ! $canal) {
            return;
        }

        $set('name', $mensaje['nombre'] . ' (' . self::nombreDeCanal($get('channel')) . ')');
        $set('body', $canal['cuerpo']);
        $set('subject', $canal['asunto'] ?? null);
    }

    private static function ayudaDeVariables(?string $evento): string
    {
        $variables = Mensajes::catalogo()[$evento]['variables'] ?? [];

        if (! $variables) {
            return __('Elegí un mensaje para ver qué datos podés usar.');
        }

        return __('Podés usar:') . ' ' . collect($variables)
            ->map(fn (string $descripcion, string $clave): string => '{' . $clave . '} ' . mb_strtolower($descripcion))
            ->implode(' · ');
    }

    public static function nombreDeCanal(?string $canal): string
    {
        return match ($canal) {
            'whatsapp' => 'WhatsApp',
            'email'    => 'Email',
            'sms'      => 'SMS',
            default    => (string) $canal,
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label(__('Nombre')),


                Tables\Columns\BadgeColumn::make('channel')
                    ->colors([
                        'success' => 'whatsapp',
                        'primary' => 'email',
                        'warning' => 'sms',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'whatsapp' => 'WhatsApp',
                        'email'    => 'Email',
                        'sms'      => 'SMS',
                        default    => $state,
                    })
                    ->label(__('Canal')),

                Tables\Columns\TextColumn::make('event')
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => Mensajes::opciones()[$state] ?? (string) $state)
                    ->label(__('Mensaje')),

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
                        'email'    => 'Email',
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
