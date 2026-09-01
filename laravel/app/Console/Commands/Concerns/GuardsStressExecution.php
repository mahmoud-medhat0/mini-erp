<?php

namespace App\Console\Commands\Concerns;

trait GuardsStressExecution
{
    protected function refusesProductionStressRun(): bool
    {
        if (! app()->environment('production')) {
            return false;
        }

        $this->error('Stress commands are disabled in production to protect operational data.');

        return true;
    }

    protected function reportStressRunTag(string|int $runTag): void
    {
        $this->line("Stress fixture run tag: {$runTag}");
    }
}
