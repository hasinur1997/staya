<?php

return [

    /*
    |--------------------------------------------------------------------------
    | First owner account
    |--------------------------------------------------------------------------
    |
    | Read by `php artisan staya:create-owner`, which the deploy runs after
    | migrating. It creates this Admin account only while no Admin exists, so
    | changing the values later never resets a password.
    |
    */

    'owner' => [
        'name' => env('STAYA_OWNER_NAME', 'Owner'),
        'name_bn' => env('STAYA_OWNER_NAME_BN'),
        'email' => env('STAYA_OWNER_EMAIL'),
        'password' => env('STAYA_OWNER_PASSWORD'),
    ],

];
