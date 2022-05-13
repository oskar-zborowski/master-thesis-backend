<?php

namespace App\Http\Libraries;

use App\Exceptions\ApiException;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Models\IpAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'producer' => 'nullable|string|between:1,30',
            'model' => 'nullable|string|between:1,50',
            'os_name' => ['nullable', Rule::in(self::getOsNames())],
            'os_version' => 'nullable|string|between:1,10',
            'app_version' => ['required', Rule::in(self::getAppVersions())],
            'uuid' => 'nullable|string|between:1,45',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ];
    }

    public static function user_updateUser() {
        return [
            'name' => 'nullable|string|between:1,15',
            'os_version' => 'nullable|string|between:1,10',
            'app_version' => ['required', Rule::in(self::getAppVersions())],
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ];
    }

    public static function room_updateRoom() {
        return [
            'host_id' => 'nullable|integer|exists:users,id',
            'game_mode' => ['nullable', Rule::in(self::getGameModes())],
            'actor_policeman_number' => 'nullable|integer|between:1,25',
            'actor_thief_number' => 'nullable|integer|between:1,5',
            'actor_agent_number' => 'nullable|integer|between:0,25',
            'actor_saboteur_number' => 'nullable|integer|between:0,25',
            'actor_saboteur_probability' => 'nullable|numeric|between:0,1',
            'game_duration_scheduled' => 'nullable|integer|between:900,10800',
            'game_duration_escape_time' => 'nullable|integer|between:300,1800',
            'catching_number' => 'nullable|integer|between:1,5',
            'catching_radius' => 'nullable|integer|between:50,500',
            'catching_time' => 'nullable|integer|between:0,30',
            'disclosure_interval' => 'nullable|integer|between:-1,1800',
            'disclosure_after_starting' => 'nullable|boolean',
            'disclosure_thief_direction' => 'nullable|boolean',
            'disclosure_short_distance' => 'nullable|boolean',
            'disclosure_thief_knows_when' => 'nullable|boolean',
            'disclosure_policeman_sees_agent' => 'nullable|boolean',
            'disclosure_saboteur_sees_thief' => 'nullable|boolean',
            'disclosure_thief_knows_saboteur' => 'nullable|boolean',
            'disclosure_after_crossing_border' => 'nullable|boolean',
            'mission_number' => 'nullable|integer|between:3,50',
            'mission_radius' => 'nullable|integer|between:50,250',
            'mission_time' => 'nullable|integer|between:0,30',
            'mission_all_visible' => 'nullable|boolean',
            'monitoring_number' => 'nullable|integer|between:0,10',
            'monitoring_radius' => 'nullable|integer|between:50,250',
            'monitoring_central_number' => 'nullable|integer|between:0,5',
            'monitoring_central_radius' => 'nullable|integer|between:50,250',
            'ticket_black_number' => 'nullable|integer|between:0,5',
            'ticket_black_probability' => 'nullable|numeric|between:0,1',
            'ticket_white_number' => 'nullable|integer|between:0,3',
            'ticket_white_probability' => 'nullable|numeric|between:0,1',
            'fake_position_number' => 'nullable|integer|between:0,3',
            'fake_position_probability' => 'nullable|numeric|between:0,1',
            'game_pause_after_disconnecting' => 'nullable|boolean',
            'game_pause_after_crossing_border' => 'nullable|boolean',
            'other_warning_number' => 'nullable|integer|between:-1,5',
            'other_crossing_border_countdown' => 'nullable|integer|between:-1,120',
            'other_max_speed' => 'nullable|numeric|between:-1,10',
            'other_bot_speed' => 'nullable|numeric|between:2,10',
            'boundary' => 'nullable|array|between:1,31',
            'monitoring_cameras' => 'nullable|array|between:1,10',
            'monitoring_centrals' => 'nullable|array|between:1,5',
            'geometries_confirmed' => 'nullable|boolean',
            'status' => ['nullable', Rule::in(['GAME_IN_PROGRESS', 'GAME_PAUSED'])],
        ];
    }

    public static function player_createPlayer() {
        return [
            'code' => 'required|string|size:6',
        ];
    }

    public static function player_updatePlayer() {
        return [
            //
        ];
    }

    public static function getAvatars() {
        return [
            'AVATAR_1',
            'AVATAR_2',
            'AVATAR_3',
            'AVATAR_4',
            'AVATAR_5',
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

    public static function getGameModes() {
        return [
            'SCOTLAND_YARD',
            'MISSION_IMPOSSIBLE',
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
            'POLICEMEN_WON_ON_TIME',
            'THIEVES_WON_BY_COMPLETING_MISSIONS',
            'THIEVES_WON_ON_TIME',
        ];
    }

    public static function getPlayerRoles() {
        return [
            'POLICEMAN',
            'THIEF',
            'AGENT',
            'SABOTEUR',
        ];
    }

    public static function getPlayerStatuses() {
        return [
            'DETECTED_BY_CAMERA',
            'BORDER_CROSSED',
            'DISCONNECTED',
            'BLOCKED',
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

    /**
     * Sprawdzenie czy upłynął określony czas
     * 
     * @param string $timeReferencePoint punkt odniesienia względem którego liczony jest czas
     * @param int $timeMarker wartość znacznika czasu przez jak długo jest ważny
     * @param string $comparator jeden z symboli <, >, == lub ich kombinacja, liczone względem bieżącego czasu
     * @param string $unit jednostka w jakiej wyrażony jest $timeMarker
     */
    public static function timeComparison(string $timeReferencePoint, int $timeMarker, string $comparator, string $unit = 'minutes') {

        $now = date('Y-m-d H:i:s');
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
                    __FUNCTION__
                );
            }
        }
    }

    public static function secondAuthenticate(Request $request, bool $thrownError = false) {

        // $encryptedIpAddress = Encrypter::encrypt($request->ip(), 45, false); // TODO Odkomentować przy wdrożeniu na serwer
        $encryptedIpAddress = Encrypter::encrypt('83.8.175.174', 45, false); // TODO Zakomentować przy wdrożeniu na serwer
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

        if (!$thrownError) {

            $token = $request->header('token');
            $refreshToken = $request->header('refreshToken');

            $routeName = Route::currentRouteName();

            $routeNamesWhitelist = Session::get('routeNamesWhitelist');
            Session::remove('routeNamesWhitelist');

            $personalAccessToken = Session::get('personalAccessToken');
            Session::remove('personalAccessToken');

            if ($request->token !== null) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(true),
                    __('auth.invalid-token-format'),
                    __FUNCTION__
                );
            }

            if ($request->refresh_token !== null) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(true),
                    __('auth.invalid-refresh-token-format'),
                    __FUNCTION__
                );
            }

            if (!in_array($routeName, $routeNamesWhitelist)) {
                if (!isset($token) && !isset($refreshToken)) {
                    throw new ApiException(
                        DefaultErrorCode::FAILED_VALIDATION(true),
                        __('auth.no-token-provided'),
                        __FUNCTION__
                    );
                }
            } else if (isset($token) || isset($refreshToken)) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(true),
                    __('auth.tokens-not-allowed'),
                    __FUNCTION__
                );
            }

            if (isset($token) && isset($refreshToken)) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(true),
                    __('auth.double-token-given'),
                    __FUNCTION__
                );
            }

            /** @var \App\Models\User $user */
            $user = Auth::user();

            if (isset($token) && !$user) {
                throw new ApiException(
                    DefaultErrorCode::UNAUTHENTICATED(true),
                    __('auth.invalid-token'),
                    __FUNCTION__
                );
            }

            if (isset($refreshToken) && !$personalAccessToken) {
                throw new ApiException(
                    DefaultErrorCode::UNAUTHENTICATED(true),
                    __('auth.invalid-refresh-token'),
                    __FUNCTION__
                );
            }

            if (isset($token) && $user) {

                /** @var PersonalAccessToken $personalAccessToken */
                $personalAccessToken = $user->tokenable()->first();

                if (Validation::timeComparison($personalAccessToken->created_at, env('JWT_LIFETIME'), '>')) {

                    if (!$personalAccessToken->expiry_alert_at) {

                        $personalAccessToken->expiry_alert_at = now();
                        $personalAccessToken->save();

                        throw new ApiException(
                            DefaultErrorCode::UNAUTHENTICATED(),
                            __('auth.token-expired'),
                            __FUNCTION__
                        );

                    } else {
                        throw new ApiException(
                            DefaultErrorCode::UNAUTHENTICATED(true),
                            __('auth.token-expired'),
                            __FUNCTION__
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
                    __FUNCTION__
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
}
