<?php

namespace App\Console\Commands;

use App\Http\Libraries\Log;
use App\Models\GpsLog;
use Illuminate\Console\Command;

class SaveGpsLog extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'gps-log:save {ipAddress} {userId} {gpsLocation}';

    /**
     * The console command description.
     */
    protected $description = 'Save the gps log';

    /**
     * Execute the console command.
     */
    public function handle() {

        $ipAddress = $this->argument('ipAddress');
        $userId = $this->argument('userId');
        $gpsLocation = $this->argument('gpsLocation');

        $location = Log::getLocation($gpsLocation, $ipAddress, $userId);

        $gpsLog = new GpsLog;
        $gpsLog->user_id = $userId;
        $gpsLog->gps_location = $gpsLocation;

        if (isset($location['house_number'])) {
            $gpsLog->house_number = $location['house_number'];
        }

        if (isset($location['street'])) {
            $gpsLog->street = $location['street'];
        }

        if (isset($location['housing_estate'])) {
            $gpsLog->housing_estate = $location['housing_estate'];
        }

        if (isset($location['district'])) {
            $gpsLog->district = $location['district'];
        }

        if (isset($location['city'])) {
            $gpsLog->city = $location['city'];
        }

        if (isset($location['voivodeship'])) {
            $gpsLog->voivodeship = $location['voivodeship'];
        }

        if (isset($location['country'])) {
            $gpsLog->country = $location['country'];
        }

        $gpsLog->save();

        return 0;
    }
}
