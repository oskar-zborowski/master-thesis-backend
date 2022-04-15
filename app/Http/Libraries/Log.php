<?php

namespace App\Http\Libraries;

use App\Mail\MaliciousnessNotification;
use App\Models\Config;
use App\Models\Connection;
use App\Models\IpAddress;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log as FacadesLog;
use Illuminate\Support\Facades\Mail;
use maxh\Nominatim\Exceptions\NominatimException;
use maxh\Nominatim\Nominatim;

/**
 * Klasa przetwarzająca logowane dane do umieszczenia w bazie danych
 */
class Log
{
    public static function prepareConnection(string $ipAddress, ?int $userId, ?bool $isMalicious) {

        $encryptedIpAddress = Encrypter::encrypt($ipAddress, 45, false);
        $aesDecrypt = Encrypter::prepareAesDecrypt('ip_address', $encryptedIpAddress);

        /** @var IpAddress $ipAddressEntity */
        $ipAddressEntity = IpAddress::whereRaw($aesDecrypt)->first();

        if (!$ipAddressEntity) {
            $ipAddressEntity = new IpAddress;
            $ipAddressEntity->ip_address = $ipAddress;
            $ipAddressEntity->save();
        }

        if ($userId) {
            /** @var \App\Models\Connection $connection */
            $connection = $ipAddressEntity->connections()->where('user_id', $userId)->first();
        } else {
            /** @var \App\Models\Connection $connection */
            $connection = $ipAddressEntity->connections()->where('user_id', null)->first();
        }

        if (!$connection) {

            $connection = new Connection;

            if ($userId) {
                $connection->user_id = $userId;
            }

            $connection->ip_address_id = $ipAddressEntity->id;

            if ($isMalicious === null) {
                $connection->successful_request_counter = 1;
            } else if ($isMalicious) {
                $connection->malicious_request_counter = 1;
            } else {
                $connection->failed_request_counter = 1;
            }

        } else {

            if ($isMalicious === null) {
                $connection->successful_request_counter = $connection->successful_request_counter + 1;
            } else if ($isMalicious) {
                $connection->malicious_request_counter = $connection->malicious_request_counter + 1;
            } else {
                $connection->failed_request_counter = $connection->failed_request_counter + 1;
            }
        }

        $connection->save();

        return [$ipAddressEntity, $connection];
    }

    public static function getLocation(string $latitude, string $longitude, string $ipAddress, int $userId) {

        $url = 'https://nominatim.openstreetmap.org';
        $nominatim = new Nominatim($url);

        do {
            sleep(env('NOMINATIM_PAUSE'));
            $config = Config::where('id', 1)->first();
            $nominatimIsBusy = $config->nominatim_is_busy || $config->nominatim_last_used_at && Validation::timeComparison($config->nominatim_last_used_at, env('NOMINATIM_PAUSE'), '<', 'seconds');
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

                $errorDescription = $e->getMessage();
                $nominatimErrorCounter++;

                if ($nominatimErrorCounter <= 2) {
                    $nominatimError = true;
                    sleep(($nominatimErrorCounter * 2) + env('NOMINATIM_PAUSE'));
                }
            }

        } while ($nominatimError);

        $config->nominatim_is_busy = false;
        $config->nominatim_last_used_at = now();
        $config->save();

        $location = [];

        if (isset($result['house_number'])) {
            $location['house_number'] = FieldConversion::stringToUppercase($result['house_number']);
        }

        if (isset($result['road'])) {
            $location['street'] = FieldConversion::stringToUppercase($result['road'], true);
        }

        if (isset($result['neighbourhood'])) {
            $location['housing_estate'] = FieldConversion::stringToUppercase($result['neighbourhood'], true);
        }

        if (isset($result['suburb'])) {
            $location['district'] = FieldConversion::stringToUppercase($result['suburb'], true);
        } else if (isset($result['borough'])) {
            $location['district'] = FieldConversion::stringToUppercase($result['borough'], true);
        } else if (isset($result['hamlet'])) {
            $location['district'] = FieldConversion::stringToUppercase($result['hamlet'], true);
        }

        if (isset($result['city'])) {
            $location['city'] = FieldConversion::stringToUppercase($result['city'], true);
        } else if (isset($result['town'])) {
            $location['city'] = FieldConversion::stringToUppercase($result['town'], true);
        } else if (isset($result['residential'])) {
            $location['city'] = FieldConversion::stringToUppercase($result['residential'], true);
        } else if (isset($result['village'])) {
            $location['city'] = FieldConversion::stringToUppercase($result['village'], true);
        } else if (isset($result['county'])) {

            $city = FieldConversion::stringToLowercase($result['county']);

            if (!str_contains($city, 'powiat')) {
                $location['city'] = FieldConversion::stringToUppercase($result['county'], true);
            } else if (isset($result['municipality'])) {

                $commune = FieldConversion::stringToLowercase($result['municipality']);

                if (str_contains($commune, 'gmina')) {
                    $commune = str_replace('gmina ', '', $commune);
                    $location['city'] = FieldConversion::stringToUppercase($commune, true);
                } else {
                    $location['city'] = FieldConversion::stringToUppercase($result['municipality'], true);
                }
            }
        }

        if (isset($result['state'])) {
            $voivodeship = FieldConversion::stringToLowercase($result['state']);
            $location['voivodeship'] = str_replace('województwo ', '', $voivodeship);
        }

        if (isset($result['country'])) {
            $location['country'] = FieldConversion::stringToUppercase($result['country'], true);
        }

        if ($nominatimErrorCounter) {

            [$ipAddressEntity, $connection] = self::prepareConnection($ipAddress, $userId, false);

            $errorDescriptionLog = "Failed to get data from Nominati ($nominatimErrorCounter times)\n$errorDescription";
            $errorDescriptionMail = "Failed to get data from Nominati ($nominatimErrorCounter times)<br>$errorDescription";

            FacadesLog::alert($errorDescriptionLog);
            Mail::send(new MaliciousnessNotification($connection, 0, 'INTERNAL SERVER ERROR', $errorDescriptionMail));
        }

        return $location;
    }
}
