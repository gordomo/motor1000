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

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.appointments-calendar';

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Calendario de Citas');
    }

    public static function getNavigationLabel(): string
    {
        return __('Calendario de Citas');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Taller');
    }

    public array $events = [];

    public function mount(): void
    {
        $this->events = array_merge($this->buildEvents(), $this->buildDeliveryEvents());
    }

    /**
     * Falla #5: las previsiones de entrega de las órdenes de servicio deben verse
     * en el calendario, no sólo las citas creadas a mano.
     */
    protected function buildDeliveryEvents(): array
    {
        return \App\Models\WorkOrder::query()
            ->whereNotNull('estimated_at')
            ->where('status', '!=', \App\Enums\WorkOrderStatus::Delivered)
            ->with(['customer:id,name', 'vehicle:id,license_plate', 'mechanic:id,name'])
            ->orderBy('estimated_at')
            ->get()
            ->map(function (\App\Models\WorkOrder $order): array {
                $start = $order->estimated_at;
                $customer = $order->customer?->name;
                $title = __('Entrega ') . $order->number . ($customer ? ' - ' . $customer : '');

                return [
                    'id' => 'wo-' . $order->id,
                    'title' => $title,
                    'start' => $start?->toIso8601String(),
                    'end' => $start?->copy()->addMinutes(30)->toIso8601String(),
                    'allDay' => false,
                    'editable' => false,
                    'color' => '#f59e0b',
                    'url' => \App\Filament\Resources\WorkOrderResource::getUrl('view', ['record' => $order]),
                    'extendedProps' => [
                        'estado' => __('Entrega prevista'),
                        'mecanico' => $order->mechanic?->name ?: __('Sin asignar'),
                        'vehiculo' => $order->vehicle?->license_plate ?: __('Sin vehículo'),
                    ],
                ];
            })
            ->values()
            ->all();
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
                    'scheduled' => __('Programada'),
                    'confirmed' => __('Confirmada'),
                    'in_progress' => __('En progreso'),
                    'completed' => __('Completada'),
                    'cancelled' => __('Cancelada'),
                    'no_show' => __('No asistió'),
                    default => ucfirst((string) $appointment->status),
                };

                $title = $appointment->title ?: __('Cita');
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
                        'mecanico' => $appointment->mechanic?->name ?: __('Sin asignar'),
                        'vehiculo' => $appointment->vehicle?->license_plate ?: __('Sin vehículo'),
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
                throw new \RuntimeException(__('No se puede reprogramar una cita completada o cancelada.'));
            }

            $startAt = Carbon::parse($start);
            $endAt = $end
                ? Carbon::parse($end)
                : $startAt->copy()->addMinutes((int) ($appointment->duration_minutes ?: 60));

            if ($endAt->lessThanOrEqualTo($startAt)) {
                throw new \RuntimeException(__('La fecha de fin debe ser mayor a la fecha de inicio.'));
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
                ->title(__('Cita reprogramada'))
                ->body(__('Los cambios se guardaron correctamente.'))
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title(__('No se pudo reprogramar la cita'))
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
                ->label(__('Ver listado'))
                ->icon('heroicon-o-list-bullet')
                ->color('gray')
                ->url(AppointmentResource::getUrl('index')),
            Actions\Action::make('create')
                ->label(__('Nueva cita'))
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(AppointmentResource::getUrl('create')),
        ];
    }
}
