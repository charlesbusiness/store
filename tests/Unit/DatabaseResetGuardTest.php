<?php

namespace Tests\Unit;

use App\Support\DatabaseResetGuard;
use RuntimeException;
use Tests\TestCase;

/**
 * The rule that keeps `migrate:fresh` away from a developer's real data.
 *
 * Pure logic on purpose: this never opens a connection, so the test itself cannot
 * be the thing that damages a database.
 */
class DatabaseResetGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Off unless a test turns it on, so the suite cannot depend on an override
        // someone left lying around in their environment.
        config(['stockly.allow_db_reset' => false]);
    }

    public function test_it_targets_the_table_dropping_commands_and_nothing_else(): void
    {
        foreach (['migrate:fresh', 'migrate:reset', 'migrate:refresh', 'db:wipe'] as $command) {
            $this->assertTrue(DatabaseResetGuard::isDestructive($command), $command);
            $this->assertTrue(DatabaseResetGuard::blocks($command, 'mysql'), $command);
        }

        // Seeding upserts, and the rest build or read.
        foreach (['db:seed', 'migrate', 'migrate:status', 'test', 'stockly:user'] as $command) {
            $this->assertFalse(DatabaseResetGuard::isDestructive($command), $command);
            $this->assertFalse(DatabaseResetGuard::blocks($command, 'mysql'), $command);
        }
    }

    public function test_only_a_throwaway_file_counts_as_safe_to_destroy(): void
    {
        // This is how the test suite and the smoke run work, so it must never need
        // the override set by hand.
        $this->assertFalse(DatabaseResetGuard::isSharedDatabase('sqlite'));
        $this->assertFalse(DatabaseResetGuard::blocks('migrate:fresh', 'sqlite'));
        DatabaseResetGuard::assertAllowed('migrate:fresh', 'sqlite');

        foreach (['mysql', 'mariadb', 'pgsql', 'sqlsrv'] as $connection) {
            $this->assertTrue(DatabaseResetGuard::isSharedDatabase($connection), $connection);
        }
    }

    public function test_a_blocked_reset_throws_and_the_override_opens_it(): void
    {
        $this->expectException(RuntimeException::class);

        try {
            DatabaseResetGuard::assertAllowed('migrate:fresh', 'mysql');
        } catch (RuntimeException $e) {
            // The message is the only thing standing between someone and a wiped
            // database, so it has to name the override and the safe alternative.
            $this->assertStringContainsString('STOCKLY_ALLOW_DB_RESET=1', $e->getMessage());
            $this->assertStringContainsString('mysql', $e->getMessage());
            $this->assertStringContainsString('DB_CONNECTION=sqlite', $e->getMessage());

            // And the override the message points at has to be the thing that works.
            config(['stockly.allow_db_reset' => true]);
            $this->assertFalse(DatabaseResetGuard::blocks('migrate:fresh', 'mysql'));

            throw $e;
        }
    }
}
