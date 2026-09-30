<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Work Order Generator Driver
    |--------------------------------------------------------------------------
    |
    | Controls which driver is used to generate or fetch work order numbers.
    |
    | Supported: "sequential", "quickbooks" (or custom drivers implementing
    | App\Contracts\WorkOrderNumberGenerator).
    |
    */
    'driver' => env('WORK_ORDER_DRIVER', 'sequential'),

    /*
    |--------------------------------------------------------------------------
    | Default Starting Number
    |--------------------------------------------------------------------------
    |
    | If no prior work order numbers are found in the database, the sequential
    | generator will initialize sequence numbers starting from this value.
    |
    */
    'starting_number' => (int) env('WORK_ORDER_STARTING_NUMBER', 10001),
];
