<?php

namespace App\Http\Libraries;

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Mail\MaliciousnessNotification;
use App\Models\Config;
use App\Models\Connection;
use App\Models\GpsLog;
use App\Models\IpAddress;
use App\Models\User;
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
    public static function prepareConnection(string $ipAddress, ?int $userId, ?bool $isMalicious, ?bool $logError, ?string $errorType, ?string $errorThrower, string $errorDescription, bool $dbConnectionError = false, bool $saveLog = true, bool $sendMail = true, bool $checkIp = true, bool $readLog = true) {

        if (!$dbConnectionError) {

            $encryptedIpAddress = Encrypter::encrypt($ipAddress, 45, false);
            $aesDecrypt = Encrypter::prepareAesDecrypt('ip_address', $encryptedIpAddress);

            try {
                /** @var IpAddress $ipAddressEntity */
                $ipAddressEntity = IpAddress::whereRaw($aesDecrypt)->first();
            } catch (QueryException $e) {
                $errorThrowerDb = get_class($e);
                $errorMessage = $e->getMessage();
                self::prepareConnection($ipAddress, $userId, $isMalicious, $logError, $errorType, $errorThrower, $errorDescription, true, $saveLog, $sendMail, $checkIp, $readLog);
                self::prepareConnection($ipAddress, $userId, false, true, 'INTERNAL SERVER ERROR', $errorThrowerDb, $errorMessage, true, $saveLog, $sendMail, $checkIp, $readLog);
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
                        self::prepareConnection($ipAddress, $userId, false, true, 'INTERNAL SERVER ERROR', $errorThrower, $errorMessage, $dbConnectionError, $saveLog, $sendMail, false, $readLog);
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
                        /** @var User $user */
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

            $errorNumber = 'brak';

            if ($readLog) {

                try {

                    $filepath = '../storage/logs/laravel.log';
                    $fp = fopen($filepath, 'r');
                    $filesize = filesize($filepath);

                    if ($filesize > 0) {
                        $logData = fread($fp, $filesize);
                    } else if ($filesize == 0) {
                        $errorNumber = 1;
                    }

                    fclose($fp);

                } catch (Exception $e) {
                    $errorThrower = get_class($e);
                    $errorMessage = $e->getMessage();
                    self::prepareConnection($ipAddress, $userId, false, true, 'INTERNAL SERVER ERROR', $errorThrower, $errorMessage, $dbConnectionError, $saveLog, $sendMail, $checkIp, false);
                }

                if (isset($logData)) {

                    $last = 0;

                    do {

                        $needle = "\nNr błędu: ";
                        $lastFoundErrorNumber = FieldConversion::findLastOccurrenceInString($logData, $needle, $last) + strlen($needle);
                        $currentErrorNumber = 0;

                        for ($i=$lastFoundErrorNumber; ord($logData[$i]) >= 48 && ord($logData[$i]) <= 57; $i++) {
                            $currentErrorNumber *= 10;
                            $currentErrorNumber += $logData[$i];
                        }

                        $last++;

                    } while ($lastFoundErrorNumber && !$currentErrorNumber);

                    $errorNumber = $currentErrorNumber + 1;
                }
            }

            if ($saveLog) {

                try {

                    $log = Log::prepareMessage('log', $connection, $status, $errorType, $errorThrower, $errorDescription, $errorNumber);
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
                    self::prepareConnection($ipAddress, $userId, false, true, 'INTERNAL SERVER ERROR', $errorThrower, $errorMessage, $dbConnectionError, false, $sendMail, $checkIp, $readLog);
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
                        Mail::send(new MaliciousnessNotification($connection, $status, $errorType, $errorThrower, $errorDescription, $errorNumber));
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
                    self::prepareConnection($ipAddress, $userId, false, true, 'INTERNAL SERVER ERROR', $errorThrower, $errorMessage, $dbConnectionError, !isset($saveLogError) && $saveLog, false, $checkIp, $readLog);
                }
            }
        }
    }

    public static function prepareMessage(string $type, ?Connection $connection, int $status, string $errorType, string $errorThrower, string $errorDescription, $errorNumber) {

        $values['errorType'] = $errorType;
        $values['errorThrower'] = $errorThrower;
        $values['errorDescription'] = $errorDescription;
        $values['errorNumber'] = $errorNumber;

        if ($type == 'mail') {
            $values['errorDescription'] = str_replace(["\n", '    '], ['<br>', '&emsp;&nbsp;'], $values['errorDescription']);
            $enter = '<br>';
            $tab = '&emsp;';
        } else if ($type == 'log') {
            $enter = '';
            $tab = '';
        } else {
            throw new ApiException(
                DefaultErrorCode::INTERNAL_SERVER_ERROR(false, true),
                __('validation.custom.invalid-log-type'),
                false
            );
        }

        if ($status == 0) {
            $mailSubject = 'Wystąpił nieoczekiwany błąd';
        } else {
            $mailSubject = 'Wykryto złośliwe żądanie';
        }

        if ($connection) {

            /** @var User $user */
            $user = $connection->user;

            /** @var \App\Models\IpAddress $ipAddress */
            $ipAddress = $connection->ipAddress;

            $connectionIds = [];
            $values['connectionId'] = '';
            $values['successfulRequestCounter'] = (int) $connection->successful_request_counter;
            $values['failedRequestCounter'] = (int) $connection->failed_request_counter;
            $values['maliciousRequestCounter'] = (int) $connection->malicious_request_counter;
            $successfulRequestCounterAll = 0;
            $failedRequestCounterAll = 0;
            $maliciousRequestCounterAll = 0;
            $values['connectionCreatedAt'] = null;

        } else {
            $user = null;
            $ipAddress = null;
        }

        if ($ipAddress) {

            $ipAddressIds = [];
            $values['ipAddressId'] = '';
            $values['ipAddressIpAddress'] = strlen(trim($ipAddress->ip_address)) > 0 ? $ipAddress->ip_address : 'brak';
            $values['ipAddressProvider'] = $ipAddress->provider !== null && strlen(trim($ipAddress->provider)) > 0 ? $ipAddress->provider : 'brak';
            $values['ipAddressCity'] = $ipAddress->city !== null && strlen(trim($ipAddress->city)) > 0 ? $ipAddress->city : 'brak';
            $values['ipAddressVoivodeship'] = $ipAddress->voivodeship !== null && strlen(trim($ipAddress->voivodeship)) > 0 ? $ipAddress->voivodeship : 'brak';
            $values['ipAddressCountry'] = $ipAddress->country !== null && strlen(trim($ipAddress->country)) > 0 ? $ipAddress->country : 'brak';

            if ($ipAddress->is_mobile !== null) {

                if ($ipAddress->is_mobile) {
                    $values['ipAddressIsMobile'] = 'Tak';
                } else {
                    $values['ipAddressIsMobile'] = 'Nie';
                }

            } else {
                $values['ipAddressIsMobile'] = 'brak';
            }

            $values['ipAddressCreatedAt'] = null;
            $values['ipAddressBlockedAt'] = 'brak';
            $blockedIpAddressesCounter = 0;
        }

        if ($user) {

            $userIds = [];
            $values['userId'] = '';
            $values['userCreatedAt'] = null;
            $values['userBlockedAt'] = 'brak';
            $blockedUsersCounter = 0;

            if ($user->uuid !== null && strlen(trim($user->uuid)) > 0) {

                $encryptedUserUuid = Encrypter::encrypt($user->uuid, 45, false);
                $aesDecrypt = Encrypter::prepareAesDecrypt('uuid', $encryptedUserUuid);

                /** @var User[] $users */
                $users = User::whereRaw($aesDecrypt)->get();

                /** @var User $u */
                foreach ($users as $u) {

                    /** @var Connection[] $connections */
                    $connections = $u->connections()->get();

                    /** @var Connection $c */
                    foreach ($connections as $c) {

                        $connectionIds[] = $c->id;

                        $successfulRequestCounterAll += $c->successful_request_counter;
                        $failedRequestCounterAll += $c->failed_request_counter;
                        $maliciousRequestCounterAll += $c->malicious_request_counter;

                        if (!$values['connectionCreatedAt']) {
                            $values['connectionCreatedAt'] = $c->created_at;
                        } else if ($values['connectionCreatedAt'] > $c->created_at) {
                            $values['connectionCreatedAt'] = $c->created_at;
                        }

                        $ip = $c->ipAddress;

                        if ($ip) {

                            if (!in_array($ip->id, $ipAddressIds)) {

                                $ipAddressIds[] = $ip->id;
    
                                if ($ip->blocked_at) {
    
                                    $blockedIpAddressesCounter++;
    
                                    if ($ipAddress->id == $ip->id) {
                                        $values['ipAddressBlockedAt'] = $ip->blocked_at;
                                    }
                                }
                            }

                            if (!$values['ipAddressCreatedAt']) {
                                $values['ipAddressCreatedAt'] = $ip->created_at;
                            } else if ($values['ipAddressCreatedAt'] > $ip->created_at) {
                                $values['ipAddressCreatedAt'] = $ip->created_at;
                            }
                        }
                    }

                    $userIds[] = $u->id;

                    if (!$values['userCreatedAt']) {
                        $values['userCreatedAt'] = $u->created_at;
                    } else if ($values['userCreatedAt'] > $u->created_at) {
                        $values['userCreatedAt'] = $u->created_at;
                    }

                    if ($u->blocked_at) {

                        $blockedUsersCounter++;

                        if ($user->id == $u->id) {
                            $values['userBlockedAt'] = $u->blocked_at;
                        }
                    }
                }

                asort($connectionIds);
                asort($ipAddressIds);
                asort($userIds);

                foreach ($connectionIds as $id) {
                    if ($connection && $id == $connection->id) {
                        $values['connectionId'] .= "($id), ";
                    } else {
                        $values['connectionId'] .= "$id, ";
                    }
                }

                foreach ($ipAddressIds as $id) {
                    if ($ipAddress && $id == $ipAddress->id) {
                        $values['ipAddressId'] .= "($id), ";
                    } else {
                        $values['ipAddressId'] .= "$id, ";
                    }
                }

                foreach ($userIds as $id) {
                    if ($id == $user->id) {
                        $values['userId'] .= "($id), ";
                    } else {
                        $values['userId'] .= "$id, ";
                    }
                }

                $values['connectionId'] = substr($values['connectionId'], 0, -2);
                $values['ipAddressId'] = substr($values['ipAddressId'], 0, -2);
                $values['userId'] = substr($values['userId'], 0, -2);

                if (strpos($values['connectionId'], ',') === false) {
                    $values['connectionId'] = str_replace(['(', ')'], ['', ''], $values['connectionId']);
                } else {

                    $values['successfulRequestCounter'] = "$successfulRequestCounterAll ({$values['successfulRequestCounter']})";
                    $values['failedRequestCounter'] = "$failedRequestCounterAll ({$values['failedRequestCounter']})";
                    $values['maliciousRequestCounter'] = "$maliciousRequestCounterAll ({$values['maliciousRequestCounter']})";

                    if ($values['connectionCreatedAt'] && $connection && $connection->created_at) {
                        $values['connectionCreatedAt'] = "{$values['connectionCreatedAt']} ($connection->created_at)";
                    } else if ($values['connectionCreatedAt']) {
                        $values['connectionCreatedAt'] = "{$values['connectionCreatedAt']} (brak)";
                    } else {
                        $values['connectionCreatedAt'] = 'brak';
                    }
                }

                if (strpos($values['ipAddressId'], ',') === false) {

                    $values['ipAddressId'] = str_replace(['(', ')'], ['', ''], $values['ipAddressId']);

                    if (!$values['ipAddressCreatedAt']) {
                        $values['ipAddressCreatedAt'] = 'brak';
                    }

                } else {

                    if ($values['ipAddressCreatedAt'] && $ipAddress && $ipAddress->created_at) {
                        $values['ipAddressCreatedAt'] = "{$values['ipAddressCreatedAt']} ($ipAddress->created_at)";
                    } else if ($values['ipAddressCreatedAt']) {
                        $values['ipAddressCreatedAt'] = "{$values['ipAddressCreatedAt']} (brak)";
                    } else {
                        $values['ipAddressCreatedAt'] = 'brak';
                    }
                    
                    $values['ipAddressBlockedAt'] = "{$values['ipAddressBlockedAt']} ($blockedIpAddressesCounter)";
                }

                if (strpos($values['userId'], ',') === false) {

                    $values['userId'] = str_replace(['(', ')'], ['', ''], $values['userId']);

                    if (!$values['userCreatedAt']) {
                        $values['userCreatedAt'] = 'brak';
                    }

                } else {

                    if ($values['userCreatedAt'] && $user->created_at) {
                        $values['userCreatedAt'] = "{$values['userCreatedAt']} ($user->created_at)";
                    } else if ($values['userCreatedAt']) {
                        $values['userCreatedAt'] = "{$values['userCreatedAt']} (brak)";
                    } else {
                        $values['userCreatedAt'] = 'brak';
                    }

                    $values['userBlockedAt'] = "{$values['userBlockedAt']} ($blockedUsersCounter)";
                }
            }

            $values['userName'] = $user->name !== null && strlen(trim($user->name)) > 0 ? $user->name : 'brak';

            if ($user->producer !== null && strlen(trim($user->producer)) > 0 && $user->model !== null && strlen(trim($user->model)) > 0) {
                $values['userTelephone'] = "$user->producer $user->model";
            } else if ($user->producer !== null && strlen(trim($user->producer)) > 0) {
                $values['userTelephone'] = $user->producer;
            } else if ($user->model !== null && strlen(trim($user->model)) > 0) {
                $values['userTelephone'] = $user->model;
            } else {
                $values['userTelephone'] = 'brak';
            }

            if ($user->os_name !== null && strlen(trim($user->os_name)) > 0 && $user->os_version !== null && strlen(trim($user->os_version)) > 0) {
                $values['userOS'] = "$user->os_name $user->os_version";
            } else if ($user->os_name !== null && strlen(trim($user->os_name)) > 0) {
                $values['userOS'] = $user->os_name;
            } else if ($user->os_version !== null && strlen(trim($user->os_version)) > 0) {
                $values['userOS'] = $user->os_version;
            } else {
                $values['userOS'] = 'brak';
            }

            $values['userAppVersion'] = strlen(trim($user->app_version)) > 0 ? $user->app_version : 'brak';

            /** @var GpsLog $gpsLog */
            $gpsLog = GpsLog::where('user_id', $userIds)->orderBy('id', 'desc')->first();

            if ($gpsLog) {

                $values['gpsLogId'] = $gpsLog->id;
                $values['gpsLogUserId'] = $gpsLog->user_id !== null ? $gpsLog->user_id : 'brak';
                $values['gpsLogLocation'] = strlen(trim($gpsLog->gps_location)) > 0 ? str_replace(':', ', ', $gpsLog->gps_location) : 'brak';

                if ($gpsLog->street !== null && strlen(trim($gpsLog->street)) > 0 && $gpsLog->house_number !== null && strlen(trim($gpsLog->house_number)) > 0) {
                    $values['gpsLogStreet'] = "$gpsLog->street $gpsLog->house_number";
                } else if ($gpsLog->street !== null && strlen(trim($gpsLog->street)) > 0) {
                    $values['gpsLogStreet'] = $gpsLog->street;
                } else {
                    $values['gpsLogStreet'] = 'brak';
                }

                if ($values['gpsLogStreet'] == 'brak' && $gpsLog->housing_estate !== null && strlen(trim($gpsLog->housing_estate)) > 0 && $gpsLog->house_number !== null && strlen(trim($gpsLog->house_number)) > 0) {
                    $values['gpsLogHousingEstate'] = "$gpsLog->housing_estate $gpsLog->house_number";
                } else if ($gpsLog->housing_estate !== null && strlen(trim($gpsLog->housing_estate)) > 0) {
                    $values['gpsLogHousingEstate'] = $gpsLog->housing_estate;
                } else {
                    $values['gpsLogHousingEstate'] = 'brak';
                }

                if ($gpsLog->district !== null && strlen(trim($gpsLog->district)) > 0) {
                    $values['gpsLogDistrict'] = $gpsLog->district;
                } else {
                    $values['gpsLogDistrict'] = 'brak';
                }

                if ($gpsLog->city !== null && strlen(trim($gpsLog->city)) > 0) {
                    $values['gpsLogCity'] = $gpsLog->city;
                } else {
                    $values['gpsLogCity'] = 'brak';
                }

                if ($gpsLog->voivodeship !== null && strlen(trim($gpsLog->voivodeship)) > 0) {
                    $values['gpsLogVoivodeship'] = FieldConversion::stringToUppercase($gpsLog->voivodeship, true);
                } else {
                    $values['gpsLogVoivodeship'] = 'brak';
                }

                if ($gpsLog->country !== null && strlen(trim($gpsLog->country)) > 0) {
                    $values['gpsLogCountry'] = $gpsLog->country;
                } else {
                    $values['gpsLogCountry'] = 'brak';
                }

                $values['gpsLogCreatedAt'] = $gpsLog->created_at !== null ? $gpsLog->created_at : 'brak';
            }
        }

        $message = self::getMessageTemplate($status, $values, $enter, $tab);

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

    private static function getMessageTemplate(int $status, array $values, string $enter, string $tab) {

        if ($status == -1) {
            $message = 'Wykryto próbę wysłania złośliwego żądania!';
        } else if ($status == 1) {
            $message = 'Wykryto pierwszą próbę wysłania złośliwego żądania!';
        } else if ($status == 2) {
            $message = 'Wykryto kolejną próbę wysłania złośliwego żądania!';
        } else if ($status == 3) {

            $message = 'Zablokowano adres IP przychodzącego żądania';

            if (isset($values['userId'])) {
                $message .= ' oraz konto użytkownika';
            }

            $message .= '!';

        } else if ($status == 4) {
            $message = 'Wymagana jest permanentna blokada adresu IP przychodzącego żądania!';
        } else {
            $message = 'Wystąpił nieoczekiwany błąd!';
        }

        $message .= "$enter$enter

Nr błędu: {$values['errorNumber']}$enter$enter

Informacje:$enter$tab
    Typ: {$values['errorType']}$enter$tab
    Zgłaszający: {$values['errorThrower']}$enter$tab
    Opis: {$values['errorDescription']}";

        if (isset($values['connectionId'])) {

            $message .= "$enter$enter

Połączenie:$enter$tab
    ID: {$values['connectionId']}$enter$tab
    Pomyślnych żądań: {$values['successfulRequestCounter']}$enter$tab
    Błędnych żądań: {$values['failedRequestCounter']}$enter$tab
    Złośliwych żądań: {$values['maliciousRequestCounter']}$enter$tab
    Data utworzenia: {$values['connectionCreatedAt']}";

        }

        if (isset($values['ipAddressId'])) {

            $message .= "$enter$enter

Adres IP:$enter$tab
    ID: {$values['ipAddressId']}$enter$tab
    Adres IP: {$values['ipAddressIpAddress']}$enter$tab
    Dostawca: {$values['ipAddressProvider']}$enter$tab
    Miasto: {$values['ipAddressCity']}$enter$tab
    Województwo: {$values['ipAddressVoivodeship']}$enter$tab
    Kraj: {$values['ipAddressCountry']}$enter$tab
    Internet mobilny: {$values['ipAddressIsMobile']}$enter$tab
    Data utworzenia: {$values['ipAddressCreatedAt']}$enter$tab
    Data blokady: {$values['ipAddressBlockedAt']}";

        }

        if (isset($values['userId'])) {

            $message .= "$enter$enter

Użytkownik:$enter$tab
    ID: {$values['userId']}$enter$tab
    Nazwa: {$values['userName']}$enter$tab
    Model telefonu: {$values['userTelephone']}$enter$tab
    System operacyjny: {$values['userOS']}$enter$tab
    Wersja aplikacji: {$values['userAppVersion']}$enter$tab
    Data utworzenia: {$values['userCreatedAt']}$enter$tab
    Data blokady: {$values['userBlockedAt']}";

        }

        if (isset($values['gpsLogId'])) {

            $message .= "$enter$enter

Lokalizacja:$enter$tab
    ID: {$values['gpsLogId']}$enter$tab
    ID użytkownika: {$values['gpsLogUserId']}$enter$tab
    Współrzędne geograficzne: {$values['gpsLogLocation']}$enter$tab
    Ulica: {$values['gpsLogStreet']}$enter$tab
    Osiedle: {$values['gpsLogHousingEstate']}$enter$tab
    Dzielnica: {$values['gpsLogDistrict']}$enter$tab
    Miasto: {$values['gpsLogCity']}$enter$tab
    Województwo: {$values['gpsLogVoivodeship']}$enter$tab
    Kraj: {$values['gpsLogCountry']}$enter$tab
    Data utworzenia: {$values['gpsLogCreatedAt']}";

        }

        return $message;
    }
}
