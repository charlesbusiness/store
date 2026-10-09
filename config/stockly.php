<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | The symbol prefixed onto every money figure. Set it to '' to drop the
    | symbol entirely, or swap it for a different one per deployment.
    |
    */

    'currency' => env('STOCKLY_CURRENCY', "\u{20A6}"),

    /*
    |--------------------------------------------------------------------------
    | Display precision
    |--------------------------------------------------------------------------
    |
    | Money is shown with at most 2 decimals and quantities with at most 3,
    | exactly as the original ledger did. These are maximums, not fixed
    | widths, so 1234.5 still renders as 1,234.5.
    |
    */

    'money_decimals' => 2,

    'quantity_decimals' => 3,

    /*
    |--------------------------------------------------------------------------
    | Stock tolerance
    |--------------------------------------------------------------------------
    |
    | Base quantities are stored as decimals, so equality checks use a small
    | tolerance rather than exact floats. This is the database-era
    | replacement for the original's 1e-9 fudge factor.
    |
    */

    'tolerance' => 0.0001,

    /*
    |--------------------------------------------------------------------------
    | Recent activity
    |--------------------------------------------------------------------------
    |
    | How many recent entries to list under the sell and buy forms.
    |
    */

    'recent_limit' => 8,

    /*
    |--------------------------------------------------------------------------
    | Demo data
    |--------------------------------------------------------------------------
    |
    | When enabled, `php artisan db:seed` loads a sample ledger so the reports
    | and trend chart have something to show.
    |
    */

    'demo' => (bool) env('STOCKLY_DEMO', false),

    /*
    |--------------------------------------------------------------------------
    | Destructive command override
    |--------------------------------------------------------------------------
    |
    | `migrate:fresh`, `migrate:reset`, `migrate:refresh` and `db:wipe` drop every
    | table. That is exactly what you want against a throwaway SQLite file, and
    | exactly what destroys a morning's work against the development MySQL
    | server. Stockly refuses the second case unless you set this to true
    | deliberately, so the reset has to be a decision rather than an accident.
    |
    | Set STOCKLY_ALLOW_DB_RESET=1 for the one command you actually mean.
    |
    */

    'allow_db_reset' => (bool) env('STOCKLY_ALLOW_DB_RESET', false),

    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    |
    | A shared ledger with no self-service signup. Accounts are created with
    | `php artisan stockly:user` instead.
    |
    */

    'registration' => false,

];
