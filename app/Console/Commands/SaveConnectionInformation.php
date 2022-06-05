<?php

namespace App\Console\Commands;

use App\Http\Libraries\Log;
use Illuminate\Console\Command;

class SaveConnectionInformation extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'connection-info:save {ipAddress} {--userId=} {--isMalicious=} {--isLoggingError=} {--isLimitExceeded=} {--isCrawler=} {errorType} {errorThrower} {errorFile} {errorMethod} {errorLine} {errorMessage} {isDbConnectionError}';

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
        $isLoggingError = $this->option('isLoggingError');
        $isLimitExceeded = $this->option('isLimitExceeded');
        $isCrawler = $this->option('isCrawler');
        $errorType = $this->argument('errorType');
        $errorThrower = $this->argument('errorThrower');
        $errorFile = $this->argument('errorFile');
        $errorMethod = $this->argument('errorMethod');
        $errorLine = $this->argument('errorLine');
        $errorMessage = $this->argument('errorMessage');
        $isDbConnectionError = $this->argument('isDbConnectionError');

        Log::prepareConnection($ipAddress, $userId, $isMalicious, $isLoggingError, $isLimitExceeded, $isCrawler, $errorType, $errorThrower, $errorFile, $errorMethod, $errorLine, $errorMessage, $isDbConnectionError);

        return 0;
    }
}
