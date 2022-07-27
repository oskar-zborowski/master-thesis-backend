<?php

namespace App\Console\Commands;

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Models\Config;
use Illuminate\Console\Command;

class CheckUtcTime extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'utc-time:check';

    /**
     * The console command description.
     */
    protected $description = 'Check the UTC time';

    /**
     * Execute the console command.
     */
    public function handle() {

        $worldTimeApi = json_decode(file_get_contents('http://worldtimeapi.org/api/ip'));

        if ($worldTimeApi !== null && $worldTimeApi->utc_offset !== null) {

            /** @var Config $config */
            $config = Config::where('id', 1)->first();
            $config->utc_time = $worldTimeApi->utc_offset;
            $config->save();

        } else {
            throw new ApiException(
                DefaultErrorCode::INTERNAL_SERVER_ERROR(false, true),
                __('validation.custom.external-api-error', ['api' => 'worldTimeApi']),
                __FUNCTION__,
                false
            );
        }
    }
}
