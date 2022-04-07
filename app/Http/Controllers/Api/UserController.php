<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Libraries\Encrypter;
use App\Http\Libraries\FieldConversion;
use App\Http\Libraries\Validation;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Responses\JsonResponse;
use App\Models\GpsLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use MatanYadaev\EloquentSpatial\Objects\Point;
use maxh\Nominatim\Nominatim;

class UserController extends Controller
{
    /**
     * #### `POST` `/api/v1/users`
     * Stworzenie nowego użytkownika
     */
    public function createUser(CreateUserRequest $request) {

        $user = new User;
        $user->name = null;
        $user->default_avatar = Validation::chooseAvatar();
        $user->producer = $request->producer;
        $user->model = $request->model;
        $user->os_name = $request->os_name;
        $user->os_version = $request->os_version;
        $user->app_version = $request->app_version;
        $user->uuid = $request->uuid;
        $user->save();

        Auth::loginUsingId($user->id);

        $this->saveGpsLog($request->latitude, $request->longitude);

        Encrypter::generateAuthTokens();
        JsonResponse::sendSuccess($request, $user->getData(), null, 201);
    }

    /**
     * #### `PATCH` `/api/v1/users/me`
     * Edycja danych użytkownika
     */
    public function updateUser(UpdateUserRequest $request) {

        /** @var User $user */
        $user = Auth::user();

        if ($request->name !== null) {
            $user->name = $request->name;
        }

        if ($request->os_version !== null) {
            $user->os_version = $request->os_version;
        }

        if ($request->app_version !== null) {
            $user->app_version = $request->app_version;
        }

        $user->save();

        if ($request->latitude !== null && $request->longitude !== null) {

            $startDate = date('Y-m-d 00:00:00');
            $endDate = date('Y-m-d 23:59:59');

            /** @var GpsLog $gpsLog */
            $gpsLog = $user->gpsLogs()->where('created_at', '>=', $startDate)->where('created_at', '<=', $endDate)->first();

            if (!$gpsLog) {
                $this->saveGpsLog($request->latitude, $request->longitude);
            }
        }

        JsonResponse::sendSuccess($request, $user->getData());
    }

    private function saveGpsLog($latitude, $longitude) {

        $url = 'https://nominatim.openstreetmap.org';
        $nominatim = new Nominatim($url);

        $reverse = $nominatim->newReverse()->latlon($latitude, $longitude);
        $result = $nominatim->find($reverse)['address'];

        /** @var User $user */
        $user = Auth::user();

        $gpsLog = new GpsLog;
        $gpsLog->user_id = $user->id;
        $gpsLog->gps_location = new Point($latitude, $longitude);

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
            $voivodeship = str_replace('województwo ', '', $voivodeship);
            $gpsLog->voivodeship = $voivodeship;
        }

        if (isset($result['country'])) {
            $gpsLog->country = FieldConversion::stringToUppercase($result['country'], true);
        }

        $gpsLog->save();
    }
}
