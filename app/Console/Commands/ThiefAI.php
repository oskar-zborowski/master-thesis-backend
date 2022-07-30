<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ThiefAi extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'thief-ai:start {roomId}';

    /**
     * The console command description.
     */
    protected $description = 'Start the Thief AI';

    /**
     * Execute the console command.
     */
    public function handle() {

        $roomId = $this->argument('roomId');

        do {
            sleep(env('BOT_REFRESH'));
        } while (false);
    }
}
