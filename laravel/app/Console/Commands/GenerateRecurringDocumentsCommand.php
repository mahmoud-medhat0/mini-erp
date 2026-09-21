<?php

namespace App\Console\Commands;

use App\Application\Recurring\RecurringTemplateService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateRecurringDocumentsCommand extends Command
{
    protected $signature = 'recurring:generate {--date= : Generate as of this date (Y-m-d) instead of today}';

    protected $description = 'Generate draft documents (expenses in this release) for every recurring template due on or before the given date';

    public function handle(RecurringTemplateService $service): int
    {
        $dateOption = $this->option('date');
        $asOf = is_string($dateOption) && $dateOption !== '' ? Carbon::parse($dateOption) : null;

        $result = $service->generateDue($asOf);

        $this->info("Recurring generation complete: {$result['generated']} document(s) generated, {$result['paused']} template(s) paused due to errors.");

        return self::SUCCESS;
    }
}
