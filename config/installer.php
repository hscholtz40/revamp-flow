<?php

return [

    /*
    |--------------------------------------------------------------------------
    | One-shot bootstrap admin (web installer only)
    |--------------------------------------------------------------------------
    |
    | Set at runtime by InstallerController during /install; never read from .env.
    | Cleared immediately after db:seed. Do not use for normal operation.
    |
    */

    'bootstrap_admin_email' => null,

    'bootstrap_admin_password' => null,

    'bootstrap_admin_name' => null,

    'bootstrap_admin_must_reset_password' => false,

];
