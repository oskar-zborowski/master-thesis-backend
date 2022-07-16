<?php

namespace App\Console\Commands;

use App\Models\Connection;
use Illuminate\Console\Command;

class RemoveExceededLimit extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'exceeded-limit:remove';

    /**
     * The console command description.
     */
    protected $description = 'Remove the exceeded limit';

    /**
     * Execute the console command.
     */
    public function handle() {

        /** @var \App\Models\Connection[] $connections */
        $connections = Connection::where('limit_exceeded_request_counter', '<', env('LIMIT_EXCEEDED_BLOCKING_TRESHOLD'))
            ->where('malicious_request_counter', '<', env('MALICIOUS_BLOCKING_TRESHOLD'))->get();

        foreach ($connections as $connection) {
            if ($connection->limit_exceeded_request_counter > env('LIMIT_EXCEEDED_DAILY_CLEANSING')) {
                $connection->limit_exceeded_request_counter = $connection->limit_exceeded_request_counter - env('LIMIT_EXCEEDED_DAILY_CLEANSING');
                $connection->save();
            } else if ($connection->limit_exceeded_request_counter > 0) {
                $connection->limit_exceeded_request_counter = 0;
                $connection->save();
            }
        }
    }
}
