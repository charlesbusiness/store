<?php

namespace App\Providers;

use App\Support\DatabaseResetGuard;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->guardDestructiveCommands();

        // Fail loudly in development rather than silently serving N+1 queries.
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    /**
     * Refuse to drop tables on a server database.
     *
     * This aborts the command before it runs, so a mistyped `migrate:fresh` cannot
     * reach the development MySQL data. See DatabaseResetGuard for the reasoning
     * and for the deliberate override.
     */
    private function guardDestructiveCommands(): void
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if ($event->command !== null) {
                DatabaseResetGuard::assertAllowed($event->command);
            }
        });
    }
}
