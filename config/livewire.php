<?php

/*
 * Only the keys that differ from Livewire's defaults; the rest are merged in
 * by the package's service provider.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Asset injection
    |--------------------------------------------------------------------------
    |
    | Off: Livewire would otherwise inject its scripts into any HTML response,
    | including central-domain pages where the tenant-bound update endpoint
    | cannot be resolved. The tenant layouts include @livewireStyles and
    | @livewireScripts explicitly instead, which also keeps a future
    | Content-Security-Policy predictable.
    |
    */

    'inject_assets' => false,

];
