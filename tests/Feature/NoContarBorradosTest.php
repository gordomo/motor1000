<?php

/**
 * Guardia contra un error fácil de repetir.
 *
 * withoutGlobalScopes() SIN argumentos quita todos los scopes del modelo, y eso
 * incluye el de borrado lógico. Los widgets y servicios lo usaban para poder
 * filtrar por taller a mano, y de paso empezaron a contar registros borrados: en
 * producción el tablero mostró "Listo para cobrar $1" con el kanban en cero,
 * porque había una orden de prueba borrada con total 1.
 *
 * Lo correcto es withoutGlobalScopes([TenantScope::class]): saca solo el scope de
 * taller y mantiene el de borrado.
 */

use Illuminate\Support\Facades\File;

/** Lugares donde quitar TODOS los scopes es correcto y está justificado. */
const EXCEPCIONES = [
    // Numeración de comprobantes: tiene que ver los borrados para no reutilizar
    // un número que ya se usó.
    'app/Models/Quote.php',
    'app/Models/WorkOrder.php',
    'app/Models/Inspection.php',
    'app/Filament/Resources/InvoiceResource/Pages/CreateInvoice.php',
    // El email es único en la base y un usuario borrado lo sigue ocupando.
    'app/Console/Commands/CreateSuperAdmin.php',
];

it('nadie usa withoutGlobalScopes() sin argumentos fuera de las excepciones', function () {
    $infractores = [];

    foreach (File::allFiles(base_path('app')) as $archivo) {
        if ($archivo->getExtension() !== 'php') {
            continue;
        }

        $relativo = 'app/' . $archivo->getRelativePathname();

        if (in_array($relativo, EXCEPCIONES, true)) {
            continue;
        }

        $contenido = File::get($archivo->getPathname());

        // withoutGlobalScopes() con paréntesis vacío = se van todos los scopes.
        if (preg_match('/withoutGlobalScopes\(\s*\)/', $contenido)) {
            $infractores[] = $relativo;
        }
    }

    expect($infractores)->toBe([], implode("\n", array_merge(
        ['Estos archivos quitan TODOS los scopes, así que cuentan registros borrados.'],
        ['Usá withoutGlobalScopes([TenantScope::class]) para sacar solo el de taller:'],
        $infractores,
    )));
});
