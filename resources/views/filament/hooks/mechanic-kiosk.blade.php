{{-- Modo taller: el mecánico usa el panel desde una tablet o un totem y su menú
     tiene dos ítems. La barra lateral es ruido, y que apareciera en una pantalla
     y no en otra rompía la sensación de estar en el mismo sistema.

     Se aplica desde un render hook del panel, así vale para TODAS sus pantallas.
     No se puede resolver con navigation(false) porque el panel se arma antes de
     saber quién es el usuario. --}}
<style>
    .fi-sidebar,
    .fi-topbar-open-sidebar-btn,
    .fi-sidebar-close-overlay {
        display: none !important;
    }

    /* El panel limita el contenido a 1560px y lo centra (ver theme-overrides).
       Sin la barra lateral hay que soltar ese tope: si solo se anula el margen
       izquierdo, el sobrante se va entero a la derecha y queda un hueco. En una
       tablet o un totem conviene usar todo el ancho. */
    .fi-main-ctn {
        max-width: none !important;
        margin-inline: 0 !important;
    }
</style>
