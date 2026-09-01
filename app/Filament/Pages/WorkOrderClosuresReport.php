<?php

namespace App\Filament\Pages;

use App\Scopes\TenantScope;
use App\Enums\WorkOrderStatus;
use App\Exports\WorkOrderClosuresExport;
use App\Models\WorkOrder;
use App\Support\CurrentTenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Pedido nuevo: cuántas órdenes de trabajo se cierran por día, semana y mes.
 *
 * "Cerrada" = pasó a Completado (el trabajo terminó). Se usa completed_at y no
 * delivered_at porque la entrega depende de cuándo pase el cliente a buscar el
 * auto, que no mide el trabajo del taller.
 */
class WorkOrderClosuresReport extends Page
{
    // Page ya implementa HasForms y usa InteractsWithForms.

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.work-order-closures-report';

    /**
     * Cuándo se considera cerrada una orden.
     *
     * La definición es "cuando terminó el trabajo" (completed_at). Pero muchas
     * órdenes se movieron directo a Entregado sin pasar por Completado, así que no
     * tienen esa fecha: de 49 entregadas en prod, 27 quedaban afuera del informe.
     * Para esas se usa la fecha de entrega, que es el mejor dato disponible.
     */
    private const FECHA_CIERRE = 'COALESCE(work_orders.completed_at, work_orders.delivered_at)';

    public ?string $desde = null;

    public ?string $hasta = null;

    public static function getNavigationGroup(): ?string
    {
        return __('Taller');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Órdenes cerradas');
    }

    public static function getNavigationLabel(): string
    {
        return __('Órdenes cerradas');
    }

    public static function canAccess(): bool
    {
        // Es información de gestión, no de taller: el mecánico no la necesita.
        return auth()->user()?->hasAnyRole(['admin', 'receptionist']) ?? false;
    }

    public function mount(): void
    {
        // Por defecto, el mes actual.
        $this->form->fill([
            'desde' => now()->startOfMonth()->toDateString(),
            'hasta' => now()->endOfMonth()->toDateString(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label(__('Descargar PDF'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    [$desde, $hasta] = $this->rango();

                    return response()->streamDownload(function () use ($desde, $hasta) {
                        echo Pdf::loadView('pdf.work-order-closures', [
                            'desde'   => $desde,
                            'hasta'   => $hasta,
                            'resumen' => $this->resumen,
                            'porDia'  => $this->porDia,
                            'porMes'  => $this->porMes,
                            'tenant'  => CurrentTenant::get(),
                        ])->setPaper('a4')->output();
                    }, 'ordenes-cerradas-' . $desde->format('Y-m-d') . '-a-' . $hasta->format('Y-m-d') . '.pdf');
                }),
            Action::make('excel')
                ->label(__('Descargar Excel'))
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->action(function () {
                    [$desde, $hasta] = $this->rango();

                    return Excel::download(
                        new WorkOrderClosuresExport($desde, $hasta),
                        'ordenes-cerradas-' . $desde->format('Y-m-d') . '-a-' . $hasta->format('Y-m-d') . '.xlsx',
                    );
                }),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->columns(2)
                    ->schema([
                        DatePicker::make('desde')
                            ->label(__('Desde'))
                            ->native(false)
                            ->live(),
                        DatePicker::make('hasta')
                            ->label(__('Hasta'))
                            ->native(false)
                            ->live(),
                    ]),
            ])
            ->statePath('');
    }

    /** Rango elegido, saneado: si viene al revés se da vuelta. */
    public function rango(): array
    {
        $desde = CarbonImmutable::parse($this->desde ?: now()->startOfMonth())->startOfDay();
        $hasta = CarbonImmutable::parse($this->hasta ?: now()->endOfMonth())->endOfDay();

        return $desde->greaterThan($hasta) ? [$hasta, $desde] : [$desde, $hasta];
    }

    /**
     * Totales de cierres del período, más los promedios que pidió el cliente
     * (por día, por semana y por mes).
     */
    public function getResumenProperty(): array
    {
        [$desde, $hasta] = $this->rango();

        $total = $this->enRango($desde, $hasta)->count();
        $dias  = max(1, $desde->diffInDays($hasta) + 1);

        return [
            'total'      => $total,
            'dias'       => $dias,
            'por_dia'    => round($total / $dias, 1),
            'por_semana' => round($total / max(1, $dias / 7), 1),
            'por_mes'    => round($total / max(1, $dias / 30), 1),
            'hoy'        => $this->baseQuery()
                ->whereRaw('DATE(' . self::FECHA_CIERRE . ') = ?', [today()->toDateString()])
                ->count(),
            'semana'     => $this->enRango(now()->startOfWeek(), now()->endOfWeek())->count(),
            'mes'        => $this->enRango(now()->startOfMonth(), now()->endOfMonth())->count(),
            // Cuántas usan la fecha de entrega por no tener fecha de cierre, para
            // poder aclararlo en pantalla en vez de que el número no cierre.
            'sin_cierre' => $this->enRango($desde, $hasta)->whereNull('completed_at')->count(),
        ];
    }

    /** Serie diaria del período, para el detalle en pantalla. */
    public function getPorDiaProperty(): array
    {
        [$desde, $hasta] = $this->rango();

        $filas = $this->enRango($desde, $hasta)
            ->selectRaw('DATE(' . self::FECHA_CIERRE . ') as dia, COUNT(*) as total')
            ->groupBy('dia')
            ->orderByDesc('dia')
            ->get();

        return $filas->map(fn ($f): array => [
            'dia'   => CarbonImmutable::parse($f->dia),
            'total' => (int) $f->total,
        ])->all();
    }

    /** Serie por mes, para ver la tendencia. */
    public function getPorMesProperty(): array
    {
        [$desde, $hasta] = $this->rango();

        // DATE_FORMAT es de MySQL; en SQLite (tests) se usa strftime.
        $expr = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', " . self::FECHA_CIERRE . ")"
            : "DATE_FORMAT(" . self::FECHA_CIERRE . ", '%Y-%m')";

        return $this->enRango($desde, $hasta)
            ->selectRaw("{$expr} as mes, COUNT(*) as total")
            ->groupBy('mes')
            ->orderByDesc('mes')
            ->get()
            ->map(fn ($f): array => ['mes' => $f->mes, 'total' => (int) $f->total])
            ->all();
    }

    /**
     * Órdenes cerradas del taller actual. Cerrada = tiene completed_at, sin
     * importar si después se entregó.
     */
    private function baseQuery()
    {
        return WorkOrder::withoutGlobalScopes([TenantScope::class])
            ->where('tenant_id', CurrentTenant::id() ?? 0)
            ->whereIn('status', [WorkOrderStatus::Completed->value, WorkOrderStatus::Delivered->value])
            // Tiene que tener alguna de las dos fechas para poder ubicarla en el
            // tiempo. Antes se exigía completed_at y eso escondía las órdenes que
            // se movieron directo a Entregado.
            ->where(fn ($q) => $q->whereNotNull('completed_at')->orWhereNotNull('delivered_at'));
    }

    /** Órdenes cerradas dentro del rango, por su fecha de cierre real. */
    private function enRango($desde, $hasta)
    {
        return $this->baseQuery()
            ->whereRaw(self::FECHA_CIERRE . ' BETWEEN ? AND ?', [$desde, $hasta]);
    }
}
