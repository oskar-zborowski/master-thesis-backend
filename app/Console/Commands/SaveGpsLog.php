<?php

namespace App\Console\Commands;

use App\Http\Libraries\FieldConversion;
use App\Models\GpsLog;
use Illuminate\Console\Command;
use maxh\Nominatim\Nominatim;

class SaveGpsLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gps-log:save {userId} {latitude} {longitude}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Save the gps log';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() {

        $userId = $this->argument('userId');
        $latitude = $this->argument('latitude');
        $longitude = $this->argument('longitude');

        $url = 'https://nominatim.openstreetmap.org';
        $nominatim = new Nominatim($url);

        $reverse = $nominatim->newReverse()->latlon($latitude, $longitude);
        $result = $nominatim->find($reverse)['address'];

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

        return 0;
    }
}
