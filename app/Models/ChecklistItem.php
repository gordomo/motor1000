<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Punto del checklist de revisión, configurable por taller (pedidos 2 y 17).
 *
 * Los presupuestos y las revisiones guardan un snapshot del checklist en su propia
 * columna JSON: si el taller edita sus puntos, los documentos ya emitidos conservan
 * lo que efectivamente se revisó. Este modelo es solo el catálogo vigente.
 */
class ChecklistItem extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'categoria',
        'nombre_item',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    /**
     * Catálogo con el que arranca cada taller. Era la lista fija de 20 puntos que
     * vivía en Quote::defaultChecklist(); ahora es solo la semilla inicial.
     */
    public const DEFAULT_CATALOG = [
        ['categoria' => 'Frenos',      'nombre_item' => 'Pastillas de freno delanteras'],
        ['categoria' => 'Frenos',      'nombre_item' => 'Pastillas de freno traseras'],
        ['categoria' => 'Frenos',      'nombre_item' => 'Líquido de frenos'],
        ['categoria' => 'Suspensión',  'nombre_item' => 'Amortiguadores delanteros'],
        ['categoria' => 'Suspensión',  'nombre_item' => 'Amortiguadores traseros'],
        ['categoria' => 'Neumáticos',  'nombre_item' => 'Presión neumáticos'],
        ['categoria' => 'Neumáticos',  'nombre_item' => 'Desgaste de neumáticos'],
        ['categoria' => 'Fluidos',     'nombre_item' => 'Nivel de aceite de motor'],
        ['categoria' => 'Fluidos',     'nombre_item' => 'Líquido refrigerante'],
        ['categoria' => 'Fluidos',     'nombre_item' => 'Líquido de dirección'],
        ['categoria' => 'Fluidos',     'nombre_item' => 'Líquido de transmisión'],
        ['categoria' => 'Luces',       'nombre_item' => 'Luces delanteras (bajas/altas)'],
        ['categoria' => 'Luces',       'nombre_item' => 'Luces traseras y stop'],
        ['categoria' => 'Luces',       'nombre_item' => 'Luces de giro (intermitentes)'],
        ['categoria' => 'Motor',       'nombre_item' => 'Correa de distribución'],
        ['categoria' => 'Motor',       'nombre_item' => 'Filtro de aire'],
        ['categoria' => 'Carrocería',  'nombre_item' => 'Limpiaparabrisas'],
        ['categoria' => 'Carrocería',  'nombre_item' => 'Estado general de carrocería'],
        ['categoria' => 'Seguridad',   'nombre_item' => 'Cinturones de seguridad'],
        ['categoria' => 'Seguridad',   'nombre_item' => 'Bocina'],
    ];

    protected static function booted(): void
    {
        // Nuevo punto al final de la lista si no se indicó posición.
        static::creating(function (ChecklistItem $item) {
            if (! $item->sort_order) {
                $item->sort_order = (int) static::query()->max('sort_order') + 1;
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Checklist en blanco del taller actual, con la forma que esperan el form y el
     * PDF: id_punto, categoria, nombre_item, estado, aclaracion.
     *
     * Si el taller todavía no tiene puntos configurados cae al catálogo por defecto,
     * para que ni el presupuesto ni la revisión queden sin nada que revisar.
     *
     * @return list<array<string, mixed>>
     */
    public static function blankChecklist(): array
    {
        $items = static::query()->active()->ordered()->get();

        if ($items->isEmpty()) {
            $items = collect(self::DEFAULT_CATALOG)->map(fn (array $i, int $index) => new static([
                'categoria'   => $i['categoria'],
                'nombre_item' => $i['nombre_item'],
                'sort_order'  => $index + 1,
            ]));
        }

        return $items->values()->map(fn (ChecklistItem $item, int $index): array => [
            'id_punto'    => $item->id ?? $index + 1,
            'categoria'   => $item->categoria,
            'nombre_item' => $item->nombre_item,
            'estado'      => null,
            'aclaracion'  => '',
        ])->all();
    }
}
