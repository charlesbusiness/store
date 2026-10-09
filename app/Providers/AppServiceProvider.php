<?php

namespace App\Providers;

use App\Support\DatabaseResetGuard;
use App\Support\ValueFormatter;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
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
        $this->registerBladeDirectives();
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

    /**
     * Formatting directives, so views never contain arithmetic or number
     * formatting. All of them delegate to ValueFormatter, which is the trait's
     * static bridge.
     */
    private function registerBladeDirectives(): void
    {
        $directives = [
            'money' => [ValueFormatter::class, 'money'],
            'qty' => [ValueFormatter::class, 'qty'],
            'plain' => [ValueFormatter::class, 'plain'],
            'unit' => [ValueFormatter::class, 'unit'],
            'displayUnit' => [ValueFormatter::class, 'displayUnit'],
            'percent' => [ValueFormatter::class, 'percent'],
            // Not named "input": that would shadow Alpine's @input listener.
            'num' => [ValueFormatter::class, 'inputValue'],
        ];

        foreach ($directives as $name => [$class, $method]) {
            Blade::directive($name, function (string $expression) use ($class, $method) {
                return "<?php echo {$class}::{$method}({$expression}); ?>";
            });
        }
    }
}
