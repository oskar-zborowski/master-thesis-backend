<?php

namespace App\Console\Commands;

use App\Http\Libraries\Log;
use Illuminate\Console\Command;

class SaveConnectionInformation extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'connection-info:save {ipAddress} {--userId=} {--isMalicious=} {--logError=} {errorType} {errorThrower} {errorFile} {errorMethod} {errorLine} {errorMessage} {dbConnectionError}';

    /**
     * The console command description.
     */
    protected $description = 'Save the connection information';

    /**
     * Execute the console command.
     */
    public function handle() {

        $ipAddress = $this->argument('ipAddress');
        $userId = $this->option('userId');
        $isMalicious = $this->option('isMalicious');
        $logError = $this->option('logError');
        $errorType = $this->argument('errorType');
        $errorThrower = $this->argument('errorThrower');
        $errorFile = $this->argument('errorFile');
        $errorMethod = $this->argument('errorMethod');
        $errorLine = $this->argument('errorLine');
        $errorMessage = $this->argument('errorMessage');
        $dbConnectionError = $this->argument('dbConnectionError');

        Log::prepareConnection($ipAddress, $userId, $isMalicious, $logError, $errorType, $errorThrower, $errorFile, $errorMethod, $errorLine, $errorMessage, $dbConnectionError);

        return 0;
    }
}
