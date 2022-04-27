<?php

namespace App\Http\Libraries;

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Mail\MaliciousnessNotification;
use App\Models\Config;
use App\Models\Connection;
use App\Models\IpAddress;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log as FacadesLog;
use Illuminate\Support\Facades\Mail;
use maxh\Nominatim\Exceptions\NominatimException;
use maxh\Nominatim\Nominatim;

/**
 * Klasa przetwarzająca logowane dane do umieszczenia w bazie danych
 */
class Log
{
    public static function prepareConnection(string $ipAddress, ?int $userId, ?bool $isMalicious, ?bool $logError, ?string $errorType, ?string $errorThrower, string $errorDescription, bool $dbConnectionError = false, bool $saveLog = true, bool $sendMail = true, bool $checkIp = true) {

        $ipAddress = '83.8.175.174'; // TODO Usunąć przy wdrożeniu na serwer

        if (!$dbConnectionError) {

            $encryptedIpAddress = Encrypter::encrypt($ipAddress, 45, false);
            $aesDecrypt = Encrypter::prepareAesDecrypt('ip_address', $encryptedIpAddress);

            try {
                /** @var IpAddress $ipAddressEntity */
                $ipAddressEntity = IpAddress::whereRaw($aesDecrypt)->first();
            } catch (QueryException $e) {
                $errorThrower = get_class($e);
                $errorMessage = $e->getMessage();
                self::prepareConnection($ipAddress, $userId, $isMalicious, $logError, $errorType, $errorThrower, $errorDescription, true, $saveLog, $sendMail, $checkIp);
                self::prepareConnection($ipAddress, $userId, false, true, 'INTERNAL SERVER ERROR', $errorThrower, $errorMessage, true, $saveLog, $sendMail, $checkIp);
                die;
            }

            if (!$ipAddressEntity) {

                if ($checkIp) {

                    do {
                        sleep(env('IP_API_CONST_PAUSE'));
                        $config = Config::where('id', 1)->first();
                        $ipApiIsBusy = $config->ip_api_is_busy || $config->ip_api_last_used_at && Validation::timeComparison($config->ip_api_last_used_at, env('IP_API_CONST_PAUSE'), '<', 'seconds');
                    } while ($ipApiIsBusy);

                    $config->ip_api_is_busy = true;
                    $config->save();

                    $ipApiErrorCounter = 0;

                    do {

                        $ipApiError = false;

                        try {
                            $result = file_get_contents("http://ip-api.com/json/$ipAddress?fields=status,message,country,regionName,city,isp,org,mobile");
                            $result = json_decode($result, true);
                        } catch (Exception $e) {

                            $errorThrower = get_class($e);
                            $errorMessage = $e->getMessage();
                            $ipApiErrorCounter++;

                            if ($ipApiErrorCounter < env('IP_API_MAX_ATTEMPTS')) {
                                $ipApiError = true;
                                sleep(($ipApiErrorCounter * env('IP_API_VAR_PAUSE')) + env('IP_API_CONST_PAUSE'));
                            }
                        }

                        if (!$ipApiError && $ipApiErrorCounter < env('IP_API_MAX_ATTEMPTS')) {

                            if (!isset($result['status']) || $result['status'] != 'success') {

                                if (!isset($errorMessage)) {

                                    if (isset($result['message']) && is_string($result['message']) && strlen(trim($result['message'])) > 0) {
                                        $errorMessage = $result['message'];
                                    } else {
                                        $errorMessage = '';
                                    }
                                }

                                $ipApiErrorCounter++;

                                if ($ipApiErrorCounter < env('IP_API_MAX_ATTEMPTS')) {
                                    $ipApiError = true;
                                    sleep(($ipApiErrorCounter * env('IP_API_VAR_PAUSE')) + env('IP_API_CONST_PAUSE'));
                                }
                            }
                        }

                    } while ($ipApiError);

                    $config->ip_api_is_busy = false;
                    $config->ip_api_last_used_at = now();
                    $config->save();

                    if ($ipApiErrorCounter) {
                        $errorMessage = "Failed to get data from ip-api.com ($ipApiErrorCounter times)\n$errorMessage";
                        self::prepareConnection($ipAddress, $userId, false, true, 'INTERNAL SERVER ERROR', $errorThrower, $errorMessage, $dbConnectionError, $saveLog, $sendMail, false);
                    }
                }

                $ipAddressEntity = new IpAddress;
                $ipAddressEntity->ip_address = $ipAddress;

                if (isset($result['org']) && is_string($result['org']) && strlen(trim($result['org'])) > 0) {
                    $ipAddressEntity->provider = $result['org'];
                } else if (isset($result['isp']) && is_string($result['isp']) && strlen(trim($result['isp'])) > 0) {
                    $ipAddressEntity->provider = $result['isp'];
                }

                if (isset($result['city']) && is_string($result['city']) && strlen(trim($result['city'])) > 0) {
                    $ipAddressEntity->city = $result['city'];
                }

                if (isset($result['regionName']) && is_string($result['regionName']) && strlen(trim($result['regionName'])) > 0) {
                    $ipAddressEntity->voivodeship = $result['regionName'];
                }

                if (isset($result['country']) && is_string($result['country']) && strlen(trim($result['country'])) > 0) {
                    $ipAddressEntity->country = $result['country'];
                }

                if (isset($result['mobile']) && (is_bool($result['mobile']) || is_int($result['mobile']))) {
                    $ipAddressEntity->is_mobile = $result['mobile'];
                }

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

                if (!isset($isMalicious)) {
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

            if ($isMalicious) {

                if ($connection->malicious_request_counter == 1) {
                    $status = 1;
                } else if ($connection->malicious_request_counter == 2) {
                    $status = 2;
                } else if ($connection->malicious_request_counter == 3) {

                    $ipAddressEntity->blocked_at = now();
                    $ipAddressEntity->save();

                    if ($userId) {
                        /** @var \App\Models\User $user */
                        $user = $connection->user()->first();
                        $user->blocked_at = now();
                        $user->save();
                    }

                    $status = 3;

                } else if ($connection->malicious_request_counter == 50) {
                    $status = 4;
                }

            } else if ($logError) {
                $status = 0;
            }

        } else {

            if ($isMalicious) {
                $status = -1;
            } else if ($logError) {
                $status = 0;
            }

            $connection = null;
        }

        if (isset($status)) {

            if ($saveLog) {

                try {

                    $log = Log::prepareMessage('log', $connection, $status, $errorType, $errorThrower, $errorDescription);
                    $log .= "\n\n------------------------------------------------------------------------------------------------------\n";

                    if ($errorType == 'INTERNAL SERVER ERROR') {
                        FacadesLog::error($log);
                    } else {
                        FacadesLog::alert($log);
                    }

                } catch (Exception $e) {
                    $errorThrower = get_class($e);
                    $saveLogError = true;
                    $errorMessage = "Failed to save the log\n{$e->getMessage()}";
                    self::prepareConnection($ipAddress, $userId, false, true, 'INTERNAL SERVER ERROR', $errorThrower, $errorMessage, $dbConnectionError, false, $sendMail, $checkIp);
                }
            }

            if ($sendMail) {

                if (!$dbConnectionError) {

                    do {
                        sleep(env('MAIL_CONST_PAUSE'));
                        $config = Config::where('id', 1)->first();
                        $mailIsBusy = $config->mail_is_busy || $config->mail_last_used_at && Validation::timeComparison($config->mail_last_used_at, env('MAIL_CONST_PAUSE'), '<', 'seconds');
                    } while ($mailIsBusy);

                    $config->mail_is_busy = true;
                    $config->save();
                }

                $mailErrorCounter = 0;

                do {

                    $mailError = false;

                    try {
                        Mail::send(new MaliciousnessNotification($connection, $status, $errorType, $errorThrower, $errorDescription));
                    } catch (Exception $e) {

                        $errorThrower = get_class($e);
                        $errorMessage = $e->getMessage();
                        $mailErrorCounter++;

                        if ($mailErrorCounter < env('MAIL_MAX_ATTEMPTS')) {
                            $mailError = true;
                            sleep(($mailErrorCounter * env('MAIL_VAR_PAUSE')) + env('MAIL_CONST_PAUSE'));
                        }
                    }

                } while ($mailError);

                if (!$dbConnectionError) {
                    $config->mail_is_busy = false;
                    $config->mail_last_used_at = now();
                    $config->save();
                }

                if ($mailErrorCounter) {
                    $errorMessage = "Failed to send the email ($mailErrorCounter times)\n$errorMessage";
                    self::prepareConnection($ipAddress, $userId, false, true, 'INTERNAL SERVER ERROR', $errorThrower, $errorMessage, $dbConnectionError, !isset($saveLogError) && $saveLog, false, $checkIp);
                }
            }
        }
    }

    public static function prepareMessage(string $type, ?Connection $connection, int $status, string $errorType, string $errorThrower, string $errorDescription) {

        if ($type == 'mail') {
            $mailSubject = 'Wykryto złośliwe żądanie';
            $errorDescription = str_replace(["\n", '    '], ['<br>', '&emsp;&nbsp;'], $errorDescription);
            $enter = '<br>';
            $tab = '&emsp;';
        } else {
            $enter = '';
            $tab = '';
        }

        if ($connection) {

            /** @var \App\Models\User $user */
            $user = $connection->user;

            /** @var \App\Models\IpAddress $ipAddress */
            $ipAddress = $connection->ipAddress;

        } else {
            $user = null;
            $ipAddress = null;
        }

        if ($status == -1) {
            $message = 'Wykryto próbę wysłania złośliwego żądania!';
        } else if ($status == 1) {
            $message = 'Wykryto pierwszą próbę wysłania złośliwego żądania!';
        } else if ($status == 2) {
            $message = 'Wykryto kolejną próbę wysłania złośliwego żądania!';
        } else if ($status == 3) {

            $message = 'Zablokowano adres IP przychodzącego żądania';

            if ($user) {
                $message .= ' oraz konto użytkownika';
            }

            $message .= '!';

        } else if ($status == 4) {
            $message = 'Wymagana jest permanentna blokada adresu IP przychodzącego żądania!';
        } else {
            $mailSubject = 'Wystąpił nieoczekiwany błąd';
            $message = 'Wystąpił nieoczekiwany błąd!';
        }

        $message .= "$enter$enter

Informacje:$enter$tab
    Typ: $errorType$enter$tab
    Zgłaszający: $errorThrower$enter$tab
    Opis: $errorDescription";

        if ($connection) {
            $successfulRequestCounter = (int) $connection->successful_request_counter;
            $failedRequestCounter = (int) $connection->failed_request_counter;
            $maliciousRequestCounter = (int) $connection->malicious_request_counter;
        }

        if ($ipAddress) {

            $ipAddressProvider = $ipAddress->provider !== null && strlen(trim($ipAddress->provider)) > 0 ? $ipAddress->provider : 'brak';
            $ipAddressCity = $ipAddress->city !== null && strlen(trim($ipAddress->city)) > 0 ? $ipAddress->city : 'brak';
            $ipAddressVoivodeship = $ipAddress->voivodeship !== null && strlen(trim($ipAddress->voivodeship)) > 0 ? $ipAddress->voivodeship : 'brak';
            $ipAddressCountry = $ipAddress->country !== null && strlen(trim($ipAddress->country)) > 0 ? $ipAddress->country : 'brak';

            if ($ipAddress->is_mobile !== null) {

                if ($ipAddress->is_mobile) {
                    $ipAddressIsMobile = 'Tak';
                } else {
                    $ipAddressIsMobile = 'Nie';
                }

            } else {
                $ipAddressIsMobile = 'brak';
            }

            $ipAddressBlockedAt = $ipAddress->blocked_at ? $ipAddress->blocked_at : 'brak';
        }

        if ($connection) {

            $message .= "$enter$enter

Połączenie:$enter$tab
    ID: $connection->id$enter$tab
    Pomyślnych żądań: $successfulRequestCounter$enter$tab
    Błędnych żądań: $failedRequestCounter$enter$tab
    Złośliwych żądań: $maliciousRequestCounter$enter$tab
    Data utworzenia: $connection->created_at$enter$enter

Adres IP:$enter$tab
    ID: $ipAddress->id$enter$tab
    Adres IP: $ipAddress->ip_address$enter$tab
    Dostawca: $ipAddressProvider$enter$tab
    Miasto: $ipAddressCity$enter$tab
    Województwo: $ipAddressVoivodeship$enter$tab
    Kraj: $ipAddressCountry$enter$tab
    Internet mobilny: $ipAddressIsMobile$enter$tab
    Data utworzenia: $ipAddress->created_at$enter$tab
    Data blokady: $ipAddressBlockedAt";

        }

        if ($user) {

            $userName = $user->name !== null && strlen(trim($user->name)) > 0 ? $user->name : 'brak';

            if ($user->producer !== null && strlen(trim($user->producer)) > 0 && $user->model !== null && strlen(trim($user->model)) > 0) {
                $userTelephone = "$user->producer $user->model";
            } else if ($user->producer !== null && strlen(trim($user->producer)) > 0) {
                $userTelephone = "$user->producer";
            } else if ($user->model !== null && strlen(trim($user->model)) > 0) {
                $userTelephone = "$user->model";
            } else {
                $userTelephone = 'brak';
            }

            if ($user->os_name !== null && strlen(trim($user->os_name)) > 0 && $user->os_version !== null && strlen(trim($user->os_version)) > 0) {
                $userOS = "$user->os_name $user->os_version";
            } else if ($user->os_name !== null && strlen(trim($user->os_name)) > 0) {
                $userOS = "$user->os_name";
            } else if ($user->os_version !== null && strlen(trim($user->os_version)) > 0) {
                $userOS = "$user->os_version";
            } else {
                $userOS = 'brak';
            }

            $userBlockedAt = $user->blocked_at ? $user->blocked_at : 'brak';

            $message .= "$enter$enter

Użytkownik:$enter$tab
    ID: $user->id$enter$tab
    Nazwa: $userName$enter$tab
    Model telefonu: $userTelephone$enter$tab
    System operacyjny: $userOS$enter$tab
    Wersja aplikacji: $user->app_version$enter$tab
    Data utworzenia: $user->created_at$enter$tab
    Data blokady: $userBlockedAt";

            /** @var \App\Models\GpsLog $gpsLog */
            $gpsLog = $user->gpsLogs()->orderBy('id', 'desc')->first();

            if ($gpsLog) {

                $gpsLocation = str_replace(':', ', ', $gpsLog->gps_location);

                if ($gpsLog->street !== null && strlen(trim($gpsLog->street)) > 0 && $gpsLog->house_number !== null && strlen(trim($gpsLog->house_number)) > 0) {
                    $gpsLogStreet = "$gpsLog->street $gpsLog->house_number";
                } else if ($gpsLog->street !== null && strlen(trim($gpsLog->street)) > 0) {
                    $gpsLogStreet = "$gpsLog->street";
                } else {
                    $gpsLogStreet = 'brak';
                }

                if ($gpsLogStreet == 'brak' && $gpsLog->housing_estate !== null && strlen(trim($gpsLog->housing_estate)) > 0 && $gpsLog->house_number !== null && strlen(trim($gpsLog->house_number)) > 0) {
                    $gpsLogHousingEstate = "$gpsLog->housing_estate $gpsLog->house_number";
                } else if ($gpsLog->housing_estate !== null && strlen(trim($gpsLog->housing_estate)) > 0) {
                    $gpsLogHousingEstate = "$gpsLog->housing_estate";
                } else {
                    $gpsLogHousingEstate = 'brak';
                }

                if ($gpsLog->district !== null && strlen(trim($gpsLog->district)) > 0) {
                    $gpsLogDistrict = "$gpsLog->district";
                } else {
                    $gpsLogDistrict = 'brak';
                }

                if ($gpsLog->city !== null && strlen(trim($gpsLog->city)) > 0) {
                    $gpsLogCity = "$gpsLog->city";
                } else {
                    $gpsLogCity = 'brak';
                }

                if ($gpsLog->voivodeship !== null && strlen(trim($gpsLog->voivodeship)) > 0) {
                    $gpsLogVoivodeship = "$gpsLog->voivodeship";
                    $gpsLogVoivodeship = FieldConversion::stringToUppercase($gpsLogVoivodeship, true);
                } else {
                    $gpsLogVoivodeship = 'brak';
                }

                if ($gpsLog->country !== null && strlen(trim($gpsLog->country)) > 0) {
                    $gpsLogCountry = "$gpsLog->country";
                } else {
                    $gpsLogCountry = 'brak';
                }

                $message .= "$enter$enter

Lokalizacja:$enter$tab
    ID: $gpsLog->id$enter$tab
    Współrzędne geograficzne: $gpsLocation$enter$tab
    Ulica: $gpsLogStreet$enter$tab
    Osiedle: $gpsLogHousingEstate$enter$tab
    Dzielnica: $gpsLogDistrict$enter$tab
    Miasto: $gpsLogCity$enter$tab
    Województwo: $gpsLogVoivodeship$enter$tab
    Kraj: $gpsLogCountry$enter$tab
    Data utworzenia: $gpsLog->created_at";

            }
        }

        if ($type == 'mail') {
            $result = [$mailSubject, $message];
        } else if ($type == 'log') {
            $result = $message;
        } else {
            throw new ApiException(
                DefaultErrorCode::INTERNAL_SERVER_ERROR(false, true),
                __('validation.custom.invalid-log-type'),
                false
            );
        }

        return $result;
    }

    public static function getLocation(string $latitude, string $longitude, string $ipAddress, int $userId) {

        $url = 'https://nominatim.openstreetmap.org';
        $nominatim = new Nominatim($url);

        do {
            sleep(env('NOMINATIM_CONST_PAUSE'));
            $config = Config::where('id', 1)->first();
            $nominatimIsBusy = $config->nominatim_is_busy || $config->nominatim_last_used_at && Validation::timeComparison($config->nominatim_last_used_at, env('NOMINATIM_CONST_PAUSE'), '<', 'seconds');
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

                $errorThrower = get_class($e);
                $errorMessage = $e->getMessage();
                $nominatimErrorCounter++;

                if ($nominatimErrorCounter < env('NOMINATIM_MAX_ATTEMPTS')) {
                    $nominatimError = true;
                    sleep(($nominatimErrorCounter * env('NOMINATIM_VAR_PAUSE')) + env('NOMINATIM_CONST_PAUSE'));
                }
            }

        } while ($nominatimError);

        $config->nominatim_is_busy = false;
        $config->nominatim_last_used_at = now();
        $config->save();

        if ($nominatimErrorCounter) {
            $errorMessage = "Failed to get data from Nominati ($nominatimErrorCounter times)\n$errorMessage";
            self::prepareConnection($ipAddress, $userId, false, true, 'INTERNAL SERVER ERROR', $errorThrower, $errorMessage);
        }

        $location = null;

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

        return $location;
    }
}
