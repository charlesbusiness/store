<?php

namespace App\Support;

use RuntimeException;

/**
 * Stops a careless database reset from wiping a database that matters.
 *
 * Dropping every table is exactly what you want against a throwaway SQLite file,
 * which is how the test suite and the smoke run work. It is exactly what destroys
 * a morning's work against the MySQL server a developer is building on.
 *
 * So rather than trusting everyone to remember which is which, the destructive
 * commands refuse to run against a server database unless the override is set
 * deliberately. That keeps the reset available and makes it a decision.
 */
final class DatabaseResetGuard
{
    /**
     * Commands that drop or empty tables.
     *
     * `db:seed` is deliberately absent: it upserts and leaves existing rows alone.
     * `migrate:fresh --seed` is covered because it runs migrate:fresh.
     */
    private const DESTRUCTIVE = [
        'migrate:fresh',
        'migrate:reset',
        'migrate:refresh',
        'db:wipe',
    ];

    /**
     * Connections safe to destroy: the database is a file, or does not exist
     * between runs. Everything else is treated as somebody's real work.
     */
    private const THROWAWAY = [
        'sqlite',
    ];

    public static function isDestructive(string $command): bool
    {
        return in_array(strtolower($command), self::DESTRUCTIVE, true);
    }

    public static function isSharedDatabase(?string $connection): bool
    {
        return ! in_array(strtolower((string) $connection), self::THROWAWAY, true);
    }

    public static function isOverridden(): bool
    {
        return (bool) config('stockly.allow_db_reset', false);
    }

    /**
     * Should this command be refused right now?
     *
     * @param  string|null  $connection  defaults to the active connection
     */
    public static function blocks(string $command, ?string $connection = null): bool
    {
        $connection ??= (string) config('database.default');

        return self::isDestructive($command)
            && self::isSharedDatabase($connection)
            && ! self::isOverridden();
    }

    /**
     * Throw if the command is one we will not run, so it aborts before it has
     * touched a single table.
     */
    public static function assertAllowed(string $command, ?string $connection = null): void
    {
        $connection ??= (string) config('database.default');

        if (self::blocks($command, $connection)) {
            throw new RuntimeException(self::refusal($command, $connection));
        }
    }

    public static function refusal(string $command, string $connection): string
    {
        return <<<TEXT
        {$command} was not run: it would drop every table in the '{$connection}' database.

        That is safe for a throwaway SQLite file, but {$connection} looks like a real
        server database holding work. To wipe it anyway, say so on purpose:

            STOCKLY_ALLOW_DB_RESET=1 php artisan {$command} [options]

        To build a clean database without touching {$connection} at all, point the
        command somewhere disposable instead:

            DB_CONNECTION=sqlite DB_DATABASE=/tmp/stockly.sqlite php artisan migrate:fresh --seed
        TEXT;
    }
}
