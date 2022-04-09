<?php

namespace App\Console\Commands;

use App\Http\Libraries\FieldConversion;
use App\Http\Libraries\Validation;
use App\Models\Config;
use App\Models\GpsLog;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use maxh\Nominatim\Exceptions\NominatimException;
use maxh\Nominatim\Nominatim;

class SaveGpsLog extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'gps-log:save {userId} {latitude} {longitude}';

    /**
     * The console command description.
     */
    protected $description = 'Save the gps log';

    /**
     * Execute the console command.
     */
    public function handle() {

        $userId = $this->argument('userId');
        $latitude = $this->argument('latitude');
        $longitude = $this->argument('longitude');

        $url = 'https://nominatim.openstreetmap.org';
        $nominatim = new Nominatim($url);

        do {
            sleep(env('NOMINATIM_PAUSE'));
            $config = Config::where('id', 1)->first();
            $nominatimIsBusy = $config->nominatim_is_busy || $config->nominatim_last_used_at !== null && Validation::timeComparison($config->nominatim_last_used_at, env('NOMINATIM_PAUSE'), '<', 'seconds');
        } while ($nominatimIsBusy);

        $config->nominatim_is_busy = true;
        $config->save();

        $nominatimErrorCounter = 0;

        do {

            $nominatimError = false;

            try {
                $reverse = $nominatim->newReverse()->latlon($latitude, $longitude);
                $result = $nominatim->find($reverse)['address'];
            } catch (NominatimException | GuzzleException $e) {

                $nominatimErrorCounter++;

                if ($nominatimErrorCounter <= 2) {
                    $nominatimError = true;
                    sleep(($nominatimErrorCounter * 2) + env('NOMINATIM_PAUSE'));
                } else {
                    $nominatimError = false;
                }
            }

        } while ($nominatimError);

        $config->nominatim_is_busy = false;
        $config->nominatim_last_used_at = now();
        $config->save();

        $gpsLog = new GpsLog;
        $gpsLog->user_id = $userId;
        $gpsLog->gps_location = "$latitude:$longitude";

        if (isset($result['house_number'])) {
            $gpsLog->house_number = FieldConversion::stringToUppercase($result['house_number']);
        }

        if (isset($result['road'])) {
            $gpsLog->street = FieldConversion::stringToUppercase($result['road'], true);
        }

        if (isset($result['neighbourhood'])) {
            $gpsLog->housing_estate = FieldConversion::stringToUppercase($result['neighbourhood'], true);
        }

        if (isset($result['suburb'])) {
            $gpsLog->district = FieldConversion::stringToUppercase($result['suburb'], true);
        } else if (isset($result['borough'])) {
            $gpsLog->district = FieldConversion::stringToUppercase($result['borough'], true);
        } else if (isset($result['hamlet'])) {
            $gpsLog->district = FieldConversion::stringToUppercase($result['hamlet'], true);
        }

        if (isset($result['city'])) {
            $gpsLog->city = FieldConversion::stringToUppercase($result['city'], true);
        } else if (isset($result['town'])) {
            $gpsLog->city = FieldConversion::stringToUppercase($result['town'], true);
        } else if (isset($result['residential'])) {
            $gpsLog->city = FieldConversion::stringToUppercase($result['residential'], true);
        } else if (isset($result['village'])) {
            $gpsLog->city = FieldConversion::stringToUppercase($result['village'], true);
        } else if (isset($result['county'])) {

            $city = FieldConversion::stringToLowercase($result['county']);

            if (!str_contains($city, 'powiat')) {
                $gpsLog->city = FieldConversion::stringToUppercase($result['county'], true);
            } else if (isset($result['municipality'])) {

                $commune = FieldConversion::stringToLowercase($result['municipality']);

                if (str_contains($commune, 'gmina')) {
                    $commune = str_replace('gmina ', '', $commune);
                    $gpsLog->city = FieldConversion::stringToUppercase($commune, true);
                } else {
                    $gpsLog->city = FieldConversion::stringToUppercase($result['municipality'], true);
                }
            }
        }

        if (isset($result['state'])) {
            $voivodeship = FieldConversion::stringToLowercase($result['state']);
            $gpsLog->voivodeship = str_replace('województwo ', '', $voivodeship);
        }

        if (isset($result['country'])) {
            $gpsLog->country = FieldConversion::stringToUppercase($result['country'], true);
        }

        $gpsLog->save();

        if ($nominatimErrorCounter) {
            $nominatimErrorCounter--;
            Log::alert("Failed to get data from Nominati ($nominatimErrorCounter times) for ID: $gpsLog->id");
        }

        return 0;
    }
}
