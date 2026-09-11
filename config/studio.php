<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Studio Owner
    |--------------------------------------------------------------------------
    |
    | The owner account created by the database seeder. The owner is also a
    | trainer and the only account that can invite others. Leave the password
    | empty to have a random one generated and printed once while seeding.
    |
    */

    'owner' => [
        'name' => env('OWNER_NAME', 'Maciej Samborski'),
        'email' => env('OWNER_EMAIL', 'maciek@samtrening.com'),
        'password' => env('OWNER_PASSWORD'),
    ],

];
