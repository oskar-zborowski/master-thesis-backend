<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\ErrorCodes\DefaultErrorCode;
use App\Http\Libraries\Encrypter;
use App\Http\Libraries\Geometry;
use App\Http\Libraries\Other;
use App\Http\Libraries\Validation;
use App\Http\Requests\CreatePlayerRequest;
use App\Http\Requests\SetRoleRequest;
use App\Http\Requests\SetStatusRequest;
use App\Http\Requests\UpdatePlayerRequest;
use App\Http\Responses\JsonResponse;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;

class PlayerController extends Controller
{
    /**
     * #### `POST` `/api/v1/players`
     * Stworzenie nowego gracza (dołączenie do pokoju)
     */
    public function createPlayer(CreatePlayerRequest $request) {

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->name === null) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.you-must-set-player-name'),
                __FUNCTION__
            );
        }

        if (strlen($request->code) != 6) {

            if (strlen($request->code) != 9 || substr($request->code, -3) != env('JOINING_ROOM_SPECIAL_SIGN')) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.size.string', ['size' => 6]),
                    __FUNCTION__
                );
            }

            $code = substr($request->code, 0, 6);

            $encryptedCode = Encrypter::encrypt($code, 6, false);
            $aesDecrypt = Encrypter::prepareAesDecrypt('code', $encryptedCode);

            /** @var Room $room */
            $room = Room::whereRaw($aesDecrypt)->orderBy('id', 'desc')->first();

            if (!$room) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.incorrect-code'),
                    __FUNCTION__
                );
            }

            /** @var Player $player */
            $player = $room->players()->where('user_id', $user->id)->first();

            if ($player) {

                if ($player->status == 'BANNED') {
                    throw new ApiException(
                        DefaultErrorCode::PERMISSION_DENIED(),
                        __('validation.custom.you-have-been-banned'),
                        __FUNCTION__
                    );
                } else if ($room->status == 'GAME_OVER') {
                    throw new ApiException(
                        DefaultErrorCode::PERMISSION_DENIED(),
                        __('validation.custom.game-is-over'),
                        __FUNCTION__
                    );
                }

                if ($room->status == 'WAITING_IN_ROOM') {
                    $player->role = null;
                }

                $player->status = 'SUPERVISING';
                $player->expected_time_at = date('Y-m-d H:i:s', strtotime('+' . env('ROOM_REFRESH') . ' seconds', strtotime(now())));
                $player->disconnecting_finished_at = null;
                $player->save();

            } else if ($room->status == 'GAME_OVER') {

                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.game-is-over'),
                    __FUNCTION__
                );

            } else {
                $player = new Player;
                $player->room_id = $room->id;
                $player->user_id = $user->id;
                $player->avatar = $user->default_avatar;
                $player->status = 'SUPERVISING';
                $player->expected_time_at = date('Y-m-d H:i:s', strtotime('+' . env('ROOM_REFRESH') . ' seconds', strtotime(now())));
                $player->save();
            }

            $room = $room->fresh();

            JsonResponse::sendSuccess($request, $room->getData(), null, 201);
        }

        $encryptedCode = Encrypter::encrypt($request->code, 6, false);
        $aesDecrypt = Encrypter::prepareAesDecrypt('code', $encryptedCode);

        /** @var Room $room */
        $room = Room::whereRaw($aesDecrypt)->orderBy('id', 'desc')->first();

        /** @var Player $player */
        $player = $user->players()->where('status', 'CONNECTED')->orderBy('id', 'desc')->first();

        if ($player) {

            /** @var Room $lastRoom */
            $lastRoom = $player->room()->first();

            if ($lastRoom->status != 'GAME_OVER' && (!$room || $room->id != $lastRoom->id)) {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.you-are-already-in-another-room'),
                    __FUNCTION__
                );
            }
        }

        if (!$room) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.incorrect-code'),
                __FUNCTION__
            );
        }

        /** @var Player $player */
        $player = $room->players()->where('user_id', $user->id)->first();

        if ($player) {

            if ($player->status == 'BANNED') {

                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.you-have-been-banned'),
                    __FUNCTION__
                );

            } else if ($player->status == 'LEFT') {

                if ($room->voting_type == 'START' || $room->status != 'WAITING_IN_ROOM') {
                    throw new ApiException(
                        DefaultErrorCode::PERMISSION_DENIED(),
                        __('validation.custom.game-already-started'),
                        __FUNCTION__
                    );
                }

                $this->checkRoomLimit($room);
                $this->checkAvatarExistence($player, $room);

            } else if ($player->status == 'DISCONNECTED') {

                if ($room->voting_type == 'START') {

                    throw new ApiException(
                        DefaultErrorCode::PERMISSION_DENIED(),
                        __('validation.custom.game-already-started'),
                        __FUNCTION__
                    );

                } else if ($room->status == 'GAME_OVER') {

                    throw new ApiException(
                        DefaultErrorCode::PERMISSION_DENIED(),
                        __('validation.custom.game-is-over'),
                        __FUNCTION__
                    );

                } else if ($room->status == 'WAITING_IN_ROOM') {
                    $this->checkRoomLimit($room);
                    $this->checkAvatarExistence($player, $room);
                } else {
                    $this->nextConnection($player, $room);
                }

            } else {

                if ($room->status == 'GAME_OVER') {
                    throw new ApiException(
                        DefaultErrorCode::PERMISSION_DENIED(),
                        __('validation.custom.game-is-over'),
                        __FUNCTION__
                    );
                }
            }

        } else if ($room->voting_type == 'START' || $room->status != 'WAITING_IN_ROOM') {

            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(),
                __('validation.custom.game-already-started'),
                __FUNCTION__
            );

        } else {

            $this->checkRoomLimit($room);

            $player = new Player;
            $player->room_id = $room->id;
            $player->user_id = $user->id;

            $avatar = $user->default_avatar;

            /** @var Player $isAvatarExists */
            $isAvatarExists = $room->players()->where([
                'avatar' => $avatar,
                'status' => 'CONNECTED',
            ])->first();

            if ($isAvatarExists) {
                $avatar = $this->findAvailableAvatar($room);
            }

            $player->avatar = $avatar;
            $player->expected_time_at = date('Y-m-d H:i:s', strtotime('+' . env('ROOM_REFRESH') . ' seconds', strtotime(now())));
            $player->save();

            $user->default_avatar = $avatar;
            $user->save();
        }

        /** @var \App\Models\Player $host */
        $host = $room->players()->where('user_id', $room->host_id)->first();

        if ($host->status != 'CONNECTED') {
            Other::setNewHost($room);
        }

        $room = $room->fresh();

        JsonResponse::sendSuccess($request, $room->getData(), null, 201);
    }

    /**
     * #### `PATCH` `/api/v1/players/my/last`
     * Edycja danych gracza (zmiana parametrów podczas oczekiwania w pokoju i w trakcie gry)
     */
    public function updatePlayer(UpdatePlayerRequest $request) {

        $reloadRoom = false;

        /** @var \App\Models\User $user */
        $user = Auth::user();

        /** @var Player $player */
        $player = $user->players()->orderBy('updated_at', 'desc')->first();

        if (!$player) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(),
                __('validation.custom.no-permission'),
                __FUNCTION__
            );
        }

        $playerUpdatedAt = $player->updated_at;

        if ($player->status == 'BANNED') {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(),
                __('validation.custom.you-have-been-banned'),
                __FUNCTION__
            );
        }

        /** @var Room $room */
        $room = $player->room()->first();

        if ($player->status == 'LEFT') {

            if ($player->warning_number > 0 && $player->warning_number > $room->config['other']['warning_number']) {

                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.warnings-number-exceeded'),
                    __FUNCTION__
                );

            } else {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.you-left-the-room'),
                    __FUNCTION__
                );
            }
        }

        if ($player->status == 'DISCONNECTED') {

            if ($room->voting_type == 'START') {

                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.game-already-started'),
                    __FUNCTION__
                );

            } else if ($room->status == 'WAITING_IN_ROOM') {
                $this->checkRoomLimit($room);
                $this->checkAvatarExistence($player, $room);
                $reloadRoom = true;
            } else {
                $this->nextConnection($player, $room);
                $reloadRoom = true;
            }

        } else if ($player->status == 'SUPERVISING') {

            if ($request->status !== null) {

                $player->global_position = null;
                $player->hidden_position = null;
                $player->fake_position = null;
                $player->is_catching = false;
                $player->is_caughting = false;
                $player->voting_answer = null;
                $player->status = $request->status;
                $player->failed_voting_type = null;
                $player->black_ticket_finished_at = null;
                $player->fake_position_finished_at = null;
                $player->disconnecting_finished_at = null;
                $player->crossing_boundary_finished_at = null;
                $player->speed_exceeded_at = null;
                $player->next_voting_starts_at = null;
                $player->save();

                if ($player->user_id == $room->host_id) {
                    Other::setNewHost($room);
                }
            }

            $minPause = null;

            /** @var Player $isAnyoneCaught */
            $isAnyoneCaught = $room->players()->where('is_caughting', true)->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->first();

            if ($room->voting_type && ($minPause === null || env('VOTING_REFRESH') < $minPause)) {
                $minPause = env('VOTING_REFRESH');
            }

            if ($isAnyoneCaught && ($minPause === null || env('CATCHING_REFRESH') < $minPause)) {
                $minPause = env('CATCHING_REFRESH');
            }

            if ($room->status == 'GAME_IN_PROGRESS' && ($minPause === null || env('GAME_REFRESH') < $minPause)) {
                $minPause = env('GAME_REFRESH');
            } else if ($room->status != 'GAME_IN_PROGRESS' && ($minPause === null || env('ROOM_REFRESH') < $minPause)) {
                $minPause = env('ROOM_REFRESH');
            }

            $player->expected_time_at = date('Y-m-d H:i:s', strtotime('+' . $minPause . ' seconds', strtotime(now())));
            $player->disconnecting_finished_at = null;
            $player->save();

            $room = $room->fresh();

            JsonResponse::sendSuccess($request, $room->getData());

        } else {
            $this->savePing($player);
            $this->nextConnection($player, $room);
            $reloadRoom = true;
        }

        if ($request->avatar !== null) {

            if ($room->voting_type == 'START' || $room->status != 'WAITING_IN_ROOM') {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.game-being-prepared'),
                    __FUNCTION__
                );
            }

            /** @var Player $avatarExists */
            $avatarExists = $room->players()->where([
                'avatar' => $request->avatar,
                'status' => 'CONNECTED',
            ])->where('id', '!=', $player->id)->first();

            if ($avatarExists) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.avatar-busy'),
                    __FUNCTION__
                );
            }

            $player->avatar = $request->avatar;
            $player->save();

            $user->default_avatar = $request->avatar;
            $user->save();

            $reloadRoom = true;
        }

        if ($room->status == 'GAME_IN_PROGRESS') {

            $now = now();

            Validation::checkGpsLocation($request->gps_location);

            $boundary = Geometry::convertGeometryLatLngToXY($room->boundary_points);

            $point = explode(' ', $request->gps_location);

            $p1['x'] = $point[0];
            $p1['y'] = $point[1];

            $point = Geometry::convertLatLngToXY($p1);

            $p = "{$point['x']} {$point['y']}";

            $isIntersects = DB::select(DB::raw("SELECT ST_Intersects(ST_GeomFromText('POLYGON(($boundary))'), ST_GeomFromText('POINT($p)')) AS isIntersects"));

            if (!$isIntersects[0]->isIntersects) {

                if ($room->config['other']['warning_number'] != -1) {
                    $player->warning_number = $player->warning_number + 1;
                }

                if ($room->config['other']['crossing_boundary_countdown'] != -1) {
                    $player->crossing_boundary_finished_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['other']['crossing_boundary_countdown'] . ' seconds', strtotime(now())));
                }

                $player->save();
                $reloadRoom = true;

            } else if ($player->crossing_boundary_finished_at) {
                $player->crossing_boundary_finished_at = null;
                $player->save();
                $reloadRoom = true;
            }

            if ($room->config['other']['max_speed'] != -1) {

                $timeDifference = strtotime($now) - strtotime($playerUpdatedAt);
                $maxDistance = $room->config['other']['max_speed'] * $timeDifference;

                $speedExceeded = DB::select(DB::raw("SELECT id FROM players WHERE id = $player->id AND ST_Distance_Sphere(ST_GeomFromText('POINT($request->gps_location)'), hidden_position) > $maxDistance"));

                if (count($speedExceeded) > 0) {

                    if ($room->config['other']['warning_number'] != -1) {
                        $player->warning_number = $player->warning_number + 1;
                    }

                    $player->speed_exceeded_at = $now;
                    $player->save();
                    $reloadRoom = true;
                }
            }

            if (!in_array($player->role, ['THIEF', 'AGENT']) && $room->config['actor']['thief']['visibility_radius'] == -1) {
                $player->global_position = DB::raw("ST_GeomFromText('POINT($request->gps_location)')");
                $reloadRoom = true;
            }

            if ($player->caught_at === null) {
                $player->hidden_position = DB::raw("ST_GeomFromText('POINT($request->gps_location)')");
                $player->save();
                $reloadRoom = true;
            }
        }

        if ($request->status !== null) {

            $player->global_position = null;
            $player->hidden_position = null;
            $player->fake_position = null;
            $player->is_catching = false;
            $player->is_caughting = false;
            $player->voting_answer = null;
            $player->status = $request->status;
            $player->failed_voting_type = null;
            $player->black_ticket_finished_at = null;
            $player->fake_position_finished_at = null;
            $player->disconnecting_finished_at = null;
            $player->crossing_boundary_finished_at = null;
            $player->speed_exceeded_at = null;
            $player->next_voting_starts_at = null;
            $player->save();

            if ($player->user_id == $room->host_id) {
                Other::setNewHost($room);
            }

            $reloadRoom = true;
        }

        if ($request->voting_type !== null) {

            $this->startVoting($room, $player, $request->voting_type, $request->is_replenishment_with_bots);

            $room->reporting_user_id = $user->id;
            $room->voting_type = $request->voting_type;
            $room->voting_ended_at = date('Y-m-d H:i:s', strtotime('+' . env('VOTING_DURATION') . ' seconds', strtotime(now())));
            $room->save();

            if (!in_array($request->voting_type, ['START', 'RESUME'])) {
                $player->next_voting_starts_at = date('Y-m-d H:i:s', strtotime('+' . env('BLOCKING_TIME_VOTING_START') . ' seconds', strtotime(now())));
            }

            $player->voting_answer = true;
            $player->save();

            $reloadRoom = true;

            shell_exec('php ' . env('APP_ROOT') . "artisan voting:check $room->id $user->id >/dev/null 2>/dev/null &");
        }

        if ($request->voting_answer !== null) {

            if ($room->voting_type === null) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.no-permission'),
                    __FUNCTION__
                );
            }

            if ($player->voting_answer !== null) {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(true),
                    __('validation.custom.no-permission'),
                    __FUNCTION__,
                    false
                );
            }

            $player->voting_answer = $request->voting_answer;
            $player->save();

            $reloadRoom = true;
        }

        if ($request->use_white_ticket) {

            $now = now();

            if ($room->status != 'GAME_IN_PROGRESS' || $player->role != 'PEGASUS' || $now < $room->game_started_at) {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.no-permission'),
                    __FUNCTION__
                );
            }

            if ($player->config['white_ticket']['number'] - $player->config['white_ticket']['used_number'] <= 0) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.no-white-ticket-available'),
                    __FUNCTION__
                );
            }

            /** @var Player[] $thieves */
            $thieves = $room->players()->where('role', 'THIEF')->where('caught_at', null)->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->get();

            foreach ($thieves as $thief) {

                if ($thief->black_ticket_finished_at === null || $now > $thief->black_ticket_finished_at) {

                    $thief->mergeCasts([
                        'global_position' => Point::class,
                        'hidden_position' => Point::class,
                        'fake_position' => Point::class,
                    ]);

                    if ($thief->fake_position_finished_at && $now <= $thief->fake_position_finished_at) {

                        if ($room->config['actor']['policeman']['visibility_radius'] != -1) {

                            $fakePosition = "{$thief->fake_position->longitude} {$thief->fake_position->latitude}";

                            $disclosureThiefByPoliceman = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND status = 'CONNECTED' AND crossing_boundary_finished_at IS NULL AND role <> 'THIEF' AND role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($fakePosition)'), hidden_position) <= {$room->config['actor']['policeman']['visibility_radius']}"));
                            $disclosureThiefByEagle = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND status = 'CONNECTED' AND crossing_boundary_finished_at IS NULL AND role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($fakePosition)'), hidden_position) <= {2 * $room->config['actor']['policeman']['visibility_radius']}"));

                            if (count($disclosureThiefByPoliceman) > 0 || count($disclosureThiefByEagle) > 0) {
                                $thief->global_position = $thief->fake_position;
                            }

                        } else {
                            $thief->global_position = $thief->fake_position;
                        }

                    } else {

                        if ($room->config['actor']['policeman']['visibility_radius'] != -1) {

                            $hiddenPosition = "{$thief->hidden_position->longitude} {$thief->hidden_position->latitude}";

                            $disclosureThiefByPoliceman = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND status = 'CONNECTED' AND crossing_boundary_finished_at IS NULL AND role <> 'THIEF' AND role <> 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($hiddenPosition)'), hidden_position) <= {$room->config['actor']['policeman']['visibility_radius']}"));
                            $disclosureThiefByEagle = DB::select(DB::raw("SELECT id FROM players WHERE room_id = $room->id AND status = 'CONNECTED' AND crossing_boundary_finished_at IS NULL AND role = 'EAGLE' AND ST_Distance_Sphere(ST_GeomFromText('POINT($hiddenPosition)'), hidden_position) <= {2 * $room->config['actor']['policeman']['visibility_radius']}"));

                            if (count($disclosureThiefByPoliceman) > 0 || count($disclosureThiefByEagle) > 0) {
                                $thief->global_position = $thief->hidden_position;
                            }

                        } else {
                            $thief->global_position = $thief->hidden_position;
                        }
                    }

                    $thief->save();
                }
            }

            $tempConfig = $player->config;
            $tempConfig['white_ticket']['used_number'] = $player->config['white_ticket']['used_number'] + 1;
            $player->config = $tempConfig;
            $player->save();

            $reloadRoom = true;
        }

        if ($request->use_black_ticket) {

            if ($room->status != 'GAME_IN_PROGRESS' || $player->role != 'THIEF' || now() < $room->game_started_at || $player->caught_at) {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.no-permission'),
                    __FUNCTION__
                );
            }

            if ($player->black_ticket_finished_at) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.black-ticket-active'),
                    __FUNCTION__
                );
            }

            if ($player->config['black_ticket']['number'] - $player->config['black_ticket']['used_number'] <= 0) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.no-black-ticket-available'),
                    __FUNCTION__
                );
            }

            $tempConfig = $player->config;
            $tempConfig['black_ticket']['used_number'] = $player->config['black_ticket']['used_number'] + 1;
            $player->config = $tempConfig;

            $player->black_ticket_finished_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['actor']['thief']['black_ticket']['duration'] . ' seconds', strtotime(now())));
            $player->save();

            $reloadRoom = true;
        }

        if ($request->use_fake_position !== null) {

            if ($room->status != 'GAME_IN_PROGRESS' || $player->role != 'THIEF' || now() < $room->game_started_at || $player->caught_at) {
                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(),
                    __('validation.custom.no-permission'),
                    __FUNCTION__
                );
            }

            if ($player->fake_position_finished_at) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.fake-position-active'),
                    __FUNCTION__
                );
            }

            if ($player->config['fake_position']['number'] - $player->config['fake_position']['used_number'] <= 0) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.no-fake-position-available'),
                    __FUNCTION__
                );
            }

            Validation::checkGpsLocation($request->use_fake_position);

            $boundary = Geometry::convertGeometryLatLngToXY($room->boundary_points);

            $point = explode(' ', $request->use_fake_position);

            $p1['x'] = $point[0];
            $p1['y'] = $point[1];

            $point = Geometry::convertLatLngToXY($p1);

            $p = "{$point['x']} {$point['y']}";

            $isIntersects = DB::select(DB::raw("SELECT ST_Intersects(ST_GeomFromText('POLYGON(($boundary))'), ST_GeomFromText('POINT($p)')) AS isIntersects"));

            if (!$isIntersects[0]->isIntersects) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.location-beyond-boundary'),
                    __FUNCTION__
                );
            }

            $tempConfig = $player->config;
            $tempConfig['fake_position']['used_number'] = $player->config['fake_position']['used_number'] + 1;
            $player->config = $tempConfig;

            $player->fake_position = DB::raw("ST_GeomFromText('POINT($request->use_fake_position)')");
            $player->fake_position_finished_at = date('Y-m-d H:i:s', strtotime('+' . $room->config['actor']['thief']['fake_position']['duration'] . ' seconds', strtotime(now())));
            $player->save();

            $reloadRoom = true;
        }

        if ($reloadRoom) {
            $room = $room->fresh();
        }

        JsonResponse::sendSuccess($request, $room->getData());
    }

    /**
     * #### `PUT` `/v1/players/{player}/status`
     * Ustawienie statusu gracza (endpoint tylko dla hosta, host nie może zmieniać swojego statusu - musi skorzystać w tym celu z updatePlayer)
     */
    public function setStatus(Player $player, SetStatusRequest $request) {

        /** @var \App\Models\User $user */
        $user = Auth::user();

        /** @var Room $room */
        $room = $player->room()->first();

        if ($user->id != $room->host_id || $user->id == $player->user_id ||
            in_array($room->voting_type, ['START', 'RESUME']) ||
            !in_array($room->status, ['WAITING_IN_ROOM', 'GAME_PAUSED']) ||
            in_array($player->status, ['CONNECTED', 'DISCONNECTED']) && $request->status == 'LEFT')
        {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('validation.custom.no-permission'),
                __FUNCTION__,
                false
            );
        }

        if ($room->status == 'WAITING_IN_ROOM') {
            $player->role = null;
        }

        $player->global_position = null;
        $player->hidden_position = null;
        $player->fake_position = null;
        $player->is_catching = false;
        $player->is_caughting = false;
        $player->voting_answer = null;
        $player->status = $request->status;
        $player->failed_voting_type = null;
        $player->black_ticket_finished_at = null;
        $player->fake_position_finished_at = null;
        $player->disconnecting_finished_at = null;
        $player->crossing_boundary_finished_at = null;
        $player->speed_exceeded_at = null;
        $player->next_voting_starts_at = null;
        $player->save();

        /** @var Player $host */
        $host = $room->players()->where('user_id', $user->id)->first();
        $host->expected_time_at = date('Y-m-d H:i:s', strtotime('+' . env('ROOM_REFRESH') . ' seconds', strtotime(now())));
        $host->save();

        $room = $room->fresh();

        JsonResponse::sendSuccess($request, $room->getData());
    }

    /**
     * #### `PUT` `/v1/players/{player}/role`
     * Ustawienie roli gracza (endpoint tylko dla hosta)
     */
    public function setRole(Player $player, SetRoleRequest $request) {

        /** @var \App\Models\User $user */
        $user = Auth::user();

        /** @var Room $room */
        $room = $player->room()->first();

        if ($user->id != $room->host_id || $room->voting_type == 'START' || $room->status != 'WAITING_IN_ROOM') {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('validation.custom.no-permission'),
                __FUNCTION__,
                false
            );
        }

        if ($player->status != 'CONNECTED') {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.user-is-not-in-room'),
                __FUNCTION__
            );
        }

        /** @var Player[] $players */
        $players = $room->players()->where([
            'role' => $request->role,
            'status' => 'CONNECTED',
        ])->get();

        $playersNumber = count($players);

        if ($request->role !== null && $request->role != $player->role &&
            $playersNumber >= $room->config['actor'][strtolower($request->role)]['number'])
        {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.max-player-number-reached'),
                __FUNCTION__
            );
        }

        $player->role = $request->role;
        $player->save();

        /** @var Player $host */
        $host = $room->players()->where('user_id', $user->id)->first();
        $host->expected_time_at = date('Y-m-d H:i:s', strtotime('+' . env('ROOM_REFRESH') . ' seconds', strtotime(now())));
        $host->save();

        $room = $room->fresh();

        JsonResponse::sendSuccess($request, $room->getData());
    }

    private function findAvailableAvatar(Room $room) {

        $i = 0;
        $avatars = Validation::getAvatars();
        shuffle($avatars);

        do {

            $avatar = $avatars[$i++];

            /** @var Player $isAvatarExists */
            $isAvatarExists = $room->players()->where([
                'avatar' => $avatar,
                'status' => 'CONNECTED',
            ])->first();

        } while ($isAvatarExists);

        return $avatar;
    }

    private function startVoting(Room $room, Player $player, string $votingType, ?bool $isReplenishmentWithBots) {

        if ($room->voting_type) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.voting-already-started'),
                __FUNCTION__
            );
        }

        if (!in_array($votingType, ['START', 'RESUME'])) {

            $now = now();

            if ($player->next_voting_starts_at && $now < $player->next_voting_starts_at) {

                $timeDifference = strtotime($player->next_voting_starts_at) - strtotime($now);

                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.voting-limit', ['seconds' => $timeDifference]),
                    __FUNCTION__
                );
            }
        }

        if ($votingType == 'START') {

            /** @var Player[] $allPlayers */
            $allPlayers = $room->players()->where('status', 'CONNECTED')->get();
            $allPlayersNumber = count($allPlayers);

            if ($player->user_id != $room->host_id || $room->status != 'WAITING_IN_ROOM') {

                throw new ApiException(
                    DefaultErrorCode::PERMISSION_DENIED(true),
                    __('validation.custom.no-permission'),
                    __FUNCTION__,
                    false
                );

            } else if ($room->boundary_points === null) {

                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.complete-boundary'),
                    __FUNCTION__
                );

            } else if ($room->config['actor']['policeman']['number'] + $room->config['actor']['thief']['number'] < $allPlayersNumber) {

                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.players-number-exceeded'),
                    __FUNCTION__
                );

            } else if (!$isReplenishmentWithBots && $room->config['actor']['policeman']['number'] + $room->config['actor']['thief']['number'] > $allPlayersNumber) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.not-enough-players'),
                    __FUNCTION__
                );
            }
        }

        if ($votingType == 'ENDING_COUNTDOWN') {
            if ($room->status != 'GAME_IN_PROGRESS') {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.no-permission'),
                    __FUNCTION__
                );
            } else if (now() >= $room->game_started_at) {
                throw new ApiException(
                    DefaultErrorCode::FAILED_VALIDATION(),
                    __('validation.custom.game-already-started'),
                    __FUNCTION__
                );
            }
        }

        if ($votingType == 'PAUSE' && $room->status != 'GAME_IN_PROGRESS') {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.no-permission'),
                __FUNCTION__
            );
        }

        if ($votingType == 'RESUME' && ($player->user_id != $room->host_id || $room->status != 'GAME_PAUSED')) {
            throw new ApiException(
                DefaultErrorCode::PERMISSION_DENIED(true),
                __('validation.custom.no-permission'),
                __FUNCTION__,
                false
            );
        }

        if (in_array($votingType, ['END_GAME', 'GIVE_UP']) && in_array($room->status, ['WAITING_IN_ROOM', 'GAME_OVER'])) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.no-permission'),
                __FUNCTION__
            );
        }
    }

    private function checkRoomLimit(Room $room) {

        /** @var Player[] $allPlayers */
        $allPlayers = $room->players()->where('status', 'CONNECTED')->get();
        $allPlayersNumber = count($allPlayers);

        if ($allPlayersNumber >= $room->config['actor']['policeman']['number'] + $room->config['actor']['thief']['number']) {
            throw new ApiException(
                DefaultErrorCode::FAILED_VALIDATION(),
                __('validation.custom.max-player-number-reached'),
                __FUNCTION__
            );
        }
    }

    private function checkAvatarExistence(Player $player, Room $room) {

        $avatar = $player->avatar;

        /** @var Player $isAvatarExists */
        $isAvatarExists = $room->players()->where([
            'avatar' => $avatar,
            'status' => 'CONNECTED',
        ])->first();

        if ($isAvatarExists) {
            $avatar = $this->findAvailableAvatar($room);
        }

        $player->avatar = $avatar;
        $player->role = null;
        $player->status = 'CONNECTED';
        $player->expected_time_at = date('Y-m-d H:i:s', strtotime('+' . env('ROOM_REFRESH') . ' seconds', strtotime(now())));
        $player->disconnecting_finished_at = null;
        $player->save();

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->default_avatar = $avatar;
        $user->save();
    }

    private function nextConnection(Player $player, Room $room) {

        $minPause = null;

        /** @var Player $isAnyoneCaught */
        $isAnyoneCaught = $room->players()->where('is_caughting', true)->whereIn('status', ['CONNECTED', 'DISCONNECTED'])->first();

        if ($room->voting_type && ($minPause === null || env('VOTING_REFRESH') < $minPause)) {
            $minPause = env('VOTING_REFRESH');
        }

        if ($isAnyoneCaught && ($minPause === null || env('CATCHING_REFRESH') < $minPause)) {
            $minPause = env('CATCHING_REFRESH');
        }

        if ($room->status == 'GAME_IN_PROGRESS' && ($minPause === null || env('GAME_REFRESH') < $minPause)) {
            $minPause = env('GAME_REFRESH');
        } else if ($room->status != 'GAME_IN_PROGRESS' && ($minPause === null || env('ROOM_REFRESH') < $minPause)) {
            $minPause = env('ROOM_REFRESH');
        }

        $player->status = 'CONNECTED';
        $player->expected_time_at = date('Y-m-d H:i:s', strtotime('+' . $minPause . ' seconds', strtotime(now())));
        $player->disconnecting_finished_at = null;
        $player->save();
    }
}
