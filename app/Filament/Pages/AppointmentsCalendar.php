<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AppointmentResource;
use App\Models\Appointment;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;

class AppointmentsCalendar extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Taller';

    protected static ?string $navigationLabel = 'Calendario de Citas';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Calendario de Citas';

    protected static string $view = 'filament.pages.appointments-calendar';

    public array $events = [];

    public function mount(): void
    {
        $this->events = $this->buildEvents();
    }

    protected function buildEvents(): array
    {
        return Appointment::query()
            ->with(['customer:id,name', 'mechanic:id,name', 'vehicle:id,license_plate'])
            ->orderBy('scheduled_at')
            ->get()
            ->map(function (Appointment $appointment): array {
                $start = $appointment->scheduled_at;
                $end = $appointment->ends_at ?? $start?->copy()->addMinutes((int) ($appointment->duration_minutes ?: 60));

                $statusLabel = match ($appointment->status) {
                    'scheduled' => 'Programada',
                    'confirmed' => 'Confirmada',
                    'in_progress' => 'En progreso',
                    'completed' => 'Completada',
                    'cancelled' => 'Cancelada',
                    'no_show' => 'No asistió',
                    default => ucfirst((string) $appointment->status),
                };

                $title = $appointment->title ?: 'Cita';
                $customer = $appointment->customer?->name;

                if ($customer) {
                    $title .= ' - ' . $customer;
                }

                return [
                    'id' => (string) $appointment->id,
                    'title' => $title,
                    'start' => $start?->toIso8601String(),
                    'end' => $end?->toIso8601String(),
                    'allDay' => false,
                    'url' => AppointmentResource::getUrl('edit', ['record' => $appointment]),
                    'extendedProps' => [
                        'estado' => $statusLabel,
                        'mecanico' => $appointment->mechanic?->name ?: 'Sin asignar',
                        'vehiculo' => $appointment->vehicle?->license_plate ?: 'Sin vehículo',
                    ],
                ];
            })
            ->values()
            ->all();
    }

    public function updateAppointmentSchedule(int|string $appointmentId, string $start, ?string $end = null): void
    {
        try {
            $appointment = Appointment::query()->findOrFail($appointmentId);

            if (in_array($appointment->status, ['completed', 'cancelled'], true)) {
                throw new \RuntimeException('No se puede reprogramar una cita completada o cancelada.');
            }

            $startAt = Carbon::parse($start);
            $endAt = $end
                ? Carbon::parse($end)
                : $startAt->copy()->addMinutes((int) ($appointment->duration_minutes ?: 60));

            if ($endAt->lessThanOrEqualTo($startAt)) {
                throw new \RuntimeException('La fecha de fin debe ser mayor a la fecha de inicio.');
            }

            $duration = max(1, $startAt->diffInMinutes($endAt));

            $appointment->update([
                'scheduled_at' => $startAt,
                'ends_at' => $endAt,
                'duration_minutes' => $duration,
            ]);

            // Keep FullCalendar mounted in the browser and avoid a full Livewire re-render.
            $this->skipRender();

            Notification::make()
                ->title('Cita reprogramada')
                ->body('Los cambios se guardaron correctamente.')
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('No se pudo reprogramar la cita')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('list')
                ->label('Ver listado')
                ->icon('heroicon-o-list-bullet')
                ->color('gray')
                ->url(AppointmentResource::getUrl('index')),
            Actions\Action::make('create')
                ->label('Nueva cita')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(AppointmentResource::getUrl('create')),
        ];
    }
}
