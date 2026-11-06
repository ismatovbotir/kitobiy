<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Market and messages
    |--------------------------------------------------------------------------
    |
    | Selling, swapping and messaging between strangers. Off unless turned on,
    | so a new server never opens them by accident. Existing listings and
    | messages stay in the database and come back when this is turned on.
    |
    */

    'enabled' => (bool) env('MARKET_ENABLED', false),

];
