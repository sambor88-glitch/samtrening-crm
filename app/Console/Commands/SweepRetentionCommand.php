<?php

namespace App\Console\Commands;

use App\Domain\Privacy\Actions\SweepRetention;
use App\Support\Plural;
use Illuminate\Console\Command;

class SweepRetentionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'samtrening:retencja';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Anonimizuje archiwalne kartoteki starsze niż okres retencji studia';

    public function handle(SweepRetention $sweep): int
    {
        $swept = $sweep->handle();

        $this->info($swept === 0
            ? 'Nic do wyczyszczenia — żadna archiwalna kartoteka nie przekroczyła retencji.'
            : 'Zanonimizowano '.Plural::of($swept, 'kartotekę', 'kartoteki', 'kartotek').'.');

        return self::SUCCESS;
    }
}
