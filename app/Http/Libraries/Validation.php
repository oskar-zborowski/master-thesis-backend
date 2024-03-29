<?php

namespace App\Http\Libraries;

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Models\IpAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;

/**
 * Klasa przeprowadzająca procesy walidacji danych
 */
class Validation
{
    public static function user_createUser() {
        return [
            'gps_location' => 'required|string|between:3,20',
            'producer' => 'nullable|string|between:1,30',
            'model' => 'nullable|string|between:1,50',
            'os_name' => ['nullable', Rule::in(self::getOsNames())],
            'os_version' => 'nullable|string|between:1,10',
            'app_version' => ['required', Rule::in(self::getAppVersions())],
            'uuid' => 'nullable|string|between:1,45',
        ];
    }

    public static function user_updateUser() {
        return [
            'gps_location' => 'required|string|between:3,20',
            'name' => 'nullable|string|between:1,15',
            'os_version' => 'nullable|string|between:1,10',
            'app_version' => ['required', Rule::in(self::getAppVersions())],
        ];
    }

    public static function room_updateRoom() {
        return [
            'host_id' => 'nullable|integer|exists:users,id',
            'is_code_renewal' => 'nullable|boolean',
            'is_set_initial_settings' => 'nullable|boolean',
            'is_cleaning_roles' => 'nullable|boolean',
            'actor_policeman_number' => 'nullable|integer|between:1,29',
            'actor_policeman_visibility_radius' => 'nullable|integer|between:-1,50000',
            'actor_policeman_are_friends_circles_visible' => 'nullable|boolean',
            'actor_policeman_catching_number' => 'nullable|integer|between:1,29',
            'actor_policeman_catching_radius' => 'nullable|integer|between:5,5000',
            'actor_thief_number' => 'nullable|integer|between:1,29',
            'actor_thief_probability' => 'nullable|numeric|between:0,1',
            'actor_thief_visibility_radius' => 'nullable|integer|between:-1,50000',
            'actor_thief_are_friends_circles_visible' => 'nullable|boolean',
            'actor_thief_are_enemies_circles_visible' => 'nullable|boolean',
            'actor_thief_escape_duration' => 'nullable|integer|between:30,3600',
            'actor_thief_disclosure_interval' => 'nullable|integer|between:-1,3600',
            'actor_thief_black_ticket_number' => 'nullable|integer|between:0,10',
            'actor_thief_black_ticket_probability' => 'nullable|numeric|between:0,1',
            'actor_thief_black_ticket_duration' => 'nullable|integer|between:30,3600',
            'actor_thief_fake_position_number' => 'nullable|integer|between:0,10',
            'actor_thief_fake_position_probability' => 'nullable|numeric|between:0,1',
            'actor_thief_fake_position_duration' => 'nullable|integer|between:30,3600',
            'actor_agent_number' => 'nullable|integer|between:0,29',
            'actor_agent_probability' => 'nullable|numeric|between:0,1',
            'actor_pegasus_number' => 'nullable|integer|between:0,29',
            'actor_pegasus_probability' => 'nullable|numeric|between:0,1',
            'actor_pegasus_white_ticket_number' => 'nullable|integer|between:0,20',
            'actor_pegasus_white_ticket_probability' => 'nullable|numeric|between:0,1',
            'actor_fatty_man_number' => 'nullable|integer|between:0,29',
            'actor_fatty_man_probability' => 'nullable|numeric|between:0,1',
            'actor_eagle_number' => 'nullable|integer|between:0,29',
            'actor_eagle_probability' => 'nullable|numeric|between:0,1',
            'duration_scheduled' => 'nullable|integer|between:300,14400',
            'other_bot_speed' => 'nullable|numeric|between:1.5,15',
            'other_max_speed' => 'nullable|numeric|between:-1,15',
            'other_warning_number' => 'nullable|integer|between:-1,5',
            'other_is_pause_after_disconnecting' => 'nullable|boolean',
            'other_disconnecting_countdown' => 'nullable|integer|between:-1,900',
            'other_crossing_boundary_countdown' => 'nullable|integer|between:-1,900',
            'boundary_points' => 'nullable|string|between:15,419',
        ];
    }

    public static function player_createPlayer() {
        return [
            'code' => 'required|string',
        ];
    }

    public static function player_updatePlayer() {
        return [
            'gps_location' => 'required|string|between:3,20',
            'avatar' => ['nullable', Rule::in(self::getAvatars())],
            'use_white_ticket' => 'nullable|boolean',
            'use_black_ticket' => 'nullable|boolean',
            'use_fake_position' => 'nullable|string|between:3,20',
            'status' => ['nullable', Rule::in(['LEFT'])],
            'voting_type' => ['nullable', Rule::in(self::getVotingTypes())],
            'voting_answer' => 'nullable|boolean',
            'is_replenishment_with_bots' => 'nullable|boolean',
        ];
    }

    public static function player_setStatus() {
        return [
            'status' => ['required', Rule::in(['BANNED', 'LEFT'])],
        ];
    }

    public static function player_setRole() {
        return [
            'role' => ['nullable', Rule::in(self::getPlayerRoles())],
        ];
    }

    public static function getAvatars() {
        return [
            'AVATAR_1',
            'AVATAR_2',
            'AVATAR_3',
            'AVATAR_4',
            'AVATAR_5',
            'AVATAR_6',
            'AVATAR_7',
            'AVATAR_8',
            'AVATAR_9',
            'AVATAR_10',
            'AVATAR_11',
            'AVATAR_12',
            'AVATAR_13',
            'AVATAR_14',
            'AVATAR_15',
            'AVATAR_16',
            'AVATAR_17',
            'AVATAR_18',
            'AVATAR_19',
            'AVATAR_20',
            'AVATAR_21',
            'AVATAR_22',
            'AVATAR_23',
            'AVATAR_24',
            'AVATAR_25',
            'AVATAR_26',
            'AVATAR_27',
            'AVATAR_28',
            'AVATAR_29',
            'AVATAR_30',
        ];
    }

    public static function getOsNames() {
        return [
            'ANDROID',
            'IOS',
        ];
    }

    public static function getAppVersions() {
        return [
            '1.0.0',
        ];
    }

    public static function getRoomStatuses() {
        return [
            'WAITING_IN_ROOM',
            'GAME_IN_PROGRESS',
            'GAME_PAUSED',
            'GAME_OVER',
        ];
    }

    public static function getGameResults() {
        return [
            'POLICEMEN_WON_BY_CATCHING',
            'THIEVES_WON_ON_TIME',
            'POLICEMEN_SURRENDERED',
            'THIEVES_SURRENDERED',
            'DRAW',
            'UNFINISHED',
        ];
    }

    public static function getPlayerRoles() {
        return [
            'POLICEMAN',
            'THIEF',
            'AGENT',
            'PEGASUS',
            'FATTY_MAN',
            'EAGLE',
        ];
    }

    public static function getPlayerStatuses() {
        return [
            'CONNECTED',
            'DISCONNECTED',
            'BANNED',
            'LEFT',
            'SUPERVISING',
        ];
    }

    public static function getVotingTypes() {
        return [
            'START', // Wszyscy muszą wyrazić zgodę
            'ENDING_COUNTDOWN', // Wszyscy muszą wyrazić zgodę
            'PAUSE',
            'RESUME', // Wszyscy muszą wyrazić zgodę
            'END_GAME',
            'GIVE_UP', // Tylko z danej frakcji
        ];
    }

    public static function getRouteNamesWhitelist() {
        return [
            'user-createUser',
            'ipAddress-get',
            'github-pull',
            'crawler-get',
            'crawler-post',
        ];
    }

    public static function checkUniqueness(string $value, $entity, string $field, bool $isEncrypted = false) {

        if ($isEncrypted) {
            $aesDecrypt = Encrypter::prepareAesDecrypt($field, $value);
            $result = empty($entity::whereRaw($aesDecrypt)->first());
        } else {
            $result = empty($entity::where($field, $value)->first());
        }

        return $result;
    }

    public static function checkBoundary(string $boundary) {

        $polygon = null;
        $boundaryPoints = explode(',', $boundary);
        $boundaryPointsNumber = count($boundaryPoints);

        if ($boundaryPointsNumber < 4 || $boundaryPointsNumber > 20) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.incorrect-boundary-vertices-number'),
                __FUNCTION__
            );
        }

        foreach ($boundaryPoints as $boundaryPoint) {

            self::checkGpsLocation($boundaryPoint);

            $coordinates = explode(' ', $boundaryPoint);

            $p1['x'] = $coordinates[0];
            $p1['y'] = $coordinates[1];

            $p = Geometry::convertLatLngToXY($p1);

            $polygon[] = [
                $p['x'],
                $p['y'],
            ];
        }

        if ($boundaryPoints[0] != $boundaryPoints[$boundaryPointsNumber-1]) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.boundary-not-closed'),
                __FUNCTION__
            );
        }

        $convertedBoundary = Geometry::convertGeometryLatLngToXY($boundary);

        $isValid = DB::select(DB::raw("SELECT ST_IsValid(ST_GeomFromText('POLYGON(($convertedBoundary))')) AS isValid"));

        if (!$isValid[0]->isValid) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.invalid-boundary-shape'),
                __FUNCTION__
            );
        }

        $isConvex = self::isConvex($polygon);

        if (!$isConvex) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.invalid-boundary-shape'),
                __FUNCTION__
            );
        }
    }

    public static function checkGpsLocation(string $gpsLocation) {

        $gpsLocation = explode(' ', $gpsLocation);

        if (count($gpsLocation) != 2) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(true),
                __('validation.custom.invalid-coordinate-format'),
                __FUNCTION__,
                false
            );
        }

        if (!self::checkNumber($gpsLocation[0], 3, 5) || !self::checkNumber($gpsLocation[1], 2, 5)) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(true),
                __('validation.custom.invalid-coordinate-format'),
                __FUNCTION__,
                false
            );
        }

        if ((float) $gpsLocation[0] <= -180 || (float) $gpsLocation[0] > 180 || (float) $gpsLocation[1] < -90 || (float) $gpsLocation[1] > 90) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(true),
                __('validation.custom.invalid-coordinate-format'),
                __FUNCTION__,
                false
            );
        }
    }

    public static function checkNumber(string $number, int $maxDigitsBeforeDecimalPoint, ?int $maxDigitsAfterDecimalPoint = null) {

        $result = true;
        $dot = false;
        $digitsAfterDot = 0;
        $minus = 0;
        $i = 1;
        $length = strlen($number);

        if ($length == 0) {
            $result = false;
        } else if ($length == 1) {

            if (!self::checkDigit($number[0])) {
                $result = false;
            }

        } else if (ord($number[0]) != 45 && !self::checkDigit($number[0])) {
            $result = false;
        } else if (ord($number[0]) == 45 && !self::checkDigit($number[1])) {
            $result = false;
        } else {

            if (ord($number[0]) == 45) {
                $minus = 1;
                $i++;
            }

            for ($i; $i<$length; $i++) {

                if (ord($number[$i]) != 46 && !self::checkDigit($number[$i])) {
                    $result = false;
                    break;
                } else if (ord($number[$i]) == 46) {

                    if ($maxDigitsAfterDecimalPoint !== null && $maxDigitsAfterDecimalPoint == 0) {
                        $result = false;
                        break;
                    } else if ($i - $minus > $maxDigitsBeforeDecimalPoint || $dot) {
                        $result = false;
                        break;
                    } else {
                        $dot = true;
                    }

                } else {

                    if ($dot) {
                        $digitsAfterDot++;
                    }

                    if ($maxDigitsAfterDecimalPoint !== null && $digitsAfterDot > $maxDigitsAfterDecimalPoint) {
                        $result = false;
                        break;
                    }
                }
            }

            if ($dot && $digitsAfterDot == 0) {
                $result = false;
            }
        }

        return $result;
    }

    public static function checkDigit(string $digit) {

        $result = true;

        if (ord($digit) < 48 || ord($digit) > 57) {
            $result = false;
        }

        return $result;
    }

    /**
     * Sprawdzenie czy upłynął określony czas
     *
     * @param string $timeReferencePoint punkt odniesienia względem którego liczony jest czas
     * @param int $timeMarker wartość znacznika czasu przez jak długo jest ważny
     * @param string $comparator jeden z symboli <, >, == lub ich kombinacja, liczone względem bieżącego czasu
     * @param string $unit jednostka w jakiej wyrażony jest $timeMarker
     */
    public static function timeComparison(string $timeReferencePoint, int $timeMarker, string $comparator, string $unit = 'minutes') {

        $now = now();
        $expirationDate = date('Y-m-d H:i:s', strtotime('+' . $timeMarker . ' ' . $unit, strtotime($timeReferencePoint)));

        $comparasion = false;

        switch ($comparator) {

            case '==':
                if ($now == $expirationDate) {
                    $comparasion = true;
                }
                break;

            case '>=':
                if ($now >= $expirationDate) {
                    $comparasion = true;
                }
                break;

            case '>':
                if ($now > $expirationDate) {
                    $comparasion = true;
                }
                break;

            case '<=':
                if ($now <= $expirationDate) {
                    $comparasion = true;
                }
                break;

            case '<':
                if ($now < $expirationDate) {
                    $comparasion = true;
                }
                break;
        }

        return $comparasion;
    }

    public static function chooseAvatar() {

        $avatars = self::getAvatars();

        $avatarCounter = count($avatars);
        $number = rand(0, $avatarCounter-1);

        return $avatars[$number];
    }

    public static function validateRequestFields(Request $request, ?string $routeName) {

        if ($routeName === null) {
            throw new ApiException(
                DefaultErrorCode::INTERNAL_SERVER_ERROR(false, true),
                __('validation.custom.endpoint-name-not-found'),
                __FUNCTION__,
                false
            );
        }

        $allFields = $request->all();
        $routeName = str_replace('-', '_', $routeName);

        if (method_exists(self::class, $routeName)) {

            foreach ($allFields as $key => $value) {
                if (!array_key_exists($key, self::$routeName())) {
                    $undefinedFields[] = $key;
                }
            }

            if (isset($undefinedFields)) {

                $undefinedFields = implode(', ', $undefinedFields);

                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(true),
                    __('validation.custom.undefined-request-fields-detected', ['fields' => $undefinedFields]),
                    __FUNCTION__,
                    false
                );
            }
        }
    }

    public static function secondAuthenticate(Request $request, bool $isErrorThrown = false) {

        if (env('APP_ENV') == 'local') {
            $encryptedIpAddress = Encrypter::encrypt('83.8.175.174', 45, false);
        } else {
            $encryptedIpAddress = Encrypter::encrypt($request->ip(), 45, false);
        }

        $aesDecrypt = Encrypter::prepareAesDecrypt('ip_address', $encryptedIpAddress);

        /** @var IpAddress $ipAddress */
        $ipAddress = IpAddress::whereRaw($aesDecrypt)->whereNotNull('blocked_at')->first();

        if ($ipAddress) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('auth.ip-blocked'),
                __FUNCTION__
            );
        }

        if (!$isErrorThrown) {

            $token = $request->header('token');
            $refreshToken = $request->header('refreshToken');

            $routeName = Route::currentRouteName();
            $routeNamesWhitelist = self::getRouteNamesWhitelist();

            $personalAccessToken = Session::get('personalAccessToken');
            Session::remove('personalAccessToken');

            if ($request->token !== null) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(true),
                    __('auth.invalid-token-format'),
                    __FUNCTION__,
                    false
                );
            }

            if ($request->refresh_token !== null) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(true),
                    __('auth.invalid-refresh-token-format'),
                    __FUNCTION__,
                    false
                );
            }

            if (!in_array($routeName, $routeNamesWhitelist)) {

                if (!isset($token) && !isset($refreshToken)) {
                    throw new ApiException(
                        DefaultErrorCode::FAILED_VALIDATION(true),
                        __('auth.no-token-provided'),
                        __FUNCTION__,
                        false
                    );
                }

            } else if (isset($token) || isset($refreshToken)) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(true),
                    __('auth.tokens-not-allowed'),
                    __FUNCTION__,
                    false
                );
            }

            if (isset($token) && isset($refreshToken)) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(true),
                    __('auth.double-token-given'),
                    __FUNCTION__,
                    false
                );
            }

            /** @var \App\Models\User $user */
            $user = Auth::user();

            if (isset($token) && !$user) {
                throw new ApiException(
                    DefaultErrorCode::UNAUTHENTICATED(true),
                    __('auth.invalid-token'),
                    __FUNCTION__,
                    false
                );
            }

            if (isset($refreshToken) && !$personalAccessToken) {
                throw new ApiException(
                    DefaultErrorCode::UNAUTHENTICATED(true),
                    __('auth.invalid-refresh-token'),
                    __FUNCTION__,
                    false
                );
            }

            if (isset($token) && $user) {

                /** @var PersonalAccessToken $personalAccessToken */
                $personalAccessToken = $user->tokenable()->first();

                if (Validation::timeComparison($personalAccessToken->created_at, env('JWT_LIFETIME'), '>')) {

                    if ($personalAccessToken->expiry_alert_at === null) {

                        $personalAccessToken->expiry_alert_at = now();
                        $personalAccessToken->save();

                        throw new ApiException(
                            DefaultErrorCode::UNAUTHENTICATED(),
                            __('auth.token-expired'),
                            __FUNCTION__,
                            false
                        );

                    } else {
                        throw new ApiException(
                            DefaultErrorCode::UNAUTHENTICATED(true),
                            __('auth.token-expired'),
                            __FUNCTION__,
                            false
                        );
                    }
                }
            }

            if (isset($refreshToken) && $personalAccessToken &&
                Validation::timeComparison($personalAccessToken->created_at, env('JWT_LIFETIME'), '<='))
            {
                throw new ApiException(
                    DefaultErrorCode::UNAUTHENTICATED(true),
                    __('auth.token-still-valid'),
                    __FUNCTION__,
                    false
                );
            }

            if ($user && $user->blocked_at) {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(true),
                    __('auth.user-blocked'),
                    __FUNCTION__
                );
            }

            if (isset($refreshToken) && $personalAccessToken) {
                $personalAccessToken->delete();
                Encrypter::generateAuthTokens();
            }

            self::validateRequestFields($request, $routeName);
        }
    }

    private static function isConvex(array $polygon) {

        $flag = 0;
        $pointsNumber = count($polygon) - 1;

        for ($i=0; $i<$pointsNumber; $i++) {

            $j = ($i + 1) % $pointsNumber;
            $k = ($i + 2) % $pointsNumber;
            $z = ($polygon[$j][0] - $polygon[$i][0]) * ($polygon[$k][1] - $polygon[$j][1]);
            $z -= ($polygon[$j][1] - $polygon[$i][1]) * ($polygon[$k][0] - $polygon[$j][0]);

            if ($z < 0) {
                $flag |= 1;
            } else if ($z > 0) {
                $flag |= 2;
            }

            if ($flag == 3) {
                return false;
            }
        }

        if ($flag != 0) {
            return true;
        } else {
            return false;
        }
    }
}
