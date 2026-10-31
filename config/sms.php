<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS driver
    |--------------------------------------------------------------------------
    |
    | "log" writes outgoing messages to the application log, for development.
    | "semaphore" sends through the Semaphore gateway (https://semaphore.co).
    |
    */

    'driver' => env('SMS_DRIVER', 'log'),

    'semaphore' => [
        'api_key' => env('SEMAPHORE_API_KEY'),
        'sender_name' => env('SEMAPHORE_SENDER_NAME'),
    ],

];
