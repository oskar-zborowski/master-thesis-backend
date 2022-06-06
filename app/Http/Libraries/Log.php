<?php

namespace App\Http\Libraries;

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Mail\MaliciousnessNotification;
use App\Models\Config;
use App\Models\Connection;
use App\Models\ErrorLog;
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
    public static function prepareConnection(string $ipAddress, ?int $userId, ?bool $isMalicious, ?bool $isLoggingError, ?bool $isLimitExceeded, ?bool $isCrawler, string $errorType, string $errorThrower, string $errorFile, string $errorMethod, string $errorLine, string $errorMessage, bool $isDbConnectionError = false, bool $isSavingLog = true, bool $isSendingMail = true, bool $isCheckingIp = true, bool $isReadingLog = true, ?array $saveDbConnectionError = null) {

        if (!$isDbConnectionError) {

            $encryptedIpAddress = Encrypter::encrypt($ipAddress, 45, false);
            $aesDecrypt = Encrypter::prepareAesDecrypt('ip_address', $encryptedIpAddress);

            try {
                /** @var IpAddress $ipAddressEntity */
                $ipAddressEntity = IpAddress::whereRaw($aesDecrypt)->first();
            } catch (QueryException $e) {
                $isDbConnectionError = true;
                $saveDbConnectionError['errorTypeDb'] = DefaultErrorCode::INTERNAL_SERVER_ERROR()->getType();
                $saveDbConnectionError['errorThrowerDb'] = get_class($e);
                $saveDbConnectionError['errorFileDb'] = __FILE__;
                $saveDbConnectionError['errorFunctionDb'] = __FUNCTION__;
                $saveDbConnectionError['errorLineDb'] = __LINE__;
                $saveDbConnectionError['errorMessageDb'] = strlen(trim($e->getMessage())) > 0 ? "A database error has occurred.\n{$e->getMessage()}" : 'A database error has occurred.';
                self::prepareConnection($ipAddress, $userId, $isMalicious, $isLoggingError, $isLimitExceeded, $isCrawler, $errorType, $errorThrower, $errorFile, $errorMethod, $errorLine, $errorMessage, $isDbConnectionError, $isSavingLog, $isSendingMail, $isCheckingIp, $isReadingLog, $saveDbConnectionError);
                die;
            }

            if (!$ipAddressEntity) {

                if ($isCheckingIp && env('IP_API_ACTIVE')) {

                    do {
                        sleep(env('IP_API_CONST_PAUSE'));
                        $config = Config::where('id', 1)->first();
                        $isIpApiBusy = $config->is_ip_api_busy || $config->ip_api_last_used_at && Validation::timeComparison($config->ip_api_last_used_at, env('IP_API_CONST_PAUSE'), '<', 'seconds');
                    } while ($isIpApiBusy);

                    $config->is_ip_api_busy = true;
                    $config->save();

                    $ipApiErrorCounter = 0;

                    do {

                        $isIpApiError = false;

                        try {
                            $result = file_get_contents("http://2131231231313ip-api.com/json/$ipAddress?fields=status,message,country,regionName,city,isp,org,mobile");
                            $result = json_decode($result, true);
                        } catch (Exception $e) {

                            $errorTypeIpApi = DefaultErrorCode::INTERNAL_SERVER_ERROR()->getType();
                            $errorThrowerIpApi = get_class($e);
                            $errorMessageIpApi = $e->getMessage();
                            $ipApiErrorCounter++;

                            if ($ipApiErrorCounter < env('IP_API_MAX_ATTEMPTS')) {
                                $isIpApiError = true;
                                sleep(($ipApiErrorCounter * env('IP_API_VAR_PAUSE')) + env('IP_API_CONST_PAUSE'));
                            }
                        }

                        if (!$isIpApiError && $ipApiErrorCounter < env('IP_API_MAX_ATTEMPTS')) {

                            if (!isset($result['status']) || $result['status'] != 'success') {

                                if (!isset($errorMessageIpApi)) {

                                    if (isset($result['message']) && is_string($result['message']) && strlen(trim($result['message'])) > 0) {
                                        $errorMessageIpApi = $result['message'];
                                    } else {
                                        $errorMessageIpApi = '';
                                    }
                                }

                                $ipApiErrorCounter++;

                                if ($ipApiErrorCounter < env('IP_API_MAX_ATTEMPTS')) {
                                    $isIpApiError = true;
                                    sleep(($ipApiErrorCounter * env('IP_API_VAR_PAUSE')) + env('IP_API_CONST_PAUSE'));
                                }
                            }
                        }

                    } while ($isIpApiError);

                    $config->is_ip_api_busy = false;
                    $config->ip_api_last_used_at = now();
                    $config->save();

                    if ($ipApiErrorCounter) {
                        $isCheckingIp = false;
                        $errorMessageIpApi = strlen(trim($errorMessageIpApi)) > 0 ? "Failed to get data from ip-api.com ($ipApiErrorCounter times).\n$errorMessageIpApi" : "Failed to get data from ip-api.com ($ipApiErrorCounter times).";
                        self::prepareConnection($ipAddress, $userId, false, true, false, false, $errorTypeIpApi, $errorThrowerIpApi, __FILE__, __FUNCTION__, __LINE__, $errorMessageIpApi, $isDbConnectionError, $isSavingLog, $isSendingMail, $isCheckingIp, $isReadingLog);
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

                if ($isMalicious) {
                    $connection->malicious_request_counter = 1;
                }

                if ($isLoggingError) {
                    $connection->failed_request_counter = 1;
                }

                if ($isLimitExceeded) {
                    $connection->limit_exceeded_request_counter = 1;
                }

                if ($isCrawler) {
                    $connection->crawler_request_counter = 1;
                }

                if (!$isMalicious && !$isLoggingError && !$isLimitExceeded && !$isCrawler) {
                    $connection->successful_request_counter = 1;
                }

            } else {

                if ($isMalicious) {
                    $connection->malicious_request_counter = $connection->malicious_request_counter + 1;
                }

                if ($isLoggingError) {
                    $connection->failed_request_counter = $connection->failed_request_counter + 1;
                }

                if ($isLimitExceeded) {
                    $connection->limit_exceeded_request_counter = $connection->limit_exceeded_request_counter + 1;
                }

                if ($isCrawler) {
                    $connection->crawler_request_counter = $connection->crawler_request_counter + 1;
                }

                if (!$isMalicious && !$isLoggingError && !$isLimitExceeded && !$isCrawler) {
                    $connection->successful_request_counter = $connection->successful_request_counter + 1;
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

            } else if ($isLimitExceeded) {

                if ($connection->limit_exceeded_request_counter == 100) {

                    $ipAddressEntity->blocked_at = now();
                    $ipAddressEntity->save();

                    if ($userId) {
                        /** @var User $user */
                        $user = $connection->user()->first();
                        $user->blocked_at = now();
                        $user->save();
                    }

                    $status = 3;

                } else if ($connection->limit_exceeded_request_counter == 500) {
                    $status = 4;
                }

            } else if ($isLoggingError) {
                $status = 0;
            }

        } else {

            if ($isMalicious) {
                $status = -1;
            } else if ($isLoggingError) {
                $status = 0;
            }

            $connection = null;
        }

        if (isset($status)) {

            if (!$isDbConnectionError) {

                /** @var Config $config */
                $config = Config::where('id', 1)->first();
                $errorNumber = $config->log_counter;

                /** @var ErrorLog $lastErrorLog */
                $lastErrorLog = ErrorLog::whereNotNull('number')->orderBy('id', 'desc')->first();

                if ($lastErrorLog && $errorNumber < $lastErrorLog->number) {
                    $errorNumber = $lastErrorLog->number;
                }

            } else {
                $errorNumber = 'brak';
            }

            if ($isReadingLog && $errorNumber != 'brak') {

                try {

                    $filepath = '../storage/logs/laravel.log';
                    $fp = fopen($filepath, 'r');
                    $filesize = filesize($filepath);

                    if ($filesize > 0) {
                        $logData = fread($fp, $filesize);
                    } else if ($filesize == 0) {
                        $errorNumber = 0;
                    }

                    fclose($fp);

                } catch (Exception $e) {
                    $errorNumber = 'brak';
                    $isReadingLog = false;
                    $errorTypeRL = DefaultErrorCode::INTERNAL_SERVER_ERROR()->getType();
                    $errorThrowerRL = get_class($e);
                    $errorMessageRL = strlen(trim($e->getMessage())) > 0 ? "There was an error opening the log file.\n{$e->getMessage()}" : 'There was an error opening the log file.';
                    self::prepareConnection($ipAddress, $userId, false, true, false, false, $errorTypeRL, $errorThrowerRL, __FILE__, __FUNCTION__, __LINE__, $errorMessageRL, $isDbConnectionError, $isSavingLog, $isSendingMail, $isCheckingIp, $isReadingLog);
                }

                if (isset($logData)) {

                    $last = 0;

                    do {

                        $needle = "\nNr błędu: ";
                        $lastFoundErrorNumber = FieldConversion::findLastOccurrenceInString($logData, $needle, $last);

                        if ($lastFoundErrorNumber !== false) {
                            $lastFoundErrorNumber += strlen($needle);
                        }

                        $currentErrorNumber = 0;

                        if ($lastFoundErrorNumber !== false) {
                            for ($i=$lastFoundErrorNumber; ord($logData[$i]) >= 48 && ord($logData[$i]) <= 57; $i++) {
                                $currentErrorNumber *= 10;
                                $currentErrorNumber += $logData[$i];
                            }
                        }

                        $last++;

                    } while ($lastFoundErrorNumber && !$currentErrorNumber);

                    if ($errorNumber < $currentErrorNumber) {
                        $errorNumber = $currentErrorNumber;
                    }
                }

            } else {
                $errorNumber = 'brak';
            }

            if ($errorNumber != 'brak') {
                $errorNumber++;
            }

            if ($isSavingLog) {

                try {

                    $log = Log::prepareMessage('log', $status, $connection, $errorType, $errorThrower, $errorFile, $errorMethod, $errorLine, $errorMessage, $errorNumber);
                    $log .= "\n\n------------------------------------------------------------------------------------------------------\n";

                    if ($errorType == DefaultErrorCode::INTERNAL_SERVER_ERROR()->getType()) {
                        FacadesLog::error($log);
                    } else {
                        FacadesLog::alert($log);
                    }

                } catch (Exception $e) {

                    $isSavingLog = false;
                    $errorTypeSL = DefaultErrorCode::INTERNAL_SERVER_ERROR()->getType();
                    $errorThrowerSL = get_class($e);
                    $strpos = strpos($e->getMessage(), 'The exception occurred while attempting to log:');

                    if ($strpos !== false) {
                        $errorMessageSL = substr($e->getMessage(), 0, $strpos-1);
                    }

                    $errorMessageSL = strlen(trim($errorMessageSL)) > 0 ? "Failed to save the log.\n$errorMessageSL" : 'Failed to save the log.';
                    self::prepareConnection($ipAddress, $userId, false, true, false, false, $errorTypeSL, $errorThrowerSL, __FILE__, __FUNCTION__, __LINE__, $errorMessageSL, $isDbConnectionError, $isSavingLog, $isSendingMail, $isCheckingIp, $isReadingLog);
                }
            }

            if ($isSendingMail && env('MAIL_ACTIVE')) {

                if (!$isDbConnectionError) {

                    do {
                        sleep(env('MAIL_CONST_PAUSE'));
                        $config = Config::where('id', 1)->first();
                        $isMailBusy = $config->is_mail_busy || $config->mail_last_used_at && Validation::timeComparison($config->mail_last_used_at, env('MAIL_CONST_PAUSE'), '<', 'seconds');
                    } while ($isMailBusy);

                    $config->is_mail_busy = true;
                    $config->save();
                }

                $mailErrorCounter = 0;

                do {

                    $isMailError = false;

                    try {
                        Mail::send(new MaliciousnessNotification($status, $connection, $errorType, $errorThrower, $errorFile, $errorMethod, $errorLine, $errorMessage, $errorNumber));
                    } catch (Exception $e) {

                        $errorTypeMail = DefaultErrorCode::INTERNAL_SERVER_ERROR()->getType();
                        $errorThrowerMail = get_class($e);
                        $errorMessageMail = $e->getMessage();
                        $mailErrorCounter++;

                        if ($mailErrorCounter < env('MAIL_MAX_ATTEMPTS')) {
                            $isMailError = true;
                            sleep(($mailErrorCounter * env('MAIL_VAR_PAUSE')) + env('MAIL_CONST_PAUSE'));
                        }
                    }

                } while ($isMailError);

                if (!$isDbConnectionError) {
                    $config->is_mail_busy = false;
                    $config->mail_last_used_at = now();
                    $config->save();
                }

                if ($mailErrorCounter) {
                    $isSendingMail = false;
                    $errorMessageMail = strlen(trim($errorMessageMail)) > 0 ? "Failed to send the email ($mailErrorCounter times).\n$errorMessageMail" : "Failed to send the email ($mailErrorCounter times).";
                    self::prepareConnection($ipAddress, $userId, false, true, false, false, $errorTypeMail, $errorThrowerMail, __FILE__, __FUNCTION__, __LINE__, $errorMessageMail, $isDbConnectionError, $isSavingLog, $isSendingMail, $isCheckingIp, $isReadingLog);
                }
            }

            if (!$isDbConnectionError) {

                $newErrorLog = new ErrorLog;

                if ($errorNumber != 'brak') {
                    $newErrorLog->number = $errorNumber;
                }

                $newErrorLog->connection_id = $connection->id;
                $newErrorLog->type = $errorType;
                $newErrorLog->thrower = $errorThrower;

                if ($errorFile != 'brak') {
                    $newErrorLog->file = $errorFile;
                }

                if ($errorMethod != 'brak') {
                    $newErrorLog->method = $errorMethod;
                }

                if ($errorLine != 'brak') {
                    $newErrorLog->line = $errorLine;
                }

                $newErrorLog->subject = self::getMessageFirstLine($status, $userId);

                if ($errorMessage != 'brak') {
                    $newErrorLog->message = $errorMessage;
                }

                $newErrorLog->save();

                if ($errorNumber != 'brak') {

                    /** @var Config $config */
                    $config = Config::where('id', 1)->first();

                    if ($errorNumber > $config->log_counter) {
                        $config->log_counter = $errorNumber;
                        $config->save();
                    }
                }
            }
        }

        if (isset($saveDbConnectionError)) {
            self::prepareConnection($ipAddress, $userId, false, true, false, false, $saveDbConnectionError['errorTypeDb'], $saveDbConnectionError['errorThrowerDb'], $saveDbConnectionError['errorFileDb'], $saveDbConnectionError['errorFunctionDb'], $saveDbConnectionError['errorLineDb'], $saveDbConnectionError['errorMessageDb'], $isDbConnectionError, $isSavingLog, $isSendingMail, $isCheckingIp, $isReadingLog);
        }
    }

    public static function prepareMessage(string $type, int $status, ?Connection $connection, string $errorType, string $errorThrower, string $errorFile, string $errorMethod, string $errorLine, string $errorMessage, $errorNumber) {

        $values['errorType'] = $errorType;
        $values['errorThrower'] = $errorThrower;
        $values['errorFile'] = $errorFile;
        $values['errorMethod'] = $errorMethod;
        $values['errorLine'] = $errorLine;
        $values['errorMessage'] = $errorMessage;
        $values['errorNumber'] = $errorNumber;

        if ($type == 'mail') {
            $values['errorMessage'] = str_replace(["\n", '    '], ['<br>', '&emsp;&nbsp;'], $values['errorMessage']);
            $enter = '<br>';
            $tab = '&emsp;';
        } else if ($type == 'log') {
            $enter = '';
            $tab = '';
        } else {
            throw new ApiException(
                DefaultErrorCode::INTERNAL_SERVER_ERROR(false, true),
                __('validation.custom.invalid-log-type'),
                __FUNCTION__,
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
            $user = $connection->user()->first();

            /** @var IpAddress $ipAddress */
            $ipAddress = $connection->ipAddress()->first();

            $connectionIds = [];
            $values['connectionId'] = '';
            $values['successfulRequestCounter'] = (int) $connection->successful_request_counter;
            $values['failedRequestCounter'] = (int) $connection->failed_request_counter;
            $values['limitExceededRequestCounter'] = (int) $connection->limit_exceeded_request_counter;
            $values['maliciousRequestCounter'] = (int) $connection->malicious_request_counter;
            $values['crawlerRequestCounter'] = (int) $connection->crawler_request_counter;
            $successfulRequestCounterAll = 0;
            $failedRequestCounterAll = 0;
            $limitExceededRequestCounterAll = 0;
            $maliciousRequestCounterAll = 0;
            $crawlerRequestCounterAll = 0;
            $values['connectionCreatedAt'] = 0;

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

            $values['ipAddressCreatedAt'] = 0;
            $values['ipAddressBlockedAt'] = $ipAddress->blocked_at ? $ipAddress->blocked_at : 'brak';
            $blockedIpAddressesCounter = 0;
        }

        if ($user) {

            $userIds = [];
            $values['userId'] = '';
            $values['userCreatedAt'] = 0;
            $values['userBlockedAt'] = $user->blocked_at ? $user->blocked_at : 'brak';
            $blockedUsersCounter = 0;

            if ($user->uuid !== null && strlen(trim($user->uuid)) > 0) {

                $encryptedUserUuid = Encrypter::encrypt($user->uuid, 45, false);
                $aesDecrypt = Encrypter::prepareAesDecrypt('uuid', $encryptedUserUuid);

                /** @var User[] $users */
                $users = User::whereRaw($aesDecrypt)->get();

                foreach ($users as $u) {

                    $isUserMalicious = false;
                    $isUserFailed = false;
                    $isUserLimitExceeded = false;
                    $isUserCrawler = false;

                    /** @var Connection[] $connections */
                    $connections = $u->connections()->get();

                    foreach ($connections as $c) {

                        if ($c->malicious_request_counter > 0) {
                            $connectionIds[] = "{$c->id}M";
                            $isUserMalicious = true;
                        } else if ($c->limit_exceeded_request_counter > 0) {
                            $connectionIds[] = "{$c->id}L";
                            $isUserLimitExceeded = true;
                        } else if ($c->failed_request_counter > 0) {
                            $connectionIds[] = "{$c->id}F";
                            $isUserFailed = true;
                        } else if ($c->crawler_request_counter > 0) {
                            $connectionIds[] = "{$c->id}C";
                            $isUserCrawler = true;
                        } else {
                            $connectionIds[] = $c->id;
                        }

                        $successfulRequestCounterAll += $c->successful_request_counter;
                        $failedRequestCounterAll += $c->failed_request_counter;
                        $limitExceededRequestCounterAll += $c->limit_exceeded_request_counter;
                        $maliciousRequestCounterAll += $c->malicious_request_counter;
                        $crawlerRequestCounterAll += $c->crawler_request_counter;

                        if ($c->created_at) {
                            if (!$values['connectionCreatedAt']) {
                                $values['connectionCreatedAt'] = $c->created_at;
                            } else if ($values['connectionCreatedAt'] > $c->created_at) {
                                $values['connectionCreatedAt'] = $c->created_at;
                            }
                        }

                        /** @var IpAddress $ip */
                        $ip = $c->ipAddress()->first();

                        if ($ip) {

                            $isIpAddressMalicious = false;
                            $isIpAddressFailed = false;
                            $isIpAddressLimitExceeded = false;
                            $isIpAddressCrawler = false;

                            /** @var Connection[] $cnns */
                            $cnns = $ip->connections()->get();

                            foreach ($cnns as $cn) {
                                if ($cn->malicious_request_counter > 0) {
                                    $isIpAddressMalicious = true;
                                } else if ($cn->limit_exceeded_request_counter > 0) {
                                    $isIpAddressLimitExceeded = true;
                                } else if ($cn->failed_request_counter > 0) {
                                    $isIpAddressFailed = true;
                                } else if ($cn->crawler_request_counter > 0) {
                                    $isIpAddressCrawler = true;
                                }
                            }

                            if (!in_array($ip->id, $ipAddressIds)) {

                                if ($isIpAddressMalicious) {
                                    $ipAddressIds[] = "{$ip->id}M";
                                } else if ($isIpAddressLimitExceeded) {
                                    $ipAddressIds[] = "{$ip->id}L";
                                } else if ($isIpAddressFailed) {
                                    $ipAddressIds[] = "{$ip->id}F";
                                } else if ($isIpAddressCrawler) {
                                    $ipAddressIds[] = "{$ip->id}C";
                                } else {
                                    $ipAddressIds[] = $ip->id;
                                }

                                if ($ip->blocked_at) {
                                    $blockedIpAddressesCounter++;
                                }
                            }

                            if ($ip->created_at) {
                                if (!$values['ipAddressCreatedAt']) {
                                    $values['ipAddressCreatedAt'] = $ip->created_at;
                                } else if ($values['ipAddressCreatedAt'] > $ip->created_at) {
                                    $values['ipAddressCreatedAt'] = $ip->created_at;
                                }
                            }
                        }
                    }

                    if ($isUserMalicious) {
                        $userIds[] = "{$u->id}M";
                    } else if ($isUserLimitExceeded) {
                        $userIds[] = "{$u->id}L";
                    } else if ($isUserFailed) {
                        $userIds[] = "{$u->id}F";
                    } else if ($isUserCrawler) {
                        $userIds[] = "{$u->id}C";
                    } else {
                        $userIds[] = $u->id;
                    }

                    if ($u->created_at) {
                        if (!$values['userCreatedAt']) {
                            $values['userCreatedAt'] = $u->created_at;
                        } else if ($values['userCreatedAt'] > $u->created_at) {
                            $values['userCreatedAt'] = $u->created_at;
                        }
                    }

                    if ($u->blocked_at) {
                        $blockedUsersCounter++;
                    }
                }

                asort($connectionIds);
                asort($ipAddressIds);
                asort($userIds);

                foreach ($connectionIds as $id) {

                    $onlyId = str_replace(['M', 'L', 'F', 'C'], ['', '', '', ''], $id);

                    if ($onlyId == $connection->id) {
                        $values['connectionId'] .= "[$id], ";
                    } else {
                        $values['connectionId'] .= "$id, ";
                    }
                }

                foreach ($ipAddressIds as $id) {

                    $onlyId = str_replace(['M', 'L', 'F', 'C'], ['', '', '', ''], $id);

                    if ($onlyId == $ipAddress->id) {
                        $values['ipAddressId'] .= "[$id], ";
                    } else {
                        $values['ipAddressId'] .= "$id, ";
                    }
                }

                foreach ($userIds as $id) {

                    $onlyId = str_replace(['M', 'L', 'F', 'C'], ['', '', '', ''], $id);

                    if ($onlyId == $user->id) {
                        $values['userId'] .= "[$id], ";
                    } else {
                        $values['userId'] .= "$id, ";
                    }
                }

                $values['connectionId'] = substr($values['connectionId'], 0, -2);
                $values['ipAddressId'] = substr($values['ipAddressId'], 0, -2);
                $values['userId'] = substr($values['userId'], 0, -2);

                if (strpos($values['connectionId'], ',') === false) {
                    $values['connectionId'] = str_replace(['[', ']'], ['', ''], $values['connectionId']);
                } else {

                    $values['successfulRequestCounter'] = "$successfulRequestCounterAll [{$values['successfulRequestCounter']}]";
                    $values['failedRequestCounter'] = "$failedRequestCounterAll [{$values['failedRequestCounter']}]";
                    $values['limitExceededRequestCounter'] = "$limitExceededRequestCounterAll [{$values['limitExceededRequestCounter']}]";
                    $values['maliciousRequestCounter'] = "$maliciousRequestCounterAll [{$values['maliciousRequestCounter']}]";
                    $values['crawlerRequestCounter'] = "$crawlerRequestCounterAll [{$values['crawlerRequestCounter']}]";

                    if ($values['connectionCreatedAt'] && $connection->created_at) {
                        $values['connectionCreatedAt'] = "{$values['connectionCreatedAt']} [$connection->created_at]";
                    } else if ($values['connectionCreatedAt']) {
                        $values['connectionCreatedAt'] = "{$values['connectionCreatedAt']} [brak]";
                    }
                }

                if (strpos($values['ipAddressId'], ',') === false) {
                    $values['ipAddressId'] = str_replace(['[', ']'], ['', ''], $values['ipAddressId']);
                } else {

                    if ($values['ipAddressCreatedAt'] && $ipAddress->created_at) {
                        $values['ipAddressCreatedAt'] = "{$values['ipAddressCreatedAt']} [$ipAddress->created_at]";
                    } else if ($values['ipAddressCreatedAt']) {
                        $values['ipAddressCreatedAt'] = "{$values['ipAddressCreatedAt']} [brak]";
                    }

                    $values['ipAddressBlockedAt'] = "{$values['ipAddressBlockedAt']} [$blockedIpAddressesCounter]";
                }

                if (strpos($values['userId'], ',') === false) {
                    $values['userId'] = str_replace(['[', ']'], ['', ''], $values['userId']);
                } else {

                    if ($values['userCreatedAt'] && $user->created_at) {
                        $values['userCreatedAt'] = "{$values['userCreatedAt']} [$user->created_at]";
                    } else if ($values['userCreatedAt']) {
                        $values['userCreatedAt'] = "{$values['userCreatedAt']} [brak]";
                    }

                    $values['userBlockedAt'] = "{$values['userBlockedAt']} [$blockedUsersCounter]";
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
                $values['gpsLogLocation'] = strlen(trim($gpsLog->gps_location)) > 0 ? $gpsLog->gps_location : 'brak';

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

        if (isset($values['connectionId']) && $values['connectionId'] == '') {
            $values['connectionId'] = $connection->id;
        }

        if (isset($values['connectionCreatedAt']) && !$values['connectionCreatedAt']) {
            if ($connection->created_at) {
                $values['connectionCreatedAt'] = $connection->created_at;
            } else {
                $values['connectionCreatedAt'] = 'brak';
            }
        }

        if (isset($values['ipAddressId']) && $values['ipAddressId'] == '') {
            $values['ipAddressId'] = $ipAddress->id;
        }

        if (isset($values['ipAddressCreatedAt']) && !$values['ipAddressCreatedAt']) {
            if ($ipAddress->created_at) {
                $values['ipAddressCreatedAt'] = $ipAddress->created_at;
            } else {
                $values['ipAddressCreatedAt'] = 'brak';
            }
        }

        if (isset($values['userCreatedAt']) && !$values['userCreatedAt']) {
            $values['userCreatedAt'] = 'brak';
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
                __FUNCTION__,
                false
            );
        }

        return $result;
    }

    public static function getLocation(string $gpsLocation, string $ipAddress, int $userId) {

        $location = null;

        if (env('NOMINATIM_ACTIVE')) {

            $gpsLocation = explode(' ', $gpsLocation);
            $longitude = $gpsLocation[0];
            $latitude = $gpsLocation[1];

            $url = 'https://nominatim.openstreetmap.org';
            $nominatim = new Nominatim($url);

            do {
                sleep(env('NOMINATIM_CONST_PAUSE'));
                $config = Config::where('id', 1)->first();
                $isNominatimBusy = $config->is_nominatim_busy || $config->nominatim_last_used_at && Validation::timeComparison($config->nominatim_last_used_at, env('NOMINATIM_CONST_PAUSE'), '<', 'seconds');
            } while ($isNominatimBusy);

            $config->is_nominatim_busy = true;
            $config->save();

            $nominatimErrorCounter = 0;

            do {

                $isNominatimError = false;

                try {
                    $reverse = $nominatim->newReverse()->latlon($latitude, $longitude);
                    $result = $nominatim->find($reverse)['address'];
                } catch (NominatimException | GuzzleException $e) {

                    $errorType = DefaultErrorCode::INTERNAL_SERVER_ERROR()->getType();
                    $errorThrower = get_class($e);
                    $errorMessage = $e->getMessage();
                    $nominatimErrorCounter++;

                    if ($nominatimErrorCounter < env('NOMINATIM_MAX_ATTEMPTS')) {
                        $isNominatimError = true;
                        sleep(($nominatimErrorCounter * env('NOMINATIM_VAR_PAUSE')) + env('NOMINATIM_CONST_PAUSE'));
                    }
                }

            } while ($isNominatimError);

            $config->is_nominatim_busy = false;
            $config->nominatim_last_used_at = now();
            $config->save();

            if ($nominatimErrorCounter) {
                $errorMessage = strlen(trim($errorMessage)) > 0 ? "Failed to get data from Nominati ($nominatimErrorCounter times).\n$errorMessage" : "Failed to get data from Nominati ($nominatimErrorCounter times).";
                self::prepareConnection($ipAddress, $userId, false, true, false, false, $errorType, $errorThrower, __FILE__, __FUNCTION__, __LINE__, $errorMessage);
            }

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
        }

        return $location;
    }

    private static function getMessageTemplate(int $status, array $values, string $enter, string $tab) {

        $userId = isset($values['userId']) ? $values['userId'] : null;

        $message = self::getMessageFirstLine($status, $userId);
        $message .= "!$enter$enter

Nr błędu: {$values['errorNumber']}$enter$enter

Informacje:$enter$tab
    Typ: {$values['errorType']}$enter$tab
    Zgłaszający: {$values['errorThrower']}$enter$tab
    Plik: {$values['errorFile']}$enter$tab
    Metoda: {$values['errorMethod']}$enter$tab
    Linia: {$values['errorLine']}$enter$tab
    Opis: {$values['errorMessage']}";

        if (isset($values['connectionId'])) {

            $message .= "$enter$enter

Połączenie:$enter$tab
    ID: {$values['connectionId']}$enter$tab
    Pomyślnych żądań: {$values['successfulRequestCounter']}$enter$tab
    Błędnych żądań: {$values['failedRequestCounter']}$enter$tab
    Nadużywanych żądań: {$values['limitExceededRequestCounter']}$enter$tab
    Złośliwych żądań: {$values['maliciousRequestCounter']}$enter$tab
    Crawlerowych żądań: {$values['crawlerRequestCounter']}$enter$tab
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

    private static function getMessageFirstLine(int $status, ?int $userId) {

        if ($status == -1) {
            $firstLine = 'Wykryto próbę wysłania złośliwego żądania';
        } else if ($status == 1) {
            $firstLine = 'Wykryto pierwszą próbę wysłania złośliwego żądania';
        } else if ($status == 2) {
            $firstLine = 'Wykryto kolejną próbę wysłania złośliwego żądania';
        } else if ($status == 3) {

            $firstLine = 'Zablokowano adres IP przychodzącego żądania';

            if (isset($userId)) {
                $firstLine .= ' oraz konto użytkownika';
            }

        } else if ($status == 4) {
            $firstLine = 'Wymagana jest permanentna blokada adresu IP przychodzącego żądania';
        } else {
            $firstLine = 'Wystąpił nieoczekiwany błąd';
        }

        return $firstLine;
    }
}
