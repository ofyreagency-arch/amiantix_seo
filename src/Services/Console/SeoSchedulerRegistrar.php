<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Console;

use Illuminate\Console\Scheduling\Schedule;

class SeoSchedulerRegistrar
{
    public function register(Schedule $schedule): void
    {
        if (! config('seo-engine.scheduler.enabled', true)) {
            return;
        }

        foreach (config('seo-engine.scheduler.commands', []) as $definition) {
            $event = $schedule->command((string) ($definition['command'] ?? ''));
            $frequency = (string) ($definition['frequency'] ?? 'dailyAt');
            $argument = $definition['argument'] ?? null;

            if ($argument === null) {
                $event->{$frequency}();
            } elseif (is_array($argument)) {
                $event->{$frequency}(...$argument);
            } else {
                $event->{$frequency}($argument);
            }

            if (($definition['withoutOverlapping'] ?? true) === true) {
                $event->withoutOverlapping();
            }
        }
    }
}
