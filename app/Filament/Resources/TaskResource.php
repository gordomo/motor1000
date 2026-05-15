<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\Pages;
use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $navigationIcon   = 'heroicon-o-check-circle';
    protected static ?string $navigationGroup  = 'Taller';
    protected static ?string $navigationLabel  = 'Tareas';
    protected static ?string $modelLabel       = 'Tarea';
    protected static ?string $pluralModelLabel = 'Tareas';
    protected static ?int    $navigationSort   = 5;

    public static function getNavigationBadge(): ?string
    {
        return (string) Task::whereIn('status', ['open', 'in_progress'])->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Tarea')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull()
                        ->label('Título'),

                    Forms\Components\Select::make('type')
                        ->options([
                            'internal'    => 'Interna',
                            'customer'    => 'Cliente',
                            'maintenance' => 'Mantenimiento',
                            'reminder'    => 'Recordatorio',
                        ])
                        ->required()
                        ->default('internal')
                        ->label('Tipo'),

                    Forms\Components\Select::make('priority')
                        ->options([
                            'low'    => 'Baja',
                            'medium' => 'Media',
                            'high'   => 'Alta',
                            'urgent' => 'Urgente',
                        ])
                        ->required()
                        ->default('medium')
                        ->label('Prioridad'),

                    Forms\Components\Select::make('status')
                        ->options([
                            'open'        => 'Abierta',
                            'in_progress' => 'En progreso',
                            'done'        => 'Completada',
                            'canceled'    => 'Cancelada',
                        ])
                        ->required()
                        ->default('open')
                        ->label('Estado'),

                    Forms\Components\Select::make('assigned_to')
                        ->relationship('assignedTo', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->label('Responsable'),

                    Forms\Components\Select::make('customer_id')
                        ->relationship('customer', 'name')
                        ->searchable()
                        ->nullable()
                        ->label('Cliente'),

                    Forms\Components\Select::make('work_order_id')
                        ->relationship('workOrder', 'number')
                        ->searchable()
                        ->nullable()
                        ->label('Orden de servicio'),

                    Forms\Components\DateTimePicker::make('due_at')
                        ->label('Vencimiento'),

                    Forms\Components\Textarea::make('description')
                        ->rows(3)
                        ->columnSpanFull()
                        ->label('Descripción'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(40)
                    ->label('Título'),

                Tables\Columns\BadgeColumn::make('priority')
                    ->colors([
                        'secondary' => 'low',
                        'primary'   => 'medium',
                        'warning'   => 'high',
                        'danger'    => 'urgent',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'low'    => 'Baja',
                        'medium' => 'Media',
                        'high'   => 'Alta',
                        'urgent' => 'Urgente',
                        default  => $state,
                    })
                    ->label('Prioridad'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'canceled',
                        'primary'   => 'open',
                        'warning'   => 'in_progress',
                        'success'   => 'done',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'open'        => 'Abierta',
                        'in_progress' => 'En progreso',
                        'done'        => 'Completada',
                        'canceled'    => 'Cancelada',
                        default       => $state,
                    })
                    ->label('Estado'),

                Tables\Columns\TextColumn::make('assignedTo.name')
                    ->placeholder('—')
                    ->label('Responsable'),

                Tables\Columns\TextColumn::make('due_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->color(fn (Task $r) => $r->due_at && $r->due_at->isPast() && $r->status !== 'done' ? 'danger' : null)
                    ->label('Vencimiento'),
            ])
            ->defaultSort('due_at', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open'        => 'Abierta',
                        'in_progress' => 'En progreso',
                        'done'        => 'Completada',
                        'canceled'    => 'Cancelada',
                    ])
                    ->label('Estado'),

                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        'low'    => 'Baja',
                        'medium' => 'Media',
                        'high'   => 'Alta',
                        'urgent' => 'Urgente',
                    ])
                    ->label('Prioridad'),

                Tables\Filters\Filter::make('overdue')
                    ->label('Vencidas')
                    ->query(fn (Builder $q) => $q->where('due_at', '<', now())->whereNotIn('status', ['done', 'canceled'])),

                Tables\Filters\Filter::make('mine')
                    ->label('Mis tareas')
                    ->query(fn (Builder $q) => $q->where('assigned_to', auth()->id())),
            ])
            ->actions([
                Tables\Actions\Action::make('complete')
                    ->label('Completar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Task $r) => ! in_array($r->status, ['done', 'canceled']))
                    ->action(fn (Task $r) => $r->update(['status' => 'done', 'completed_at' => now()])),
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
            'index'  => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'edit'   => Pages\EditTask::route('/{record}/edit'),
        ];
    }
}
